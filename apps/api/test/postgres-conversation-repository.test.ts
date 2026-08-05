import type { Pool } from 'pg';
import { describe, expect, it, vi } from 'vitest';
import { PostgresConversationRepository } from '../src/modules/conversations/postgres-conversation-repository.js';

describe('PostgresConversationRepository', () => {
  it('maps normalized PostgreSQL rows to the existing HTTP contract', async () => {
    const query = vi.fn().mockResolvedValue({
      rows: [{
        id: '77',
        zone: null,
        channel: 'email',
        channelId: 'thread-123',
        phone: null,
        name: 'Compras Verdeo',
        status: 'waiting',
        lastMessage: 'Necesito una cotización',
        lastMessageAt: new Date('2026-08-04T15:30:00.000Z'),
      }],
    });
    const repository = new PostgresConversationRepository({
      query,
    } as unknown as Pick<Pool, 'query'>);

    await expect(repository.list({ limit: 26 })).resolves.toEqual([{
      id: 77,
      zone: '',
      channel: 'email',
      channelId: 'thread-123',
      phone: null,
      name: 'Compras Verdeo',
      status: 'esperando',
      lastMessage: 'Necesito una cotización',
      lastMessageAt: '2026-08-04T15:30:00.000Z',
    }]);
    expect(query).toHaveBeenCalledWith(expect.any(String), [26]);
  });

  it('uses bound parameters for filters, cursor and bounded pagination', async () => {
    const query = vi.fn().mockResolvedValue({ rows: [] });
    const repository = new PostgresConversationRepository({
      query,
    } as unknown as Pick<Pool, 'query'>);

    await repository.list({
      limit: 500,
      channel: 'whatsapp',
      status: 'esperando',
      cursor: { timestamp: '2026-08-04T12:00:00.000Z', id: 142 },
    });

    const [statement, parameters] = query.mock.calls[0] as [string, unknown[]];
    expect(statement).toContain('channel.code = $1');
    expect(statement).toContain('conversation.status = $2');
    expect(statement).toContain('conversation.id < $4');
    expect(statement).toContain('LIMIT $5');
    expect(parameters).toEqual([
      'whatsapp',
      'waiting',
      '2026-08-04T12:00:00.000Z',
      142,
      101,
    ]);
  });

  it('rejects identifiers that cannot be represented safely by the public contract', async () => {
    const query = vi.fn().mockResolvedValue({
      rows: [{
        id: '9007199254740993',
        zone: 'bsas',
        channel: 'whatsapp',
        channelId: null,
        phone: null,
        name: null,
        status: 'open',
        lastMessage: null,
        lastMessageAt: '2026-08-04T15:30:00.000Z',
      }],
    });
    const repository = new PostgresConversationRepository({
      query,
    } as unknown as Pick<Pool, 'query'>);

    await expect(repository.list({ limit: 1 })).rejects.toThrow('safe integer range');
  });
});
