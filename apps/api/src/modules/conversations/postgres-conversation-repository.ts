import type { Pool } from 'pg';
import {
  conversationChannels,
  type ConversationChannel,
  type ConversationRepository,
  type ConversationStatus,
  type ConversationSummary,
  type ListConversationsQuery,
} from './conversation.js';

type QueryablePool = Pick<Pool, 'query'>;

type PostgresConversationRow = {
  id: string;
  zone: string | null;
  channel: string;
  channelId: string | null;
  phone: string | null;
  name: string | null;
  status: 'open' | 'waiting' | 'closed';
  lastMessage: string | null;
  lastMessageAt: Date | string;
};

const statusToPostgres: Record<ConversationStatus, PostgresConversationRow['status']> = {
  abierta: 'open',
  esperando: 'waiting',
  cerrada: 'closed',
};

const statusFromPostgres: Record<PostgresConversationRow['status'], ConversationStatus> = {
  open: 'abierta',
  waiting: 'esperando',
  closed: 'cerrada',
};

function isConversationChannel(value: string): value is ConversationChannel {
  return conversationChannels.some((channel) => channel === value);
}

function parseId(value: string): number {
  const id = Number(value);

  if (!Number.isSafeInteger(id) || id < 1) {
    throw new Error(`PostgreSQL conversation id is outside the safe integer range: ${value}`);
  }

  return id;
}

function timestampToIso(value: Date | string): string {
  const timestamp = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(timestamp.getTime())) {
    throw new Error('PostgreSQL returned an invalid conversation timestamp');
  }

  return timestamp.toISOString();
}

export class PostgresConversationRepository implements ConversationRepository {
  public constructor(private readonly pool: QueryablePool) {}

  public async ping(): Promise<void> {
    await this.pool.query('SELECT 1');
  }

  public async list(query: ListConversationsQuery): Promise<ConversationSummary[]> {
    const conditions: string[] = [];
    const parameters: Array<string | number> = [];

    const bind = (value: string | number): string => {
      parameters.push(value);
      return `$${parameters.length}`;
    };

    if (query.channel) {
      conditions.push(`channel.code = ${bind(query.channel)}`);
    }

    if (query.status) {
      conditions.push(`conversation.status = ${bind(statusToPostgres[query.status])}`);
    }

    if (query.cursor) {
      const timestampParameter = bind(query.cursor.timestamp);
      const idParameter = bind(query.cursor.id);
      conditions.push(`(
        COALESCE(conversation.last_message_at, conversation.created_at) < ${timestampParameter}::timestamptz
        OR (
          COALESCE(conversation.last_message_at, conversation.created_at) = ${timestampParameter}::timestamptz
          AND conversation.id < ${idParameter}
        )
      )`);
    }

    const limit = Math.max(1, Math.min(Math.trunc(query.limit), 101));
    const limitParameter = bind(limit);
    const where = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    const result = await this.pool.query<PostgresConversationRow>(`
      SELECT
        conversation.id::text AS id,
        conversation.zone,
        channel.code AS channel,
        conversation.external_ref AS "channelId",
        contact.phone,
        contact.display_name AS name,
        conversation.status,
        CASE
          WHEN conversation.source_system = 'laravel_mysql'
            THEN COALESCE(conversation.metadata->>'legacyLastMessage', latest_message.body)
          ELSE latest_message.body
        END AS "lastMessage",
        COALESCE(conversation.last_message_at, conversation.created_at) AS "lastMessageAt"
      FROM messaging.conversations AS conversation
      JOIN messaging.channels AS channel ON channel.id = conversation.channel_id
      LEFT JOIN LATERAL (
        SELECT participant.phone, participant.display_name
        FROM messaging.conversation_participants AS membership
        JOIN messaging.participants AS participant ON participant.id = membership.participant_id
        WHERE membership.conversation_id = conversation.id
          AND membership.role = 'contact'
          AND membership.left_at IS NULL
        ORDER BY membership.joined_at, participant.id
        LIMIT 1
      ) AS contact ON true
      LEFT JOIN LATERAL (
        SELECT message.body
        FROM messaging.messages AS message
        WHERE message.conversation_id = conversation.id
          AND message.deleted_at IS NULL
        ORDER BY message.occurred_at DESC, message.id DESC
        LIMIT 1
      ) AS latest_message ON true
      ${where}
      ORDER BY
        COALESCE(conversation.last_message_at, conversation.created_at) DESC,
        conversation.id DESC
      LIMIT ${limitParameter}
    `, parameters);

    return result.rows.map((row) => {
      if (!isConversationChannel(row.channel)) {
        throw new Error(`Unsupported PostgreSQL conversation channel: ${row.channel}`);
      }

      return {
        id: parseId(row.id),
        zone: row.zone ?? '',
        channel: row.channel,
        channelId: row.channelId,
        phone: row.phone,
        name: row.name,
        status: statusFromPostgres[row.status],
        lastMessage: row.lastMessage,
        lastMessageAt: timestampToIso(row.lastMessageAt),
      };
    });
  }
}
