<?php

declare(strict_types=1);

namespace Sofy\Admin;

/**
 * Thin back-compat alias for \Sofy\View\Icons.
 *
 * The icon library was promoted to a framework-wide catalog in v0.4.2 so
 * any UI component (cards, buttons, banners) — not just the admin sidebar
 * — can reuse it. This class inherits every constant from the parent so
 * existing imports of \Sofy\Admin\Icons keep working unchanged.
 *
 * New code should reach for \Sofy\View\Icons directly, or use the
 * UI::icon('name') factory for sized / recoloured renders.
 */
final class Icons extends \Sofy\View\Icons
{
}
