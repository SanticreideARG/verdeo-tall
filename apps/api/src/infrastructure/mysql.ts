import mysql, { type Pool } from 'mysql2/promise';
import type { AppConfig } from '../config.js';

export function createMySqlPool(config: AppConfig['mysql']): Pool {
  return mysql.createPool({
    ...config,
    connectionLimit: 10,
    enableKeepAlive: true,
    keepAliveInitialDelay: 0,
    timezone: 'Z',
    dateStrings: true,
  });
}
