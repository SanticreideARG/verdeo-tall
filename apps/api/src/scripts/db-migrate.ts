import { createHash } from 'node:crypto';
import { readFile, readdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { loadConfig } from '../config.js';
import { createPostgresPool } from '../infrastructure/postgres.js';

const lockName = 'verdeo:messaging:schema-migrations';

async function migrate(): Promise<void> {
  const config = loadConfig();
  const pool = createPostgresPool(config.postgres);
  const client = await pool.connect();

  try {
    await client.query('SELECT pg_advisory_lock(hashtext($1))', [lockName]);
    await client.query(`
      CREATE TABLE IF NOT EXISTS public.schema_migrations (
        name text PRIMARY KEY,
        checksum text NOT NULL,
        applied_at timestamptz NOT NULL DEFAULT now()
      )
    `);

    const migrationsDirectory = resolve(process.env.MIGRATIONS_DIR ?? 'migrations');
    const migrationNames = (await readdir(migrationsDirectory))
      .filter((name) => /^\d+_[a-z0-9_]+\.sql$/.test(name))
      .sort();

    for (const name of migrationNames) {
      const sql = await readFile(resolve(migrationsDirectory, name), 'utf8');
      const checksum = createHash('sha256').update(sql).digest('hex');
      const existing = await client.query<{ checksum: string }>(
        'SELECT checksum FROM public.schema_migrations WHERE name = $1',
        [name],
      );

      if (existing.rowCount === 1) {
        if (existing.rows[0]?.checksum !== checksum) {
          throw new Error(`Migration ${name} changed after it was applied`);
        }

        continue;
      }

      await client.query('BEGIN');
      try {
        await client.query(sql);
        await client.query(
          'INSERT INTO public.schema_migrations (name, checksum) VALUES ($1, $2)',
          [name, checksum],
        );
        await client.query('COMMIT');
        console.info(`Applied ${name}`);
      } catch (error) {
        await client.query('ROLLBACK');
        throw error;
      }
    }
  } finally {
    await client.query('SELECT pg_advisory_unlock(hashtext($1))', [lockName]);
    client.release();
    await pool.end();
  }
}

migrate().catch((error: unknown) => {
  console.error(error);
  process.exitCode = 1;
});
