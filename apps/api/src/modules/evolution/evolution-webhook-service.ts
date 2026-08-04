import type { Pool, PoolClient } from 'pg';
import {
  normalizeEvolutionPayload,
  type EvolutionWebhookPayload,
  type NormalizedEvolutionMessage,
} from './evolution-payload.js';

const sourceSystem = 'evolution';

export type EvolutionIngestionResult = {
  ingestionEventId: string;
  duplicate: boolean;
  processed: boolean;
};

export interface EvolutionWebhookHandler {
  ingest(payload: EvolutionWebhookPayload): Promise<EvolutionIngestionResult>;
}

async function upsertParticipant(
  client: PoolClient,
  sourceRef: string,
  kind: 'contact' | 'system',
  displayName: string | null,
  phone: string | null,
  metadata: Record<string, unknown>,
): Promise<string> {
  const result = await client.query<{ id: string }>(`
    INSERT INTO messaging.participants (
      source_system, source_ref, kind, display_name, phone, metadata
    ) VALUES ($1, $2, $3, $4, $5, $6::jsonb)
    ON CONFLICT (source_system, source_ref) DO UPDATE
    SET display_name = COALESCE(EXCLUDED.display_name, messaging.participants.display_name),
        phone = COALESCE(EXCLUDED.phone, messaging.participants.phone),
        metadata = messaging.participants.metadata || EXCLUDED.metadata,
        updated_at = now()
    RETURNING id
  `, [sourceSystem, sourceRef, kind, displayName, phone, JSON.stringify(metadata)]);

  return result.rows[0]!.id;
}

async function persistMessage(
  client: PoolClient,
  event: NormalizedEvolutionMessage,
): Promise<string> {
  const contactParticipantId = await upsertParticipant(
    client,
    event.contactRef!,
    'contact',
    event.displayName,
    event.phone,
    { instance: event.instance, remoteJid: event.remoteJid },
  );
  const systemParticipantId = await upsertParticipant(
    client,
    `instance:${event.instance}:self`,
    'system',
    `Verdeo (${event.instance})`,
    null,
    { instance: event.instance },
  );
  const conversationResult = await client.query<{ id: string }>(`
    INSERT INTO messaging.conversations (
      source_system, source_ref, channel_id, external_ref, status,
      last_message_at, metadata
    ) VALUES (
      $1, $2,
      (SELECT id FROM messaging.channels WHERE code = 'whatsapp'),
      $3, 'open', $4, $5::jsonb
    )
    ON CONFLICT (source_system, source_ref) DO UPDATE
    SET external_ref = EXCLUDED.external_ref,
        last_message_at = GREATEST(
          messaging.conversations.last_message_at,
          EXCLUDED.last_message_at
        ),
        metadata = messaging.conversations.metadata || EXCLUDED.metadata,
        updated_at = now()
    RETURNING id
  `, [
    sourceSystem,
    event.conversationRef,
    event.remoteJid,
    event.occurredAt,
    JSON.stringify({ instance: event.instance, remoteJid: event.remoteJid }),
  ]);
  const conversationId = conversationResult.rows[0]!.id;

  await client.query(`
    INSERT INTO messaging.conversation_participants (
      conversation_id, participant_id, role
    ) VALUES ($1, $2, 'contact'), ($1, $3, 'bot')
    ON CONFLICT (conversation_id, participant_id, role) DO NOTHING
  `, [conversationId, contactParticipantId, systemParticipantId]);

  const senderParticipantId = event.fromMe ? systemParticipantId : contactParticipantId;
  const messageResult = await client.query<{ id: string }>(`
    INSERT INTO messaging.messages (
      source_system, source_ref, conversation_id, sender_participant_id,
      direction, type, body, occurred_at, status, deleted_at, raw_payload
    ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11::jsonb)
    ON CONFLICT (source_system, source_ref) DO UPDATE
    SET conversation_id = EXCLUDED.conversation_id,
        sender_participant_id = EXCLUDED.sender_participant_id,
        direction = EXCLUDED.direction,
        type = CASE
          WHEN messaging.messages.type = 'other' THEN EXCLUDED.type
          ELSE messaging.messages.type
        END,
        body = COALESCE(messaging.messages.body, EXCLUDED.body),
        occurred_at = CASE
          WHEN messaging.messages.body IS NULL AND EXCLUDED.body IS NOT NULL
            THEN EXCLUDED.occurred_at
          ELSE messaging.messages.occurred_at
        END,
        status = CASE
          WHEN messaging.messages.status = 'deleted' OR EXCLUDED.status = 'deleted'
            THEN 'deleted'
          WHEN array_position(
            ARRAY['failed', 'pending', 'received', 'sent', 'delivered', 'read'],
            EXCLUDED.status
          ) >= array_position(
            ARRAY['failed', 'pending', 'received', 'sent', 'delivered', 'read'],
            messaging.messages.status
          ) THEN EXCLUDED.status
          ELSE messaging.messages.status
        END,
        deleted_at = COALESCE(messaging.messages.deleted_at, EXCLUDED.deleted_at),
        raw_payload = messaging.messages.raw_payload || EXCLUDED.raw_payload,
        updated_at = now()
    RETURNING id
  `, [
    sourceSystem,
    event.sourceRef,
    conversationId,
    senderParticipantId,
    event.direction,
    event.type,
    event.body,
    event.occurredAt,
    event.status,
    event.deletedAt,
    JSON.stringify(event.rawPayload),
  ]);

  return messageResult.rows[0]!.id;
}

