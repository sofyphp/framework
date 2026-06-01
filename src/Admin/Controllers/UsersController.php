<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Http\Response;
use Sofy\View\UI;

/**
 * Built-in admin example. Lists rows from the `users` table — covers the
 * stock users migration that ships with the framework, but degrades
 * gracefully when the table doesn't exist yet (e.g. before migrations have
 * run, or in an app that decided not to use the default users table).
 *
 * Apps that want their own users page can either replace this route from
 * routes/web.php or add their menu item under a different key.
 */
class UsersController
{
    public function index(): Response
    {
        $rows = $this->fetchUsers();

        if ($rows === null) {
            return Admin::page('Users')
                ->header('Users')
                ->add(UI::alert(
                    'The users table does not exist yet — run `php sofy migrate` to create it.',
                    'warning',
                    'No users table',
                ))
                ->response();
        }

        $body = empty($rows)
            ? UI::emptyState('No users yet', 'Create users via your registration flow or seed them with a factory.', icon: '👥')
            : UI::dataTable(
                ['ID', 'Name', 'Email', 'Created'],
                $rows,
                ['id', 'name', 'email', fn(array $r) => $this->formatDate($r['created_at'] ?? null)],
                perPage: 25,
            );

        return Admin::page('Users')
            ->header('Users (' . count($rows) . ')')
            ->add(UI::card(null, $body))
            ->response();
    }

    /** @return list<array<string,mixed>>|null  null when the table is missing. */
    private function fetchUsers(): ?array
    {
        try {
            $db = \Sofy\Database\Connection::getDefault();
        } catch (\Throwable) {
            return null;
        }

        try {
            return $db->query('SELECT id, name, email, created_at FROM users ORDER BY id DESC LIMIT 500');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDate(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        try {
            return (new \DateTimeImmutable((string) $raw))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $raw;
        }
    }
}
