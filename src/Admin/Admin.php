<?php

declare(strict_types=1);

namespace Sofy\Admin;

use Sofy\Http\Response;
use Sofy\View\UI;

/**
 * Admin — static facade for the Sofy admin panel.
 *
 * Modules and applications use this in three ways:
 *
 *   1. Register menu items (typically inside Module::register()):
 *
 *      Admin::menu()->add('blog.posts', 'Posts', '/admin/blog/posts')
 *          ->icon('📝')->section('Content')->order(10);
 *
 *   2. Register dashboard widgets:
 *
 *      Admin::widget(\Main\Admin\UsersCountWidget::class);
 *
 *   3. Render an admin page from a controller:
 *
 *      return Admin::page('Users')
 *          ->add(UI::card('All users', UI::dataTable(...)))
 *          ->response();
 *
 * Other tweakable bits live on Admin::panel() — brand text, auth toggle,
 * login URL.
 */
class Admin
{
    public static function panel(): AdminPanel
    {
        return AdminPanel::instance();
    }

    public static function menu(): MenuRegistrar
    {
        return new MenuRegistrar(AdminPanel::instance());
    }

    public static function widget(AdminWidget|string $widget): AdminWidget
    {
        return AdminPanel::instance()->addWidget($widget);
    }

    public static function page(string $title): AdminPage
    {
        return new AdminPage($title);
    }

    /** Turn on the EnsureAdmin middleware globally. Off by default. */
    public static function useAuth(string $requiredRole = 'admin'): void
    {
        $panel = AdminPanel::instance();
        $panel->requireAuth  = true;
        $panel->requiredRole = $requiredRole;
    }

    public static function brand(string $brand): void
    {
        AdminPanel::instance()->brand = $brand;
    }
}

/**
 * Tiny fluent wrapper so Admin::menu()->add(...) reads nicely while still
 * delegating the bookkeeping to AdminPanel.
 *
 * @internal
 */
final class MenuRegistrar
{
    public function __construct(private readonly AdminPanel $panel) {}

    public function add(string $key, string $label, string $url): MenuItem
    {
        return $this->panel->addMenuItem(new MenuItem($key, $label, $url));
    }
}
