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
  };
}
