# Browser notifications (desktop + sound)

Any code that notifies a user surfaces in the admin as a **desktop notification
with a sound** — built on the existing database notifications, zero-asset (the
chime is synthesized with WebAudio, no audio file), with an in-page toast
fallback when the browser denies permission.

---

## How it works

1. Somewhere you notify a user with a database notification whose
   `toDatabase()` returns a `title` (and optional `body` / `url`):

   ```php
   class TaskAssigned extends Notification
   {
       public function via(mixed $n): array { return ['database']; }
       public function toDatabase(mixed $n): array {
           return [
               'title' => 'New task assigned',
               'body'  => $this->task->name,
               'url'   => '/admin/tasks/' . $this->task->id,
           ];
       }
   }

   $user->notify(new TaskAssigned($task));
   ```

2. Every admin page runs `sofyNotify`, which polls
   `GET /admin/notifications/feed` for that user's unread notifications. New
   ones fire a browser notification + chime, then are marked seen via
   `POST /admin/notifications/seen`.

3. The **bell** in the admin topbar enables desktop notifications for that
   browser (browsers require a user gesture to grant `Notification`
   permission). Until enabled — or if permission is denied — notifications
   appear as an in-page toast instead, still with sound.

No configuration, no extra tables: it rides on the framework's existing
`notifications` table and the `Notifiable` trait (`User` already uses it).

---

## The data contract

The feed maps each notification's stored `data` to:

| key     | used for                                  |
|---------|-------------------------------------------|
| `title` | notification heading (required)           |
| `body`  | notification text                          |
| `url`   | where clicking it navigates                |
| `tag`   | dedupe key (defaults to the row id)        |

`subject`/`message` are accepted as aliases for `title`/`body`.

---

## From JavaScript

`sofyNotify` is global on every page:

```js
sofyNotify.show({ title: 'Saved', body: 'Your changes are live', url: '/admin' });
sofyNotify.beep();                 // just the chime
sofyNotify.request();              // prompt for permission
sofyNotify.enabled();              // is desktop notify on for this browser?
```

`show()` raises a desktop notification when the tab is in the background and
permission is granted; otherwise it shows the toast. Either way it chimes
(pass `silent: true` to mute).

---

## Messenger uses it

The Messenger module notifies the other participants on every message
(`NewMessageNotification`), so a chat message pops a desktop notification with
sound anywhere in the admin, and clicking it opens the conversation. See
`docs/18-messenger.md`.
