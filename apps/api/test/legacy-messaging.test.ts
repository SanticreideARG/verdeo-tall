import { describe, expect, it } from 'vitest';
import {
  mapLegacyStatus,
  normalizeLegacyMessage,
  normalizeLegacyTimestamp,
  parseLegacyMessages,
} from '../src/migration/legacy-messaging.js';

describe('legacy messaging migration', () => {
  it('parses JSON arrays without accepting malformed payloads', () => {
    expect(parseLegacyMessages('[{"from":"cliente","texto":"Hola"}]')).toHaveLength(1);
    expect(parseLegacyMessages('{"from":"cliente"}')).toEqual([]);
    expect(parseLegacyMessages('not-json')).toEqual([]);
  });

  it('interprets timezone-less legacy timestamps using the configured offset', () => {
    expect(normalizeLegacyTimestamp(
      '2026-05-18 12:05:09',
      '-03:00',
      '1970-01-01T00:00:00.000Z',
    )).toBe('2026-05-18T15:05:09.000Z');
  });

  it('creates stable source references and directions', () => {
    expect(normalizeLegacyMessage(
      42,
      3,
      { from: 'cliente', texto: 'Necesito ayuda', at: '2026-05-18 12:05:09' },
      '-03:00',
      '1970-01-01T00:00:00.000Z',
    )).toMatchObject({
      sourceRef: 'conversation:42:json:3',
      direction: 'inbound',
      body: 'Necesito ayuda',
    });
  });

  it('maps legacy workflow states to the target vocabulary', () => {
    expect(mapLegacyStatus('abierta')).toBe('open');
    expect(mapLegacyStatus('esperando')).toBe('waiting');
    expect(mapLegacyStatus('cerrada')).toBe('closed');
  });
});
