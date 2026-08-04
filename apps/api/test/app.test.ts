import { describe, expect, it } from 'vitest';
import { buildApp } from '../src/app.js';
import type { AppConfig } from '../src/config.js';
import type {
  ConversationRepository,
  ConversationSummary,
  ListConversationsQuery,
} from '../src/modules/conversations/conversation.js';

const config: AppConfig = {
  env: 'test',
  host: '127.0.0.1',
  port: 3000,
  logLevel: 'silent',
  internalApiToken: 'test-token-at-least-sixteen-characters',
  mysql: {
    host: 'mysql',
    port: 3306,
    database: 'verdeo_db',
    user: 'verdeo',
    password: 'secret',
  },
  postgres: {
    host: 'postgres',
    port: 5432,
    database: 'verdeo_messaging',
    user: 'verdeo',
    password: 'secret',
  },
  legacyTimezoneOffset: '-03:00',
};

const conversation: ConversationSummary = {
  id: 142,
  zone: 'bsas',
  channel: 'whatsapp',
  channelId: null,
  phone: '5491100000000',
  name: 'Cliente de prueba',
  status: 'abierta',
  lastMessage: 'Hola',
  lastMessageAt: '2026-08-04T12:00:00.000Z',
};

class FakeConversationRepository implements ConversationRepository {
  public lastQuery: ListConversationsQuery | undefined;

  public async ping(): Promise<void> {}

  public async list(query: ListConversationsQuery): Promise<ConversationSummary[]> {
    this.lastQuery = query;
    return [conversation];
  }
}

describe('Verdeo API', () => {
  it('exposes liveness without authentication', async () => {
    const app = buildApp({
      config,
      conversationRepository: new FakeConversationRepository(),
    });

    const response = await app.inject({ method: 'GET', url: '/v1/health' });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toMatchObject({ status: 'ok', service: 'verdeo-api' });
    await app.close();
  });

  it('protects conversation data', async () => {
    const app = buildApp({
      config,
      conversationRepository: new FakeConversationRepository(),
    });

    const response = await app.inject({ method: 'GET', url: '/v1/conversations' });

    expect(response.statusCode).toBe(401);
    await app.close();
  });

  it('lists conversations with bounded cursor pagination', async () => {
    const repository = new FakeConversationRepository();
    const app = buildApp({ config, conversationRepository: repository });

    const response = await app.inject({
      method: 'GET',
      url: '/v1/conversations?limit=25&channel=whatsapp',
      headers: { authorization: `Bearer ${config.internalApiToken}` },
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({
      data: [conversation],
      pagination: { nextCursor: null, limit: 25 },
    });
    expect(repository.lastQuery).toEqual({ limit: 26, channel: 'whatsapp' });
    await app.close();
  });
});
