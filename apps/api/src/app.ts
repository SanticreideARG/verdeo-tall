import { timingSafeEqual } from 'node:crypto';
import Fastify, { type FastifyInstance } from 'fastify';
import type { Pool } from 'mysql2/promise';
import type { AppConfig } from './config.js';
import type { ConversationRepository } from './modules/conversations/conversation.js';
import { registerConversationRoutes } from './modules/conversations/routes.js';

type BuildAppOptions = {
  config: AppConfig;
  conversationRepository: ConversationRepository;
  pool?: Pool;
};

function tokensMatch(received: string | undefined, expected: string): boolean {
  if (!received?.startsWith('Bearer ')) {
    return false;
  }

  const receivedToken = Buffer.from(received.slice(7));
  const expectedToken = Buffer.from(expected);

  return receivedToken.length === expectedToken.length
    && timingSafeEqual(receivedToken, expectedToken);
}

export function buildApp(options: BuildAppOptions): FastifyInstance {
  const app = Fastify({
    logger: options.config.env === 'test'
      ? false
      : { level: options.config.logLevel },
  });

  app.addHook('onRequest', async (request, reply) => {
    if (request.url === '/v1/health' || request.url === '/v1/ready') {
      return;
    }

    if (!tokensMatch(request.headers.authorization, options.config.internalApiToken)) {
      return reply.status(401).send({ error: 'UNAUTHORIZED' });
    }
  });

  app.get('/v1/health', async () => ({
    status: 'ok',
    service: 'verdeo-api',
    version: '0.1.0',
  }));

  app.get('/v1/ready', async (_request, reply) => {
    try {
      await options.conversationRepository.ping();
      return reply.send({ status: 'ready' });
    } catch (error) {
      app.log.error(error);
      return reply.status(503).send({ status: 'unavailable' });
    }
  });

  registerConversationRoutes(app, options.conversationRepository);

  if (options.pool) {
    app.addHook('onClose', async () => {
      await options.pool?.end();
    });
  }

  return app;
}
