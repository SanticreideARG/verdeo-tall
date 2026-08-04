import type { RowDataPacket } from 'mysql2/promise';
import type { PoolClient } from 'pg';
import { loadConfig } from '../config.js';
import { createMySqlPool } from '../infrastructure/mysql.js';
import { createPostgresPool } from '../infrastructure/postgres.js';
import {
  mapLegacyStatus,
  normalizeLegacyMessage,
  parseLegacyMessages,
} from '../migration/legacy-messaging.js';

const sourceSystem = 'laravel_mysql';
const lockName = 'verdeo:messaging:legacy-backfill';

type LegacyConversationRow = RowDataPacket & {
  id: number;
  zone: string;
  channel: string;
  channelId: string | null;
  phone: string | null;
  name: string | null;
  status: string;
  lastMessage: string | null;
  lastMessageAt: string | null;
  messages: unknown;
  createdAt: string | null;
  updatedAt: string | null;
};

type ImportStats = {
  conversations: number;
  contactParticipants: number;
  messages: number;
  messagesWithoutBody: number;
};

type Reconciliation = {
  actual: Omit<ImportStats, 'messagesWithoutBody'>;
  expected: Omit<ImportStats, 'messagesWithoutBody'>;
  matches: boolean;
};

function mysqlTimestamp(value: string | null, fallback = '1970-01-01T00:00:00.000Z'): string {
  if (!value) {
    return fallback;
  }

  const candidate = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)
    ? `${value.replace(' ', 'T')}Z`
    : value;
  const parsed = new Date(candidate);

  return Number.isNaN(parsed.getTime()) ? fallback : parsed.toISOString();
}

async function upsertSystemParticipant(client: PoolClient): Promise<string> {
  const result = await client.query<{ id: string }>(`
    INSERT INTO messaging.participants (
      source_system, source_ref, kind, display_name, metadata
    ) VALUES ($1, 'system:verdeo', 'system', 'Verdeo', '{}'::jsonb)
    ON CONFLICT (source_system, source_ref) DO UPDATE
    SET display_name = EXCLUDED.display_name,
        updated_at = now()
    RETURNING id
  `, [sourceSystem]);

  return result.rows[0]!.id;
}

