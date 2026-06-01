<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Database\Connection;
use Sofy\Security\Hash;
use Throwable;

/**
 * Interactive admin creator. Production counterpart to AdminSeeder — instead
 * of reading credentials from env, this prompts for them so the password
 * never lands in .env or the shell history.
 *
 *   php sofy admin:create
 *   php sofy admin:create --email=ops@acme.com --role=admin
 *   php sofy admin:create --email=… --password=…   (non-interactive: useful for provisioning scripts)
 *
 * Same idempotency story as the seeder: existing user → password is replaced,
 * role link is created once. Roles row is auto-created if missing.
 */
class AdminCreateCommand extends Command
{
    protected string $signature   = 'admin:create {--email= : Email (prompted if omitted)} {--name= : Name (defaults to email local-part)} {--password= : Password (auto-generated if omitted)} {--role=admin : Role slug to assign}';
    protected string $description = 'Create or update an admin user and assign a role';

    public function handle(): int
    {
        try {
            $db = Connection::getDefault();
        } catch (Throwable $e) {
            $this->error('No database connection: ' . $e->getMessage());
            $this->line('  Check .env DB_* values, or run `php sofy migrate` first.');
            return 1;
        }

        $email = trim((string) ($this->option('email') ?: $this->ask('Email:')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Valid email is required.');
            return 1;
        }

        $defaultName = explode('@', $email)[0];
        $name = (string) ($this->option('name') ?: $this->ask("Name [default: $defaultName]:"));
        if ($name === '') {
            $name = $defaultName;
        }

        $password  = (string) ($this->option('password') ?: '');
        $generated = false;
        if ($password === '') {
            $password = trim($this->ask('Password (leave empty to auto-generate):'));
            if ($password === '') {
                $password = bin2hex(random_bytes(8));
                $generated = true;
            }
        }

        $role = (string) ($this->option('role') ?: 'admin');

        try {
            $userId = $this->upsertUser($db, $email, $name, $password);
            $this->ensureRole($db, $role);
            $this->assignRole($db, $userId, $role);
        } catch (Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("Admin user ready: $email");
        if ($generated) {
            $this->line('');
            $this->line("  Generated password: \033[1;33m$password\033[0m");
            $this->warn('Save it — this is the only time it is shown.');
        }
        return 0;
    }

    private function upsertUser(Connection $db, string $email, string $name, string $password): int
    {
        $existing = $db->query('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        $hash     = Hash::make($password);
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            $id = (int) $existing[0]['id'];
            $db->execute(
                'UPDATE users SET name = ?, password = ?, updated_at = ? WHERE id = ?',
                [$name, $hash, $now, $id],
            );
            $this->info("Updated existing user (id=$id).");
            return $id;
        }

        $db->execute(
            'INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$name, $email, $hash, $now, $now],
        );
        $id = (int) ($db->lastInsertId() ?: $db->query('SELECT id FROM users WHERE email = ?', [$email])[0]['id']);
        $this->info("Created user (id=$id).");
        return $id;
    }

    private function ensureRole(Connection $db, string $slug): void
    {
        $existing = $db->query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug]);
        if ($existing) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO roles (name, slug, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [ucfirst($slug), $slug, $now, $now],
        );
        $this->info("Created role: $slug");
    }

    private function assignRole(Connection $db, int $userId, string $slug): void
    {
        $row    = $db->query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])[0] ?? null;
        if (!$row) {
            return;
        }
        $roleId = (int) $row['id'];

        $linked = $db->query(
            'SELECT 1 FROM role_user WHERE user_id = ? AND role_id = ? LIMIT 1',
            [$userId, $roleId],
        );
        if ($linked) {
            $this->info("Role '$slug' already assigned to user $userId.");
            return;
        }

        $db->execute('INSERT INTO role_user (role_id, user_id) VALUES (?, ?)', [$roleId, $userId]);
        $this->info("Assigned role '$slug' to user $userId.");
    }
}