export class PostgresEvolutionWebhookService implements EvolutionWebhookHandler {
  public constructor(private readonly pool: Pool) {}

  public async ingest(payload: EvolutionWebhookPayload): Promise<EvolutionIngestionResult> {
    const event = normalizeEvolutionPayload(payload);
    const client = await this.pool.connect();

    try {
      await client.query('BEGIN');
      const ingestionResult = await client.query<{ id: string }>(`
        INSERT INTO messaging.ingestion_events (
          provider, idempotency_key, event_type, payload, status
        ) VALUES ('evolution', $1, $2, $3::jsonb, 'processing')
        ON CONFLICT (provider, idempotency_key) DO NOTHING
        RETURNING id
      `, [event.idempotencyKey, event.eventName, JSON.stringify(event.rawPayload)]);

      if (ingestionResult.rowCount === 0) {
        const existing = await client.query<{ id: string; status: string }>(`
          SELECT id, status
          FROM messaging.ingestion_events
          WHERE provider = 'evolution' AND idempotency_key = $1
        `, [event.idempotencyKey]);
        await client.query('COMMIT');

        return {
          ingestionEventId: existing.rows[0]!.id,
          duplicate: true,
          processed: existing.rows[0]!.status === 'processed',
        };
      }

      const ingestionEventId = ingestionResult.rows[0]!.id;
      const processable = event.messageId !== null
        && event.sourceRef !== null
        && event.conversationRef !== null
        && event.contactRef !== null
        && event.remoteJid !== null
        && event.outboxEventType !== null;

      if (!processable) {
        await client.query(`
          UPDATE messaging.ingestion_events
          SET status = 'processed', processed_at = now()
          WHERE id = $1
        `, [ingestionEventId]);
        await client.query('COMMIT');
        return { ingestionEventId, duplicate: false, processed: false };
      }

      const messageId = await persistMessage(client, event);

      await client.query(`
        INSERT INTO messaging.outbox_events (
          deduplication_key, aggregate_type, aggregate_id, event_type, payload
        ) VALUES ($1, 'message', $2, $3, $4::jsonb)
        ON CONFLICT (deduplication_key) DO NOTHING
      `, [
        `evolution:${event.idempotencyKey}`,
        messageId,
        event.outboxEventType,
        JSON.stringify({
          instance: event.instance,
          messageId: event.messageId,
          remoteJid: event.remoteJid,
          direction: event.direction,
          status: event.status,
          occurredAt: event.occurredAt,
        }),
      ]);

      await client.query(`
        UPDATE messaging.ingestion_events
        SET status = 'processed', processed_at = now()
        WHERE id = $1
      `, [ingestionEventId]);
      await client.query('COMMIT');

      return { ingestionEventId, duplicate: false, processed: true };
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }
}