async function importConversation(
  client: PoolClient,
  row: LegacyConversationRow,
  systemParticipantId: string,
  timezoneOffset: string,
): Promise<Pick<ImportStats, 'messages' | 'messagesWithoutBody'>> {
  const createdAt = mysqlTimestamp(row.createdAt ?? row.lastMessageAt ?? row.updatedAt);
  const updatedAt = mysqlTimestamp(row.updatedAt, createdAt);
  const lastMessageAt = mysqlTimestamp(row.lastMessageAt, createdAt);
  const contactSourceRef = `conversation:${row.id}:contact`;
  const contactResult = await client.query<{ id: string }>(`
    INSERT INTO messaging.participants (
      source_system, source_ref, kind, display_name, phone, metadata, created_at, updated_at
    ) VALUES ($1, $2, 'contact', $3, $4, $5::jsonb, $6, $7)
    ON CONFLICT (source_system, source_ref) DO UPDATE
    SET display_name = EXCLUDED.display_name,
        phone = EXCLUDED.phone,
        metadata = EXCLUDED.metadata,
        updated_at = EXCLUDED.updated_at
    RETURNING id
  `, [
    sourceSystem,
    contactSourceRef,
    row.name,
    row.phone,
    JSON.stringify({ legacyConversationId: row.id }),
    createdAt,
    updatedAt,
  ]);
  const contactParticipantId = contactResult.rows[0]!.id;

  const conversationResult = await client.query<{ id: string }>(`
    INSERT INTO messaging.conversations (
      source_system, source_ref, channel_id, external_ref, zone, status,
      last_message_at, metadata, created_at, updated_at
    ) VALUES (
      $1, $2,
      (SELECT id FROM messaging.channels WHERE code = $3),
      $4, $5, $6, $7, $8::jsonb, $9, $10
    )
    ON CONFLICT (source_system, source_ref) DO UPDATE
    SET channel_id = EXCLUDED.channel_id,
        external_ref = EXCLUDED.external_ref,
        zone = EXCLUDED.zone,
        status = EXCLUDED.status,
        last_message_at = EXCLUDED.last_message_at,
        metadata = EXCLUDED.metadata,
        updated_at = EXCLUDED.updated_at
    RETURNING id
  `, [
    sourceSystem,
    `conversation:${row.id}`,
    row.channel,
    row.channelId,
    row.zone,
    mapLegacyStatus(row.status),
    lastMessageAt,
    JSON.stringify({
      legacyId: row.id,
      legacyStatus: row.status,
      legacyLastMessage: row.lastMessage,
    }),
    createdAt,
    updatedAt,
  ]);
  const conversationId = conversationResult.rows[0]!.id;

  await client.query(`
    INSERT INTO messaging.conversation_participants (
      conversation_id, participant_id, role, joined_at
    ) VALUES ($1, $2, 'contact', $4), ($1, $3, 'bot', $4)
    ON CONFLICT (conversation_id, participant_id, role) DO NOTHING
  `, [conversationId, contactParticipantId, systemParticipantId, createdAt]);

  const messages = parseLegacyMessages(row.messages);
  let messagesWithoutBody = 0;

  for (const [index, legacyMessage] of messages.entries()) {
    const message = normalizeLegacyMessage(
      row.id,
      index,
      legacyMessage,
      timezoneOffset,
      createdAt,
    );
    const senderParticipantId = message.direction === 'inbound'
      ? contactParticipantId
      : systemParticipantId;

    if (message.body === null) {
      messagesWithoutBody += 1;
    }

    await client.query(`
      INSERT INTO messaging.messages (
        source_system, source_ref, conversation_id, sender_participant_id,
        direction, type, body, occurred_at, status, raw_payload
      ) VALUES ($1, $2, $3, $4, $5, 'text', $6, $7, $8, $9::jsonb)
      ON CONFLICT (source_system, source_ref) DO UPDATE
      SET conversation_id = EXCLUDED.conversation_id,
          sender_participant_id = EXCLUDED.sender_participant_id,
          direction = EXCLUDED.direction,
          body = EXCLUDED.body,
          occurred_at = EXCLUDED.occurred_at,
          status = EXCLUDED.status,
          raw_payload = EXCLUDED.raw_payload,
          updated_at = now()
    `, [
      sourceSystem,
      message.sourceRef,
      conversationId,
      senderParticipantId,
      message.direction,
      message.body,
      message.occurredAt,
      message.direction === 'inbound' ? 'received' : 'sent',
      JSON.stringify(message.rawPayload),
    ]);
  }

  return { messages: messages.length, messagesWithoutBody };
}

async function reconcile(client: PoolClient, expected: ImportStats): Promise<Reconciliation> {
  const result = await client.query<{
    conversations: string;
    contact_participants: string;
    messages: string;
  }>(`
    SELECT
      (SELECT count(*) FROM messaging.conversations WHERE source_system = $1) AS conversations,
      (SELECT count(*) FROM messaging.participants WHERE source_system = $1 AND kind = 'contact') AS contact_participants,
      (SELECT count(*) FROM messaging.messages WHERE source_system = $1) AS messages
  `, [sourceSystem]);
  const row = result.rows[0]!;
  const actual = {
    conversations: Number(row.conversations),
    contactParticipants: Number(row.contact_participants),
    messages: Number(row.messages),
  };
  const expectedCounts = {
    conversations: expected.conversations,
    contactParticipants: expected.contactParticipants,
    messages: expected.messages,
  };

  return {
    actual,
    expected: expectedCounts,
    matches: actual.conversations === expectedCounts.conversations
      && actual.contactParticipants === expectedCounts.contactParticipants
      && actual.messages === expectedCounts.messages,
  };
}

