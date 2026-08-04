import { createHash } from 'node:crypto';
import { z } from 'zod';

const payloadSchema = z.object({
  event: z.string().min(1),
  instance: z.string().min(1),
  data: z.unknown(),
  destination: z.string().optional(),
  date_time: z.string().optional(),
  sender: z.string().optional(),
  server_url: z.string().optional(),
  apikey: z.string().optional(),
}).passthrough();

export type EvolutionWebhookPayload = z.infer<typeof payloadSchema>;

export type NormalizedEvolutionMessage = {
  idempotencyKey: string;
  eventName: string;
  instance: string;
  messageId: string | null;
  sourceRef: string | null;
  conversationRef: string | null;
  contactRef: string | null;
  remoteJid: string | null;
  phone: string | null;
  displayName: string | null;
  fromMe: boolean;
  direction: 'inbound' | 'outbound';
  type: 'text' | 'image' | 'location' | 'file' | 'audio' | 'video' | 'other';
  body: string | null;
  occurredAt: string;
  status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed' | 'received' | 'deleted';
  deletedAt: string | null;
  outboxEventType: 'message.received' | 'message.sent' | 'message.status_changed' | 'message.deleted' | null;
  rawPayload: Record<string, unknown>;
};

type UnknownRecord = Record<string, unknown>;

function asRecord(value: unknown): UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
    ? value as UnknownRecord
    : {};
}

function canonicalize(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(canonicalize);
  }

  if (typeof value === 'object' && value !== null) {
    return Object.fromEntries(
      Object.entries(value as UnknownRecord)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, item]) => [key, canonicalize(item)]),
    );
  }

  return value;
}

function timestamp(value: unknown, fallback: string | undefined): string {
  if (typeof value === 'number' && Number.isFinite(value)) {
    const milliseconds = value > 10_000_000_000 ? value : value * 1_000;
    return new Date(milliseconds).toISOString();
  }

  if (typeof value === 'string' && /^\d+$/.test(value)) {
    return timestamp(Number(value), fallback);
  }

  if (typeof value === 'string') {
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed.toISOString();
    }
  }

  if (fallback) {
    const parsedFallback = new Date(fallback);
    if (!Number.isNaN(parsedFallback.getTime())) {
      return parsedFallback.toISOString();
    }
  }

  return new Date(0).toISOString();
}

function messageBody(message: UnknownRecord): string | null {
  if (typeof message.conversation === 'string') {
    return message.conversation;
  }

  const candidates = [
    asRecord(message.extendedTextMessage).text,
    asRecord(message.imageMessage).caption,
    asRecord(message.videoMessage).caption,
    asRecord(message.documentMessage).caption,
    asRecord(message.documentMessage).fileName,
    asRecord(message.locationMessage).name,
    asRecord(message.locationMessage).address,
  ];

  return candidates.find((value): value is string => typeof value === 'string') ?? null;
}

function messageType(value: unknown): NormalizedEvolutionMessage['type'] {
  const type = typeof value === 'string' ? value.toLowerCase() : '';

  if (type.includes('image')) return 'image';
  if (type.includes('audio')) return 'audio';
  if (type.includes('video')) return 'video';
  if (type.includes('document')) return 'file';
  if (type.includes('location')) return 'location';
  if (type.includes('conversation') || type.includes('text')) return 'text';
  return 'other';
}

function messageStatus(
  eventName: string,
  value: unknown,
  fromMe: boolean,
): NormalizedEvolutionMessage['status'] {
  if (eventName.includes('delete')) return 'deleted';

  if (typeof value === 'number') {
    if (value >= 4) return 'read';
    if (value === 3) return 'delivered';
    if (value === 2) return 'sent';
    if (value === 1) return 'pending';
    return 'failed';
  }

  const status = typeof value === 'string' ? value.toUpperCase() : '';
  if (status.includes('READ') || status.includes('PLAYED')) return 'read';
  if (status.includes('DELIVERY')) return 'delivered';
  if (status.includes('SERVER') || status === 'SENT') return 'sent';
  if (status.includes('PENDING')) return 'pending';
  if (status.includes('ERROR') || status.includes('FAIL')) return 'failed';
  return fromMe ? 'sent' : 'received';
}

function outboxEventType(
  eventName: string,
  fromMe: boolean,
): NormalizedEvolutionMessage['outboxEventType'] {
  if (eventName.includes('delete')) return 'message.deleted';
  if (eventName.includes('update')) return 'message.status_changed';
  if (eventName.includes('upsert')) return fromMe ? 'message.sent' : 'message.received';
  return null;
}

export function parseEvolutionPayload(value: unknown): EvolutionWebhookPayload {
  return payloadSchema.parse(value);
}

export function sanitizeEvolutionPayload(payload: EvolutionWebhookPayload): Record<string, unknown> {
  const { apikey: _apikey, ...sanitized } = payload;
  return sanitized;
}

export function normalizeEvolutionPayload(
  payload: EvolutionWebhookPayload,
): NormalizedEvolutionMessage {
  const eventName = payload.event.toLowerCase().replaceAll('_', '.');
  const data = asRecord(payload.data);
  const key = asRecord(data.key);
  const update = asRecord(data.update);
  const message = asRecord(data.message);
  const messageId = typeof key.id === 'string' && key.id !== '' ? key.id : null;
  const primaryJid = typeof key.remoteJid === 'string' ? key.remoteJid : null;
  const alternateJidValue = key.remoteJidAlt ?? key.senderPn ?? data.senderPn;
  const alternateJid = typeof alternateJidValue === 'string'
    ? alternateJidValue.includes('@') ? alternateJidValue : `${alternateJidValue}@s.whatsapp.net`
    : null;
  const remoteJid = primaryJid?.endsWith('@lid') && alternateJid?.endsWith('@s.whatsapp.net')
    ? alternateJid
    : primaryJid;
  const fromMe = key.fromMe === true;
  const rawPayload = sanitizeEvolutionPayload(payload);
  const status = messageStatus(eventName, data.status ?? update.status, fromMe);
  const eventFingerprint = messageId
    ? [eventName, payload.instance, messageId, eventName.includes('update') ? status : ''].join(':')
    : JSON.stringify(canonicalize(rawPayload));
  const idempotencyKey = createHash('sha256').update(eventFingerprint).digest('hex');
  const phoneCandidate = remoteJid?.split('@')[0] ?? '';
  const phone = remoteJid?.endsWith('@s.whatsapp.net') && /^\d+$/.test(phoneCandidate)
    ? phoneCandidate
    : null;
  const occurredAt = timestamp(data.messageTimestamp, payload.date_time);

  return {
    idempotencyKey,
    eventName,
    instance: payload.instance,
    messageId,
    sourceRef: messageId ? `instance:${payload.instance}:message:${messageId}` : null,
    conversationRef: remoteJid ? `instance:${payload.instance}:chat:${remoteJid}` : null,
    contactRef: remoteJid ? `instance:${payload.instance}:contact:${remoteJid}` : null,
    remoteJid,
    phone,
    displayName: typeof data.pushName === 'string' ? data.pushName : null,
    fromMe,
    direction: fromMe ? 'outbound' : 'inbound',
    type: messageType(data.messageType),
    body: messageBody(message),
    occurredAt,
    status,
    deletedAt: status === 'deleted' ? occurredAt : null,
    outboxEventType: outboxEventType(eventName, fromMe),
    rawPayload,
  };
}
