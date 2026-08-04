import { z } from 'zod';

const environmentSchema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  PORT: z.coerce.number().int().min(1).max(65_535).default(3000),
  HOST: z.string().min(1).default('0.0.0.0'),
  LOG_LEVEL: z.enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace', 'silent']).default('info'),
  INTERNAL_API_TOKEN: z.string().min(16),
  MYSQL_HOST: z.string().min(1),
  MYSQL_PORT: z.coerce.number().int().min(1).max(65_535).default(3306),
  MYSQL_DATABASE: z.string().min(1),
  MYSQL_USER: z.string().min(1),
  MYSQL_PASSWORD: z.string(),
  POSTGRES_HOST: z.string().min(1),
  POSTGRES_PORT: z.coerce.number().int().min(1).max(65_535).default(5432),
  POSTGRES_DATABASE: z.string().min(1),
  POSTGRES_USER: z.string().min(1),
  POSTGRES_PASSWORD: z.string().min(1),
  LEGACY_TIMEZONE_OFFSET: z.string().regex(/^[+-]\d{2}:\d{2}$/).default('-03:00'),
});

export type AppConfig = {
  env: z.infer<typeof environmentSchema>['NODE_ENV'];
  port: number;
  host: string;
  logLevel: z.infer<typeof environmentSchema>['LOG_LEVEL'];
  internalApiToken: string;
  mysql: {
    host: string;
    port: number;
    database: string;
    user: string;
    password: string;
  };
  postgres: {
    host: string;
    port: number;
    database: string;
    user: string;
    password: string;
  };
  legacyTimezoneOffset: string;
};

export function loadConfig(environment: NodeJS.ProcessEnv = process.env): AppConfig {
  const parsed = environmentSchema.parse(environment);

  return {
    env: parsed.NODE_ENV,
    port: parsed.PORT,
    host: parsed.HOST,
    logLevel: parsed.LOG_LEVEL,
    internalApiToken: parsed.INTERNAL_API_TOKEN,
    mysql: {
      host: parsed.MYSQL_HOST,
      port: parsed.MYSQL_PORT,
      database: parsed.MYSQL_DATABASE,
      user: parsed.MYSQL_USER,
      password: parsed.MYSQL_PASSWORD,
    },
    postgres: {
      host: parsed.POSTGRES_HOST,
      port: parsed.POSTGRES_PORT,
      database: parsed.POSTGRES_DATABASE,
      user: parsed.POSTGRES_USER,
      password: parsed.POSTGRES_PASSWORD,
    },
    legacyTimezoneOffset: parsed.LEGACY_TIMEZONE_OFFSET,
  };
}
