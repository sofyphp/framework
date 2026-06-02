<?php

declare(strict_types=1);

/**
 * Marketplace config — controls where /admin/system/marketplace gets its
 * catalog from. Override per-environment via .env (MARKETPLACE_CATALOG_URL).
 *
 * Leave catalog_url empty to rely on the bundled docs/marketplace.json
 * (offline / air-gapped installs still get a usable page).
 */
return [
    /*
     * URL of the catalog JSON file ({"modules": [...]}). Hosted by the
     * Sofy team at sofyphp/marketplace; you can point this at your own
     * private registry and serve the same shape.
     */
    'catalog_url' => env('MARKETPLACE_CATALOG_URL', 'https://raw.githubusercontent.com/sofyphp/marketplace/main/modules.json'),

    /*
     * If true, /admin/system/marketplace install button will run
     * migrations from the new module right after extraction. Disable
     * if your prod policy requires a separate `php sofy migrate` step.
     */
    'run_migrations_after_install' => env('MARKETPLACE_RUN_MIGRATIONS', true),
];
