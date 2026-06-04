# Messenger & the chat component

Two things ship together: a reusable **`UI::chat`** component and a
**Messenger** module that wires it into the admin so users can message each
other — 1:1 DMs and named group channels.

---

## Enable it

Messenger is a module like Products. Install it (registers PSR-4 + enables),
then migrate to create the chat tables:

```bash
php sofy module:install Messenger      # if not already enabled
php sofy migrate                       # creates chat_channels / _participants / _messages
```

A **Сообщения** item appears in the admin menu with an unread-count badge.
`/admin/messages` lists your conversations; pick a user to start a DM, or open
a thread.

---

## How live updates work

Sending and persistence are always plain HTTP (CSRF-protected, auth-gated).
Live delivery is layered:

- **Polling (default, zero infra):** the chat polls `/admin/messages/{id}/poll?after={lastId}`
  every few seconds and appends new messages. Works on any nginx/fpm host with
  nothing extra.
- **WebSocket (optional, instant):** set `MESSENGER_WS_URL` and run the chat
  WebSocket handler. The browser then opens a socket and, after each send,
  emits a tiny *bump* to the channel room; everyone in the room refetches
  immediately. The socket carries only signals — message bodies still come over
  HTTP — so **no Redis bridge is needed**, just a running `ws:serve`.

```bash
php sofy ws:serve --handler="Messenger\WebSocket\ChatHandler" --port=8080
```

```dotenv
# proxy ws:// through nginx, then:
MESSENGER_WS_URL=wss://your-host/ws
```

If the socket drops, the chat keeps working on the polling fallback and
reconnects automatically.

---

## The `UI::chat` component (reusable)

Use it anywhere you need a chat thread — it's not tied to the Messenger module:

```php
UI::chat(
    messages: $messages,                       // [{id,user_id,name,body,time,mine}]
    sendUrl:  '/admin/messages/5/send',        // POST {body} -> {message:{…}}
    pollUrl:  '/admin/messages/5/poll',        // GET ?after=id -> {messages:[…]}
    currentUserId: $me,
    wsUrl:    config('messenger.ws_url') ?: null,
    room:     'chat.5',
);
```

Contract for your endpoints:

- **send** — persist the posted `body`, return `{"message": {id,user_id,name,body,time,mine}}`.
- **poll** — return `{"messages": [ … ]}` for rows after `?after=`.

The component handles bubble rendering (own vs others), auto-scroll, Enter to
send / Shift+Enter for newline, the textarea auto-grow, polling, and the
optional WebSocket. Markup-only — `sofyChat` behaviour + styles ship once per
page from Page (same pattern as DataTable/Combobox).

---

## Data model

- `chat_channels` — `type` (`direct`|`group`), `name` (groups), `dm_key`
  (canonical `minId:maxId` for DMs, so a pair always maps to one channel),
  `created_by`.
- `chat_participants` — membership + `last_read_at` (drives unread counts).
- `chat_messages` — `channel_id`, `user_id` (sender), `body`, timestamps.

Unread counts are "messages newer than my `last_read_at` that I didn't send",
summed for the menu badge and shown per-conversation in the list.
