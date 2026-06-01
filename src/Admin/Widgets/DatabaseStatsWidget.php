<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\Database\Connection;
use Sofy\Database\Schema\Grammar;
use Sofy\View\UI;

/**
 * Stat tile: number of tables in the live database + the driver name.
 * Same code path the database browser uses (Grammar::listTablesSql) so
 * the count stays consistent with what /admin/database shows.
 */
class DatabaseStatsWidget extends AdminWidget
{
    public int $order = 20;
    public int $cols  = 1;

    public function render(): mixed
    {
        try {
            $conn   = Connection::getDefault();
            $driver = $conn->getDriverName();
            $tables = count($conn->query(Grammar::forConnection($conn)->listTablesSql()));

            return UI::stat(
                'Database',
                number_format($tables),
                trend: $driver,
                description: 'tables',
            );
        } catch (\Throwable) {
            return UI::stat(
                'Database',
                '—',
                trend: 'not connected',
                description: 'check .env DB_*',
            );
        }
    }
}
