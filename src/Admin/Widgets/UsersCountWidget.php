<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\Database\Connection;
use Sofy\View\UI;

/**
 * Stat tile: total registered users + the delta from the last 7 days.
 * Falls back to 0 when the `users` table doesn't exist yet — useful for
 * a brand-new install before `php sofy migrate` has run.
 */
class UsersCountWidget extends AdminWidget
{
    public int $order = 10;
    public int $cols  = 1;

    public function render(): mixed
    {
        [$total, $weekly] = $this->numbers();

        $trend = $weekly > 0 ? "+{$weekly} this week" : null;

        return UI::stat(
            'Users',
            number_format($total),
            trend: $trend,
            description: 'registered accounts',
        );
    }

    /** @return array{0:int,1:int} */
    private function numbers(): array
    {
        try {
            $conn  = Connection::getDefault();
            $total = (int) (array_values($conn->query('SELECT COUNT(*) AS c FROM users')[0] ?? ['c' => 0])[0]);

            // Driver-aware "last 7 days" — PG/SQLite/MySQL all accept
            // a literal date string in the WHERE clause.
            $since  = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $weekly = (int) (array_values($conn->query(
                'SELECT COUNT(*) AS c FROM users WHERE created_at >= ?',
                [$since],
            )[0] ?? ['c' => 0])[0]);
        } catch (\Throwable) {
            $total = 0;
            $weekly = 0;
        }

        return [$total, $weekly];
    }
}