async function backfill(): Promise<void> {
  const apply = process.argv.includes('--apply');
  const config = loadConfig();
  const requestedBatchSize = Number(process.env.BACKFILL_BATCH_SIZE ?? 100);
  const batchSize = Number.isInteger(requestedBatchSize)
    ? Math.max(1, Math.min(requestedBatchSize, 500))
    : 100;
  const mysql = createMySqlPool(config.mysql);
  const postgres = createPostgresPool(config.postgres);
  const client = await postgres.connect();
  const stats: ImportStats = {
    conversations: 0,
    contactParticipants: 0,
    messages: 0,
    messagesWithoutBody: 0,
  };
  let runId: string | undefined;

  try {
    const lock = await client.query<{ acquired: boolean }>(
      'SELECT pg_try_advisory_lock(hashtext($1)) AS acquired',
      [lockName],
    );
    if (!lock.rows[0]?.acquired) {
      throw new Error('Another messaging backfill is already running');
    }

    if (apply) {
      const run = await client.query<{ id: string }>(`
        INSERT INTO messaging.migration_runs (source_system, mode, status)
        VALUES ($1, 'apply', 'running')
        RETURNING id
      `, [sourceSystem]);
      runId = run.rows[0]!.id;
    }

    const systemParticipantId = apply
      ? await upsertSystemParticipant(client)
      : undefined;
    let lastId = 0;

    while (true) {
      const [rows] = await mysql.query<LegacyConversationRow[]>(`
        SELECT
          id,
          zona AS zone,
          canal AS channel,
          canal_id AS channelId,
          telefono AS phone,
          nombre AS name,
          estado AS status,
          ultimo_mensaje AS lastMessage,
          ultimo_mensaje_at AS lastMessageAt,
          mensajes AS messages,
          created_at AS createdAt,
          updated_at AS updatedAt
        FROM conversaciones
        WHERE id > ?
        ORDER BY id
        LIMIT ${batchSize}
      `, [lastId]);

      if (rows.length === 0) {
        break;
      }

      if (apply) {
        await client.query('BEGIN');
      }

      try {
        for (const row of rows) {
          const messages = parseLegacyMessages(row.messages);
          stats.conversations += 1;
          stats.contactParticipants += 1;
          stats.messages += messages.length;
          stats.messagesWithoutBody += messages.filter((message) => typeof message.texto !== 'string').length;

          if (apply) {
            const imported = await importConversation(
              client,
              row,
              systemParticipantId!,
              config.legacyTimezoneOffset,
            );
            if (imported.messages !== messages.length) {
              throw new Error(`Message count changed while importing conversation ${row.id}`);
            }
          }

          lastId = row.id;
        }

        if (apply) {
          await client.query('COMMIT');
        }
      } catch (error) {
        if (apply) {
          await client.query('ROLLBACK');
        }
        throw error;
      }
    }

    const reconciliation = await reconcile(client, stats);

    if (apply && runId) {
      await client.query(`
        UPDATE messaging.migration_runs
        SET status = $2,
            stats = $3::jsonb,
            finished_at = now()
        WHERE id = $1
      `, [
        runId,
        reconciliation.matches ? 'completed' : 'failed',
        JSON.stringify({ ...stats, reconciliation }),
      ]);
    }

    console.info(JSON.stringify({
      mode: apply ? 'apply' : 'dry-run',
      sourceSystem,
      stats,
      reconciliation,
    }, null, 2));

    if (apply && !reconciliation.matches) {
      process.exitCode = 1;
    }
  } catch (error) {
    if (apply && runId) {
      await client.query('ROLLBACK');
      await client.query(`
        UPDATE messaging.migration_runs
        SET status = 'failed',
            error_message = $2,
            stats = $3::jsonb,
            finished_at = now()
        WHERE id = $1
      `, [runId, error instanceof Error ? error.message : String(error), JSON.stringify(stats)]);
    }
    throw error;
  } finally {
    await client.query('SELECT pg_advisory_unlock(hashtext($1))', [lockName]);
    client.release();
    await Promise.all([mysql.end(), postgres.end()]);
  }
}

backfill().catch((error: unknown) => {
  console.error(error);
  process.exitCode = 1;
});
