# Laravel 12 & 13 Support — Design

**Date:** 2026-06-03
**Branch:** `empire-medical-customizations-l12-l13` (off `empire-medical-customizations-l11`)
**Status:** Draft

## Goal

Drop support for Laravel 8–11 and PHP < 8.2. Add support for Laravel 12 and 13 on PHP 8.2+. Preserve all behavior changes already made on the `empire-medical-customizations-l11` branch (int duration default, removed `get_locked_on_retrieve`, removed `user_type` reference from `IsLockable`, changed `isLocked` behavior, Carbon 3 compatibility). No public API changes.

## Non-goals

- No new features.
- No refactor of the `requestLock()` / `lockWatchers` code path. It still references `user_type` on the watcher model; that column lives on the watcher table and is a separate concern from the trait-level removal already done on this branch. Out of scope here.
- No changes to the package's public surface (trait methods, events, models, controller, commands, facade).

## Scope of changes

### 1. `composer.json`

**Runtime requires:**

- `php`: `^8.2`
- `illuminate/contracts`: `^12.0|^13.0`
- `laravel/framework`: `^12.0|^13.0`

**Dev requires:**

- `orchestra/testbench`: `^10.0|^11.0` (10 → L12, 11 → L13)
- `phpunit/phpunit`: `^11.0|^12.0`
- `pestphp/pest`: `^3.0`
- `pestphp/pest-plugin-laravel`: `^3.0`
- `nunomaduro/collision`: `^8.0`
- `larastan/larastan`: `^3.0` (vendor moved from `nunomaduro/larastan`)
- `phpstan/extension-installer`: keep, bump if needed
- `phpstan/phpstan-deprecation-rules`: bump to current major
- `phpstan/phpstan-phpunit`: bump to current major

**Drop:**

- `laravel/legacy-factories` — incompatible with L12/L13, and tests already use modern class-based factories.
- `phpunit/php-code-coverage` — transitive via PHPUnit 11/12.

### 2. `phpunit.xml.dist`

Migrate to the PHPUnit 11/12 schema:

- Remove deprecated attributes: `backupStaticAttributes`, `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions`, `verbose`.
- Replace `<coverage><include>…</include></coverage>` with the new top-level `<source>` element. Move `<report>` to be a sibling of `<source>`, not nested under `<coverage>`.
- Update `xsi:noNamespaceSchemaLocation` to point at the new schema location.

### 3. `tests/TestCase.php`

- Remove the `$this->withFactories(__DIR__.'/database/factories')` call in `setUp()`. That method came from `laravel/legacy-factories`, which we're dropping. The class-based factories in `tests/database/factories/` are autoloaded via composer and don't need explicit registration.

### 4. Source code

No expected changes. The trait, models, controller, commands, and service provider use only stable Laravel APIs that survive into L12/L13. The branch's existing fixes (int duration in `config/config.php`, Carbon 3 compat) cover the known breaking changes.

If `composer update`, `pest`, or `phpstan` surface anything, fix it in place. Anything more invasive than a one-line tweak should pause and ask.

### 5. CI workflows

Delete the stale per-Laravel-version workflows:

- `.github/workflows/laravel7-tests.yml`
- `.github/workflows/laravel8-tests.yml`
- `.github/workflows/laravel9-tests.yml`

Add a single `.github/workflows/tests.yml` that matrixes over:

- Laravel: `12.*`, `13.*`
- PHP: `8.2`, `8.3`, `8.4`
- Stability: `prefer-stable`

Pattern after the existing workflows for shell, caching, and runner OS. Keep the other workflows (`phpstan.yml`, `fix-php-code-style-issues.yml`, `dependency-review.yml`, `devskim.yml`, `dependabot-auto-merge.yml`, `update-changelog.yml`) intact unless they reference dropped tooling.

### 6. PHPStan baseline

Delete `phpstan-baseline.neon` and regenerate it after the upgrade. Workflow:

1. Run `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon` after upgrading dependencies.
2. Commit the regenerated baseline.

If the regenerated baseline is empty (i.e., the analyser finds no issues), remove the `phpstan-baseline.neon` include from `phpstan.neon.dist` and don't commit an empty baseline.

### 7. Verification

After all changes:

1. `composer update` — must resolve cleanly.
2. `vendor/bin/pest` — full suite must pass.
3. `vendor/bin/phpstan analyse` — must pass (possibly via the regenerated baseline).
4. Spot-check that the package's service provider boots in a real Laravel 12 and 13 testbench app (the matrix CI run covers this).

## Components touched

| File | Change |
|---|---|
| `composer.json` | Update requires, dev-requires |
| `phpunit.xml.dist` | Migrate to PHPUnit 11/12 schema |
| `tests/TestCase.php` | Drop `withFactories()` call |
| `phpstan-baseline.neon` | Delete, regenerate (or remove if empty) |
| `.github/workflows/laravel7-tests.yml` | Delete |
| `.github/workflows/laravel8-tests.yml` | Delete |
| `.github/workflows/laravel9-tests.yml` | Delete |
| `.github/workflows/tests.yml` | Create (L12/L13 × PHP 8.2/8.3/8.4 matrix) |

## Risks

- **Pest 3 / PHPUnit 11–12 strictness:** May surface previously-tolerated risky tests. If so, fix the tests, don't disable the strictness.
- **Testbench 10/11 environment differences:** The custom auth guard setup in `TestCase::defineEnvironment()` could trip on Testbench's stricter validation. Mitigation: run the suite locally before pushing.
- **`requestLock()` watcher code path:** Untouched by this work but still references `user_type`. If a future change removes that column from the watcher model, this method will break. Flagged here, not fixed here.

## Out-of-scope follow-ups (do not do now)

- Remove `user_type` from the `lockWatchers` model / migration.
- Update README for L12/L13 install instructions.
- Revisit dependabot config for the new dependency set.
