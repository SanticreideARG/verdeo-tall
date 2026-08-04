import { z } from 'zod';
import type { ConversationCursor } from './conversation.js';

const cursorSchema = z.object({
  timestamp: z.iso.datetime(),
  id: z.number().int().positive(),
});

export function encodeCursor(cursor: ConversationCursor): string {
  return Buffer.from(JSON.stringify(cursor)).toString('base64url');
}

export function decodeCursor(value: string): ConversationCursor {
  try {
    const decoded: unknown = JSON.parse(Buffer.from(value, 'base64url').toString('utf8'));
    return cursorSchema.parse(decoded);
  } catch {
    throw new Error('INVALID_CURSOR');
  }
}
