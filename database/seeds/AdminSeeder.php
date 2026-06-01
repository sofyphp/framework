<?php

declare(strict_types=1);

use Sofy\Database\Connection;
use Sofy\Database\Seeder;
use Sofy\Security\Hash;

/**
 * Create (or update) an admin user and assign them the 'admin' role.
 *
 * Run it standalone:
 *
 *   php sofy db:seed --class=AdminSeeder
 *
 * Or call it from your DatabaseSeeder. Idempotent — if a user with the same
 * email already exists, only the password is overwritten (and only if
 * ADMIN_PASSWORD is explicitly set; a freshly-generated one won't silently
 * replace the existing hash).
 *
 * Configurable via environment variables (.env):
 *
 *   ADMIN_EMAIL=admin@example.com        # default: admin@example.com
 *   ADMIN_NAME=Admin                     # default: Admin
 *   ADMIN_PASSWORD=                      # default: auto-generated, printed
 *   ADMIN_ROLE=admin                     # default: admin
 *
 * Pairs with Admin::useAuth() — after running this once and enabling auth,
 * the admin can sign in with the printed credentials.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) ($_ENV['ADMIN_EMAIL']    ?? getenv('ADMIN_EMAIL')    ?: 'admin@example.com'));
        $name  =       (string) ($_ENV['ADMIN_NAME']     ?? getenv('ADMIN_NAME')     ?: 'Admin');
        $role  =       (string) ($_ENV['ADMIN_ROLE']     ?? getenv('ADMIN_ROLE')     ?: 'admin');
        $pass  =       (string) ($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '');

        $generated = $pass === '';
        if ($generated) {
            $pass = bin2hex(random_bytes(8)); // 16 hex chars
        }

        $db = Connection::getDefault();

        if (!$this->tableExists($db, 'users') || !$this->tableExists($db, 'roles')) {
            $this->line("\033[31m  ✗ users / roles table missing — run `php sofy migrate` first.\033[0m");
            return;
        }

        $this->ensureRole($db, $role, ucfirst($role));

        $existing = $db->query('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);

        if ($existing) {
            $userId = (int) $existing[0]['id'];
            if ($generated) {
                $this->line("  • User \033[1m$email\033[0m already exists — keeping current password (set ADMIN_PASSWORD to override).");
                $generated = false; // don't print a password that wasn't applied
            } else {
                $db->execute(
                    'UPDATE users SET password = ?, updated_at = ? WHERE id = ?',
                    [Hash::make($pass), date('Y-m-d H:i:s'), $userId],
                );
                $this->line("  ✓ Updated password for \033[1m$email\033[0m (id=$userId)");
            }
        } else {
            $now = date('Y-m-d H:i:s');
            $db->execute(
                'INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$name, $email, Hash::make($pass), $now, $now],
            );
            $userId = (int) ($db->lastInsertId() ?: $db->query('SELECT id FROM users WHERE email = ?', [$email])[0]['id']);
            $this->line("  ✓ Created admin user \033[1m$email\033[0m (id=$userId)");
        }

        $this->assignRole($db, $userId, $role);

        if ($generated) {
            $this->line('');
            $this->line('  ┌─────────────────────────────────────────┐');
            $this->line('  │ Generated admin credentials             │');
            $this->line('  ├─────────────────────────────────────────┤');
            $this->line("  │  Email:    \033[1m$email\033[0m");
            $this->line("  │  Password: \033[1;33m$pass\033[0m");
            $this->line('  └─────────────────────────────────────────┘');
            $this->line('  Save the password — it is not stored anywhere else.');
        }
    }

    private function tableExists(Connection $db, string $table): bool
    {
        try {
            $db->query("SELECT 1 FROM \"$table\" LIMIT 1");
            return true;
        } catch (\Throwable) {
            // Fallback for MySQL (which doesn't accept double-quoted identifiers
            // unless ANSI_QUOTES mode is on).
            try {
                $db->query("SELECT 1 FROM `$table` LIMIT 1");
                return true;
            } catch (\Throwable) {
                return false;
            }
        }
    }

    private function ensureRole(Connection $db, string $slug, string $name): void
    {
        $existing = $db->query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug]);
        if ($existing) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO roles (name, slug, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$name, $slug, $now, $now],
        );
        $this->line("  ✓ Created role: $slug");
    }

    private function assignRole(Connection $db, int $userId, string $slug): void
    {
        $role   = $db->query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])[0] ?? null;
        if (!$role) {
            return;
        }
        $roleId = (int) $role['id'];

        $linked = $db->query(
            'SELECT 1 FROM role_user WHERE user_id = ? AND role_id = ? LIMIT 1',
            [$userId, $roleId],
        );
        if ($linked) {
            $this->line("  • Role '$slug' already assigned to user $userId");
            return;
        }

        $db->execute('INSERT INTO role_user (role_id, user_id) VALUES (?, ?)', [$roleId, $userId]);
        $this->line("  ✓ Assigned role '$slug' to user $userId");
    }
}
