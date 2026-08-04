import pg from 'pg';
import type { AppConfig } from '../config.js';

const { Pool } = pg;

export function createPostgresPool(config: AppConfig['postgres']): pg.Pool {
  return new Pool({
    ...config,
    max: 10,
    idleTimeoutMillis: 30_000,
    connectionTimeoutMillis: 5_000,
  });
}
