import { timingSafeEqual } from 'node:crypto';
import type { FastifyInstance } from 'fastify';
import type { AppConfig } from '../../config.js';
import { parseEvolutionPayload } from './evolution-payload.js';
import type { EvolutionWebhookHandler } from './evolution-webhook-service.js';

function secretsMatch(received: string | undefined, expected: string): boolean {
  if (!received) {
    return false;
  }

  const receivedBuffer = Buffer.from(received);
  const expectedBuffer = Buffer.from(expected);
  return receivedBuffer.length === expectedBuffer.length
    && timingSafeEqual(receivedBuffer, expectedBuffer);
}

export function registerEvolutionRoutes(
  app: FastifyInstance,
  config: AppConfig,
  handler: EvolutionWebhookHandler,
): void {
  app.post('/v1/webhooks/evolution', {
    bodyLimit: 512 * 1_024,
  }, async (request, reply) => {
    let payload;

    try {
      payload = parseEvolutionPayload(request.body);
    } catch {
      return reply.status(422).send({ error: 'INVALID_EVOLUTION_PAYLOAD' });
    }

    const headerSecret = request.headers['x-verdeo-webhook-secret'];
    const receivedSecret = typeof headerSecret === 'string' ? headerSecret : payload.apikey;
    if (!secretsMatch(receivedSecret, config.evolutionWebhookSecret)) {
      return reply.status(401).send({ error: 'INVALID_WEBHOOK_SECRET' });
    }

    try {
      const result = await handler.ingest(payload);
      return reply.status(result.duplicate ? 200 : 202).send({
        accepted: true,
        duplicate: result.duplicate,
        processed: result.processed,
        ingestionEventId: result.ingestionEventId,
      });
    } catch (error) {
      app.log.error({ err: error }, 'Evolution webhook ingestion failed');
      return reply.status(503).send({ error: 'INGESTION_UNAVAILABLE' });
    }
  });
}
