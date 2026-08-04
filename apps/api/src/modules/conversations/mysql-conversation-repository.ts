import type { Pool, RowDataPacket } from 'mysql2/promise';
import type {
  ConversationRepository,
  ConversationSummary,
  ListConversationsQuery,
} from './conversation.js';

type ConversationRow = RowDataPacket & ConversationSummary;

export class MySqlConversationRepository implements ConversationRepository {
  public constructor(private readonly pool: Pool) {}

  public async ping(): Promise<void> {
    await this.pool.query('SELECT 1');
  }

  public async list(query: ListConversationsQuery): Promise<ConversationSummary[]> {
    const conditions: string[] = [];
    const parameters: Array<string | number> = [];

    if (query.channel) {
      conditions.push('canal = ?');
      parameters.push(query.channel);
    }

    if (query.status) {
      conditions.push('estado = ?');
      parameters.push(query.status);
    }

    if (query.cursor) {
      conditions.push(`(
        COALESCE(ultimo_mensaje_at, created_at, '1970-01-01 00:00:00') < ?
        OR (
          COALESCE(ultimo_mensaje_at, created_at, '1970-01-01 00:00:00') = ?
          AND id < ?
        )
      )`);
      const cursorTimestamp = query.cursor.timestamp.slice(0, 19).replace('T', ' ');
      parameters.push(cursorTimestamp, cursorTimestamp, query.cursor.id);
    }

    const where = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    const limit = Math.max(1, Math.min(Math.trunc(query.limit), 101));

    const [rows] = await this.pool.execute<ConversationRow[]>(
      `SELECT
        id,
        zona AS zone,
        canal AS channel,
        canal_id AS channelId,
        telefono AS phone,
        nombre AS name,
        estado AS status,
        ultimo_mensaje AS lastMessage,
        DATE_FORMAT(
          COALESCE(ultimo_mensaje_at, created_at, '1970-01-01 00:00:00'),
          '%Y-%m-%dT%H:%i:%s.000Z'
        ) AS lastMessageAt
      FROM conversaciones
      ${where}
      ORDER BY
        COALESCE(ultimo_mensaje_at, created_at, '1970-01-01 00:00:00') DESC,
        id DESC
      LIMIT ${limit}`,
      parameters,
    );

    return rows.map((row) => ({
      id: row.id,
      zone: row.zone,
      channel: row.channel,
      channelId: row.channelId,
      phone: row.phone,
      name: row.name,
      status: row.status,
      lastMessage: row.lastMessage,
      lastMessageAt: row.lastMessageAt,
    }));
  }
}
