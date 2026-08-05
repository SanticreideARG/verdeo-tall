import { describe, expect, it } from 'vitest';
import { loadConfig } from '../src/config.js';

const requiredEnvironment = {
  INTERNAL_API_TOKEN: 'internal-test-token-at-least-sixteen',
  EVOLUTION_WEBHOOK_SECRET: 'evolution-test-secret-at-least-sixteen',
  MYSQL_HOST: 'mysql',
  MYSQL_DATABASE: 'verdeo_db',
  MYSQL_USER: 'verdeo',
  MYSQL_PASSWORD: 'secret',
  POSTGRES_HOST: 'postgres',
  POSTGRES_DATABASE: 'verdeo_messaging',
  POSTGRES_USER: 'verdeo',
  POSTGRES_PASSWORD: 'secret',
};

describe('configuration', () => {
  it('keeps MySQL as the safe default conversation read source', () => {
    expect(loadConfig(requiredEnvironment).conversationReadSource).toBe('mysql');
  });

  it('allows PostgreSQL conversation reads explicitly', () => {
    expect(loadConfig({
      ...requiredEnvironment,
      CONVERSATION_READ_SOURCE: 'postgres',
    }).conversationReadSource).toBe('postgres');
  });

  it('rejects unknown conversation read sources', () => {
    expect(() => loadConfig({
      ...requiredEnvironment,
      CONVERSATION_READ_SOURCE: 'mongodb',
    })).toThrow();
  });
});
