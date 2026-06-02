# Sofy Module Spec

Modules that want to be discoverable in the Sofy marketplace ship a
`sofy-module.json` manifest in their repository root. Without it, the
module still works (Sofy autoloads by folder convention), but it won't
show up in `/admin/system/marketplace`.

## Manifest example

```json
{
  "slug": "orders",
  "name": "Orders",
  "namespace": "Orders\\",
  "version": "1.0.0",
  "description": "Customer orders with line items, statuses and admin CRUD.",
  "author": "sofyphp",
  "homepage": "https://github.com/sofyphp/orders-module",
  "license": "MIT",
  "requires": {
    "sofy": "^0.4",
    "php":  "^8.3",
    "ext":  ["pdo"]
  },
  "categories": ["commerce"],
  "tags": ["orders", "checkout"],
  "screenshots": [
    "https://raw.githubusercontent.com/sofyphp/orders-module/main/docs/list.png"
  ],
  "dist": {
    "type": "github-release",
    "repo": "sofyphp/orders-module"
  }
}
```

## Fields

| Field | Type | Required | Meaning |
|---|---|---|---|
| `slug`        | string  | ✓ | Kebab-case identifier. Stable. Used in URLs and CLI (`marketplace:install orders`). Author-scoped form `vendor/name` is allowed. |
| `name`        | string  | ✓ | PascalCase folder + class name. Determines the on-disk folder (`modules/Orders/`) and the module class FQCN (`Orders\Orders`). |
| `namespace`   | string  | ✓ | PSR-4 prefix (with trailing `\\`). Patched into the host app's `composer.json` on install. |
| `version`     | semver  | ✓ | Module release version. |
| `description` | string  | ✓ | One-sentence summary; shown on the marketplace card. |
| `author`      | string  | ✓ | Display name or org. |
| `homepage`    | URL     | – | Repo, docs page or landing. |
| `license`     | string  | – | SPDX identifier (`MIT`, `Apache-2.0`, …). |
| `requires`    | object  | – | `sofy`/`php`/`ext` compatibility constraints (composer-style). |
| `categories`  | strings | – | Coarse grouping for filters (`commerce`, `cms`, `auth`, …). |
| `tags`        | strings | – | Free-form search keywords. |
| `screenshots` | URLs    | – | PNG/JPG/WebP. Shown on the detail card. |
| `dist`        | object  | ✓ | Where to fetch the module from. See below. |

## `dist`

How the marketplace installer downloads the module:

```jsonc
// GitHub release zip (preferred — semver-tagged, immutable)
{ "type": "github-release", "repo": "sofyphp/orders-module" }

// Specific tag from a GitHub archive
{ "type": "github-tag", "repo": "sofyphp/orders-module", "tag": "v1.2.0" }

// Direct zip URL (CDN, S3, anywhere serving public HTTPS)
{ "type": "zip", "url": "https://cdn.example.com/orders-1.0.0.zip" }
```

For `github-release` the installer asks the GitHub API for the latest
release and downloads its source zip. For `github-tag` it goes straight
to `https://github.com/{repo}/archive/refs/tags/{tag}.zip`.

## Catalog file

The marketplace reads a catalog JSON at the URL set in
`config('marketplace.catalog_url')` (default:
`https://raw.githubusercontent.com/sofyphp/marketplace/main/modules.json`).
The file is just an array of manifests:

```json
{
  "$schema": "https://sofyphp.com/marketplace.schema.json",
  "modules": [
    { "slug": "orders", "name": "Orders", "...": "(full manifest)" },
    { "slug": "blog",   "name": "Blog",   "...": "(full manifest)" }
  ]
}
```

To add a module: PR the entry into `sofyphp/marketplace`. Until that
goes live, Sofy ships a bundled fallback at `docs/marketplace.json`
inside the framework — it's what the admin shows when remote fetch
fails or the URL is empty.
