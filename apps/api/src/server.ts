import { buildApp } from './app.js';
import { loadConfig } from './config.js';
import { createMySqlPool } from './infrastructure/mysql.js';
import { MySqlConversationRepository } from './modules/conversations/mysql-conversation-repository.js';

const config = loadConfig();
const pool = createMySqlPool(config.mysql);
const conversationRepository = new MySqlConversationRepository(pool);
const app = buildApp({ config, conversationRepository, pool });

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
