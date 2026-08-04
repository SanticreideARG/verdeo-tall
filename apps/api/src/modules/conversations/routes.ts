import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import {
  conversationChannels,
  conversationStatuses,
  type ConversationRepository,
  type ListConversationsQuery,
} from './conversation.js';
import { decodeCursor, encodeCursor } from './cursor.js';

const listQuerySchema = z.object({
  limit: z.coerce.number().int().min(1).max(100).default(25),
  channel: z.enum(conversationChannels).optional(),
  status: z.enum(conversationStatuses).optional(),
  cursor: z.string().min(1).optional(),
});

export function registerConversationRoutes(
  app: FastifyInstance,
  repository: ConversationRepository,
): void {
  app.get('/v1/conversations', async (request, reply) => {
    const parsed = listQuerySchema.safeParse(request.query);

    if (!parsed.success) {
      return reply.status(400).send({
        error: 'INVALID_QUERY',
        details: z.treeifyError(parsed.error),
      });
    }

    let query: ListConversationsQuery;

    try {
      query = {
        limit: parsed.data.limit + 1,
        ...(parsed.data.channel ? { channel: parsed.data.channel } : {}),
        ...(parsed.data.status ? { status: parsed.data.status } : {}),
        ...(parsed.data.cursor ? { cursor: decodeCursor(parsed.data.cursor) } : {}),
      };
    } catch {
      return reply.status(400).send({ error: 'INVALID_CURSOR' });
    }

    const conversations = await repository.list(query);
    const hasNextPage = conversations.length > parsed.data.limit;
    const data = conversations.slice(0, parsed.data.limit);
    const lastConversation = data.at(-1);
    const nextCursor = hasNextPage && lastConversation
      ? encodeCursor({ timestamp: lastConversation.lastMessageAt, id: lastConversation.id })
      : null;

    return reply.send({
      data,
      pagination: { nextCursor, limit: parsed.data.limit },
    });
  });
}
