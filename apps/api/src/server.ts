import { buildApp } from './app.js';
import { loadConfig } from './config.js';
import { createMySqlPool } from './infrastructure/mysql.js';
import { createPostgresPool } from './infrastructure/postgres.js';
import { MySqlConversationRepository } from './modules/conversations/mysql-conversation-repository.js';
import { PostgresConversationRepository } from './modules/conversations/postgres-conversation-repository.js';
import { PostgresEvolutionWebhookService } from './modules/evolution/evolution-webhook-service.js';

const config = loadConfig();
const pool = createMySqlPool(config.mysql);
const postgresPool = createPostgresPool(config.postgres);
const conversationRepository = config.conversationReadSource === 'postgres'
  ? new PostgresConversationRepository(postgresPool)
  : new MySqlConversationRepository(pool);
const evolutionWebhookHandler = new PostgresEvolutionWebhookService(postgresPool);
const app = buildApp({
  config,
  conversationRepository,
  mysqlPool: pool,
  postgresPool,
  evolutionWebhookHandler,
});

app.log.info(
  { source: config.conversationReadSource },
  'conversation read repository selected',
);

const shutdown = async (signal: string): Promise<void> => {
  app.log.info({ signal }, 'shutdown requested');
  await app.close();
  process.exit(0);
};

process.on('SIGINT', () => void shutdown('SIGINT'));
process.on('SIGTERM', () => void shutdown('SIGTERM'));

try {
  await app.listen({ host: config.host, port: config.port });
} catch (error) {
  app.log.error(error);
  process.exit(1);
}
