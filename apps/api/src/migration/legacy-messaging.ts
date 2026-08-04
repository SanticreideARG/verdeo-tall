export type LegacyMessage = {
  at?: unknown;
  from?: unknown;
  texto?: unknown;
  [key: string]: unknown;
};

export type NormalizedLegacyMessage = {
  sourceRef: string;
  direction: 'inbound' | 'outbound' | 'system';
  body: string | null;
  occurredAt: string;
  rawPayload: LegacyMessage;
};

export function parseLegacyMessages(value: unknown): LegacyMessage[] {
  let parsed = value;

  if (typeof parsed === 'string') {
    try {
      parsed = JSON.parse(parsed) as unknown;
    } catch {
      return [];
    }
  }

  if (!Array.isArray(parsed)) {
    return [];
  }

  return parsed.filter((message): message is LegacyMessage => (
    typeof message === 'object' && message !== null && !Array.isArray(message)
  ));
}

export function normalizeLegacyTimestamp(
  value: unknown,
  timezoneOffset: string,
  fallback: string,
): string {
  if (typeof value !== 'string' || value.trim() === '') {
    return fallback;
  }

  const trimmed = value.trim();
  const candidate = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(trimmed)
    ? `${trimmed.replace(' ', 'T')}${timezoneOffset}`
    : trimmed;
  const parsed = new Date(candidate);

  return Number.isNaN(parsed.getTime()) ? fallback : parsed.toISOString();
}

export function normalizeLegacyMessage(
  conversationId: number,
  index: number,
  message: LegacyMessage,
  timezoneOffset: string,
  fallbackTimestamp: string,
): NormalizedLegacyMessage {
  const origin = typeof message.from === 'string' ? message.from.toLowerCase() : '';
  const direction = origin === 'cliente'
    ? 'inbound'
    : origin === 'verdeo'
      ? 'outbound'
      : 'system';

  return {
    sourceRef: `conversation:${conversationId}:json:${index}`,
    direction,
    body: typeof message.texto === 'string' ? message.texto : null,
    occurredAt: normalizeLegacyTimestamp(
      message.at,
      timezoneOffset,
      fallbackTimestamp,
    ),
    rawPayload: message,
  };
}

export function mapLegacyStatus(status: string): 'open' | 'waiting' | 'closed' {
  if (status === 'cerrada') {
    return 'closed';
  }

  if (status === 'esperando') {
    return 'waiting';
  }

  return 'open';
}
