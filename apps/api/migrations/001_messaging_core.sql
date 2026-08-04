CREATE SCHEMA IF NOT EXISTS messaging;

CREATE TABLE IF NOT EXISTS messaging.channels (
    id smallint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code text NOT NULL UNIQUE CHECK (code ~ '^[a-z][a-z0-9_-]{1,31}$'),
    display_name text NOT NULL,
    provider text,
    active boolean NOT NULL DEFAULT true,
    config jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

INSERT INTO messaging.channels (code, display_name, provider)
VALUES
    ('whatsapp', 'WhatsApp', 'evolution'),
    ('messenger', 'Messenger', 'meta'),
    ('instagram', 'Instagram', 'meta'),
    ('email', 'Email', NULL),
    ('internal', 'Mensajería interna', 'verdeo')
ON CONFLICT (code) DO UPDATE
SET display_name = EXCLUDED.display_name,
    provider = EXCLUDED.provider,
    updated_at = now();

CREATE TABLE IF NOT EXISTS messaging.participants (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_system text NOT NULL,
    source_ref text NOT NULL,
    kind text NOT NULL CHECK (kind IN ('contact', 'agent', 'system')),
    display_name text,
    phone text,
    email text,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (source_system, source_ref)
);

CREATE INDEX IF NOT EXISTS participants_phone_idx
    ON messaging.participants (phone)
    WHERE phone IS NOT NULL;

CREATE INDEX IF NOT EXISTS participants_email_idx
    ON messaging.participants (lower(email))
    WHERE email IS NOT NULL;

CREATE TABLE IF NOT EXISTS messaging.conversations (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_system text NOT NULL,
    source_ref text NOT NULL,
    channel_id smallint NOT NULL REFERENCES messaging.channels (id),
    external_ref text,
    zone text,
    status text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'waiting', 'closed')),
    subject text,
    last_message_at timestamptz,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (source_system, source_ref)
);

CREATE INDEX IF NOT EXISTS conversations_channel_status_idx
    ON messaging.conversations (channel_id, status);

CREATE INDEX IF NOT EXISTS conversations_last_message_idx
    ON messaging.conversations (last_message_at DESC, id DESC);

CREATE TABLE IF NOT EXISTS messaging.conversation_participants (
    conversation_id bigint NOT NULL REFERENCES messaging.conversations (id) ON DELETE CASCADE,
    participant_id bigint NOT NULL REFERENCES messaging.participants (id) ON DELETE CASCADE,
    role text NOT NULL CHECK (role IN ('contact', 'assignee', 'bot', 'observer')),
    joined_at timestamptz NOT NULL DEFAULT now(),
    left_at timestamptz,
    PRIMARY KEY (conversation_id, participant_id, role)
);

CREATE INDEX IF NOT EXISTS conversation_participants_participant_idx
    ON messaging.conversation_participants (participant_id, conversation_id);

CREATE TABLE IF NOT EXISTS messaging.messages (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_system text NOT NULL,
    source_ref text NOT NULL,
    conversation_id bigint NOT NULL REFERENCES messaging.conversations (id) ON DELETE CASCADE,
    sender_participant_id bigint REFERENCES messaging.participants (id) ON DELETE SET NULL,
    direction text NOT NULL CHECK (direction IN ('inbound', 'outbound', 'internal', 'system')),
    type text NOT NULL DEFAULT 'text' CHECK (type IN ('text', 'image', 'location', 'file', 'audio', 'video', 'event', 'other')),
    body text,
    occurred_at timestamptz NOT NULL,
    status text NOT NULL DEFAULT 'received' CHECK (status IN ('pending', 'sent', 'delivered', 'read', 'failed', 'received')),
    raw_payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (source_system, source_ref)
);

CREATE INDEX IF NOT EXISTS messages_conversation_timeline_idx
    ON messaging.messages (conversation_id, occurred_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS messages_occurred_at_idx
    ON messaging.messages (occurred_at DESC);

CREATE TABLE IF NOT EXISTS messaging.attachments (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    message_id bigint NOT NULL REFERENCES messaging.messages (id) ON DELETE CASCADE,
    source_ref text,
    kind text NOT NULL CHECK (kind IN ('image', 'audio', 'video', 'document', 'other')),
    mime_type text,
    filename text,
    size_bytes bigint CHECK (size_bytes IS NULL OR size_bytes >= 0),
    storage_key text,
    external_url text,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (message_id, source_ref)
);

CREATE TABLE IF NOT EXISTS messaging.ingestion_events (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    provider text NOT NULL,
    idempotency_key text NOT NULL,
    event_type text NOT NULL,
    payload jsonb NOT NULL,
    status text NOT NULL DEFAULT 'received' CHECK (status IN ('received', 'processing', 'processed', 'failed')),
    error_message text,
    received_at timestamptz NOT NULL DEFAULT now(),
    processed_at timestamptz,
    UNIQUE (provider, idempotency_key)
);

CREATE INDEX IF NOT EXISTS ingestion_events_pending_idx
    ON messaging.ingestion_events (received_at)
    WHERE status IN ('received', 'failed');

CREATE TABLE IF NOT EXISTS messaging.migration_runs (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_system text NOT NULL,
    mode text NOT NULL CHECK (mode IN ('apply')),
    status text NOT NULL CHECK (status IN ('running', 'completed', 'failed')),
    stats jsonb NOT NULL DEFAULT '{}'::jsonb,
    error_message text,
    started_at timestamptz NOT NULL DEFAULT now(),
    finished_at timestamptz
);
