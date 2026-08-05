import { describe, expect, it } from 'vitest';
import { buildApp } from '../src/app.js';
import type { AppConfig } from '../src/config.js';
import type {
  ConversationRepository,
  ListConversationsQuery,
} from '../src/modules/conversations/conversation.js';
import {
  normalizeEvolutionPayload,
  parseEvolutionPayload,
  sanitizeEvolutionPayload,
} from '../src/modules/evolution/evolution-payload.js';
import type {
  EvolutionIngestionResult,
  EvolutionWebhookHandler,
} from '../src/modules/evolution/evolution-webhook-service.js';

const config: AppConfig = {
  env: 'test',
  host: '127.0.0.1',
  port: 3000,
  logLevel: 'silent',
  internalApiToken: 'internal-test-token-at-least-sixteen',
  evolutionWebhookSecret: 'evolution-test-secret-at-least-sixteen',
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
  conversationReadSource: 'mysql',
  legacyTimezoneOffset: '-03:00',
};

const payload = {
  event: 'messages.upsert',
  instance: 'verdeo-principal',
  data: {
    key: {
      remoteJid: '123456789@lid',
      remoteJidAlt: '5491112345678@s.whatsapp.net',
      fromMe: false,
      id: 'MESSAGE-123',
    },
    pushName: 'Cliente de prueba',
    message: { extendedTextMessage: { text: 'Hola Verdeo' } },
    messageType: 'extendedTextMessage',
    messageTimestamp: 1_786_000_000,
  },
  date_time: '2026-08-04T12:00:00.000Z',
  apikey: config.evolutionWebhookSecret,
};

class EmptyConversationRepository implements ConversationRepository {
  public async ping(): Promise<void> {}
  public async list(_query: ListConversationsQuery) { return []; }
}

class FakeEvolutionHandler implements EvolutionWebhookHandler {
  public calls = 0;

  public constructor(private readonly result: EvolutionIngestionResult) {}

  public async ingest(): Promise<EvolutionIngestionResult> {
    this.calls += 1;
    return this.result;
  }
}

describe('Evolution webhook', () => {
  it('normalizes contact identity, body and inbound direction', () => {
    const normalized = normalizeEvolutionPayload(parseEvolutionPayload(payload));

    expect(normalized).toMatchObject({
      messageId: 'MESSAGE-123',
      remoteJid: '5491112345678@s.whatsapp.net',
      phone: '5491112345678',
      direction: 'inbound',
      type: 'text',
      body: 'Hola Verdeo',
      status: 'received',
      outboxEventType: 'message.received',
    });
  });

  it('removes the Evolution API key before persistence', () => {
    const sanitized = sanitizeEvolutionPayload(parseEvolutionPayload(payload));

    expect(sanitized).not.toHaveProperty('apikey');
  });

  it('rejects requests with an invalid webhook secret', async () => {
    const handler = new FakeEvolutionHandler({
      ingestionEventId: '1',
      duplicate: false,
      processed: true,
    });
    const app = buildApp({
      config,
      conversationRepository: new EmptyConversationRepository(),
      evolutionWebhookHandler: handler,
    });

    const response = await app.inject({
      method: 'POST',
      url: '/v1/webhooks/evolution',
      payload: { ...payload, apikey: 'invalid-secret-value' },
    });

    expect(response.statusCode).toBe(401);
    expect(handler.calls).toBe(0);
    await app.close();
  });

  it('accepts a new event without the internal API bearer token', async () => {
    const handler = new FakeEvolutionHandler({
      ingestionEventId: '41',
      duplicate: false,
      processed: true,
    });
    const app = buildApp({
      config,
      conversationRepository: new EmptyConversationRepository(),
      evolutionWebhookHandler: handler,
    });

    const response = await app.inject({
      method: 'POST',
      url: '/v1/webhooks/evolution',
      payload,
    });

    expect(response.statusCode).toBe(202);
    expect(response.json()).toMatchObject({ accepted: true, duplicate: false, processed: true });
    expect(handler.calls).toBe(1);
    await app.close();
  });

  it('acknowledges replayed events with 200', async () => {
    const handler = new FakeEvolutionHandler({
      ingestionEventId: '41',
      duplicate: true,
      processed: true,
    });
    const app = buildApp({
      config,
      conversationRepository: new EmptyConversationRepository(),
      evolutionWebhookHandler: handler,
    });

    const response = await app.inject({
      method: 'POST',
      url: '/v1/webhooks/evolution',
      payload,
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toMatchObject({ accepted: true, duplicate: true });
    await app.close();
  });
});
