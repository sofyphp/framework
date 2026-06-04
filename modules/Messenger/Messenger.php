<?php

declare(strict_types=1);

namespace Messenger;

use Messenger\Models\Participant;
use Sofy\Admin\Admin;
use Sofy\Auth\Auth;
use Sofy\Core\Application;
use Sofy\Core\Module;
use Sofy\View\Icons;

/**
 * Messenger — user-to-user messaging in the admin. 1:1 DMs + named group
 * channels, a chat thread (UI::chat), live via polling and upgradeable to
 * WebSocket push (ws:serve --handler="Messenger\WebSocket\ChatHandler").
 *
 * Self-contained: models, migration, controller, routes, config and this
 * module file. Routes register under /admin/messages behind EnsureAdmin.
 */
class Messenger extends Module
{
    public function name(): string
    {
        return 'messenger';
    }

    public function config(): array
    {
        return require $this->path('config.php');
    }

    public function register(Application $app): void
    {
        Admin::menu()->add('messenger', 'Сообщения', '/admin/messages')
            ->icon(Icons::MESSAGE_CIRCLE)
            ->section('Manage')
            ->order(20)
            ->badge(static function (): string {
                try {
                    $me = Auth::id();
                    if ($me === null) return '';
                    $n = Participant::unreadCount($me);
                    return $n > 0 ? (string) $n : '';
                } catch (\Throwable) {
                    return '';
                }
            });
    }
}
