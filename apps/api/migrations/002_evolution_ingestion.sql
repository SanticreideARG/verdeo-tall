ALTER TABLE messaging.messages
    DROP CONSTRAINT IF EXISTS messages_status_check;

ALTER TABLE messaging.messages
    ADD CONSTRAINT messages_status_check
    CHECK (status IN ('pending', 'sent', 'delivered', 'read', 'failed', 'received', 'deleted'));

ALTER TABLE messaging.messages
    ADD COLUMN IF NOT EXISTS deleted_at timestamptz;

CREATE TABLE IF NOT EXISTS messaging.outbox_events (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    deduplication_key text NOT NULL UNIQUE,
    aggregate_type text NOT NULL,
    aggregate_id bigint NOT NULL,
    event_type text NOT NULL,
    payload jsonb NOT NULL,
    status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'published', 'failed')),
    attempts integer NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    available_at timestamptz NOT NULL DEFAULT now(),
    last_error text,
    created_at timestamptz NOT NULL DEFAULT now(),
    published_at timestamptz
);

CREATE INDEX IF NOT EXISTS outbox_events_pending_idx
    ON messaging.outbox_events (available_at, id)
    WHERE status IN ('pending', 'failed');

CREATE INDEX IF NOT EXISTS ingestion_events_status_idx
    ON messaging.ingestion_events (status, received_at);
