CREATE INDEX IF NOT EXISTS conversations_read_cursor_idx
    ON messaging.conversations (
        (COALESCE(last_message_at, created_at)) DESC,
        id DESC
    );
