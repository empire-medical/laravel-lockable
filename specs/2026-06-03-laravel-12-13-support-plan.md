# Laravel 12 & 13 Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Drop Laravel 8–11 / PHP < 8.2 support and add Laravel 12 + 13 support on PHP 8.2+ to this package, preserving the branch's existing behavior changes.

**Architecture:** Constraint-and-config upgrade. No source rewrites. Bump runtime + dev dependencies in `composer.json`, migrate `phpunit.xml.dist` to PHPUnit 11/12 schema, drop the legacy-factories shim in `tests/TestCase.php`, replace the stale per-Laravel-version GitHub Actions workflows with a single matrixed workflow, regenerate the PHPStan baseline.

**Tech Stack:** PHP 8.2+, Laravel 12/13, PHPUnit 11/12, Pest 3, Orchestra Testbench 10/11, Larastan 3.

**Spec:** `specs/2026-06-03-laravel-12-13-support-design.md`

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `composer.json` | Modify | Declare new PHP/Laravel/dev-tool version constraints |
| `phpunit.xml.dist` | Modify | PHPUnit 11/12-compatible configuration |
| `tests/TestCase.php` | Modify | Remove legacy-factories registration |
| `phpstan.neon.dist` | Modify | Drop deprecated `checkMissingIterableValueType`; keep baseline include conditional |
| `phpstan-baseline.neon` | Replace | Regenerate against new toolchain (or delete if empty) |
| `.github/workflows/laravel7-tests.yml` | Delete | Stale |
| `.github/workflows/laravel8-tests.yml` | Delete | Stale |
| `.github/workflows/laravel9-tests.yml` | Delete | Stale |
| `.github/workflows/tests.yml` | Create | L12/L13 × PHP 8.2/8.3/8.4 test matrix |

No source files (`src/**`) are expected to change. If `composer update` or the test/phpstan run surfaces required source changes, that's a deviation from this plan — pause and reassess.

---

## Task 1: Bump `composer.json` constraints

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Replace `composer.json` with the upgraded version**

Replace the entire contents of `composer.json` with:

```json
{
    "name": "lowerrocklabs/laravel-lockable",
    "description": "Laravel Lockable provides traits to allow for models to be locked",
    "keywords": [
        "LowerRockLabs",
        "laravel",
        "laravel-lockable"
    ],
    "homepage": "https://github.com/lowerrocklabs/laravel-lockable",
    "license": "MIT",
    "authors": [
        {
            "name": "Joe",
            "email": "joe@lowerrocklabs.com",
            "role": "Developer"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^12.0|^13.0",
        "laravel/framework": "^12.0|^13.0"
    },
    "require-dev": {
        "larastan/larastan": "^3.0",
        "nunomaduro/collision": "^8.0",
        "orchestra/testbench": "^10.0|^11.0",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "phpstan/extension-installer": "^1.4",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0",
        "phpunit/phpunit": "^11.0|^12.0"
    },
    "autoload": {
        "psr-4": {
            "LowerRockLabs\\Lockable\\": "src",
            "LowerRockLabs\\Lockable\\Database\\Factories\\": "database/factories"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "LowerRockLabs\\Lockable\\Tests\\": "tests"
        }
    },
    "scripts": {
        "analyse": "vendor/bin/phpstan analyse",
        "test": "vendor/bin/pest",
        "test-coverage": "vendor/bin/pest --coverage",
        "format": "vendor/bin/pint"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "phpstan/extension-installer": true
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "LowerRockLabs\\Lockable\\LockableServiceProvider"
            ],
            "aliases": {
                "Lockable": "LowerRockLabs\\Lockable\\Facades\\Lockable"
            }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Notable diffs from the prior file:

- `require.php`: `^7.3|^8.0` → `^8.2`
- `require.illuminate/contracts`: `^8.0|^9.0|^10.0|^11.0` → `^12.0|^13.0`
- `require.laravel/framework`: `^8.0|^9.19|^10.0|^11.0` → `^12.0|^13.0`
- Removed `require-dev.laravel/legacy-factories`
- Removed `require-dev.phpunit/php-code-coverage` (transitive via PHPUnit)
- `nunomaduro/larastan` (`^1.0|^2.0.1`) → `larastan/larastan` (`^3.0`) — vendor moved
- `nunomaduro/collision`: `^5.0|^6.0|^7.0` → `^8.0`
- `orchestra/testbench`: `^6.0|^7.9` → `^10.0|^11.0`
- `pestphp/pest`: `^1.21|^2.0` → `^3.0`
- `pestphp/pest-plugin-laravel`: `^1.1|^2.0` → `^3.0`
- `phpunit/phpunit`: `^9.5|^10.0` → `^11.0|^12.0`
- `phpstan/extension-installer`: `^1.1` → `^1.4`
- `phpstan/phpstan-deprecation-rules`: `^1.0` → `^2.0`
- `phpstan/phpstan-phpunit`: `^1.0` → `^2.0`

- [ ] **Step 2: Resolve dependencies**

Run: `composer update --no-interaction --prefer-dist`

Expected: clean resolution, no errors. If composer reports a conflict, do **not** add `--with-all-dependencies` or relax constraints without reading the error first. Stop and report.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "Bump composer deps for Laravel 12/13, PHP 8.2+

Drop Laravel 8-11 and PHP <8.2 support. Pin to L12/L13 on PHP 8.2+,
upgrade dev toolchain to PHPUnit 11/12, Pest 3, Testbench 10/11,
Larastan 3 (note: nunomaduro/larastan was renamed to larastan/larastan).
Drop legacy-factories shim — class-based factories handle this now."
```

---

## Task 2: Migrate `phpunit.xml.dist` to PHPUnit 11/12 schema

**Files:**
- Modify: `phpunit.xml.dist`

- [ ] **Step 1: Replace the file**

Replace the entire contents of `phpunit.xml.dist` with:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    backupGlobals="false"
    bootstrap="vendor/autoload.php"
    colors="true"
    processIsolation="false"
    stopOnFailure="false"
    executionOrder="random"
    failOnWarning="true"
    failOnRisky="true"
    failOnEmptyTestSuite="true"
    beStrictAboutOutputDuringTests="true"
    cacheDirectory="build/.phpunit.cache"
>
    <testsuites>
        <testsuite name="VendorName Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
        <exclude>
            <directory suffix=".php">./src/Events</directory>
            <directory suffix=".php">./src/Facades</directory>
        </exclude>
    </source>
    <coverage>
        <report>
            <html outputDirectory="build/coverage"/>
            <text outputFile="build/coverage.txt"/>
            <clover outputFile="build/logs/clover.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="build/report.junit.xml"/>
    </logging>
</phpunit>
```

Notable diffs:

- Removed `backupStaticAttributes` (removed in PHPUnit 10).
- Removed `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions` (removed in PHPUnit 10).
- Removed `verbose` (removed in PHPUnit 10).
- Added `cacheDirectory="build/.phpunit.cache"` (replaces the old `cacheResultFile` default behavior).
- Moved `<include>`/`<exclude>` out of `<coverage>` into the new top-level `<source>` element.
- Kept `<coverage>` with only the `<report>` child, since PHPUnit 10+ requires source set under `<source>`.

- [ ] **Step 2: Run the test suite to confirm the config parses**

Run: `vendor/bin/pest`

Expected: tests run (pass or fail). If PHPUnit complains about the config schema, the most likely cause is a stray deprecated attribute — re-check against the file above.

- [ ] **Step 3: Commit**

```bash
git add phpunit.xml.dist
git commit -m "Migrate phpunit.xml.dist to PHPUnit 11/12 schema

Drop deprecated attributes (backupStaticAttributes, convertErrors*,
convertNotices*, convertWarnings*, verbose). Move coverage include/exclude
into the new top-level <source> element."
```

---

## Task 3: Drop `withFactories()` from `tests/TestCase.php`

**Files:**
- Modify: `tests/TestCase.php`

- [ ] **Step 1: Remove the legacy-factories call**

In `tests/TestCase.php`, find:

```php
    protected function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__.'/database/factories');
    }
```

Replace with:

```php
    protected function setUp(): void
    {
        parent::setUp();
    }
```

That's the only change to this file.

- [ ] **Step 2: Run the test suite**

Run: `vendor/bin/pest`

Expected: all tests pass. The class-based factories under `tests/database/factories/` (e.g., `UserFactory`, `AdminFactory`, `NoteFactory`) are already autoloaded via the `LowerRockLabs\Lockable\Tests\` PSR-4 mapping in `composer.json`, so no explicit registration is needed.

If a test fails with "No factory defined for model X" or similar, check that the factory class's `$model` property matches the model class. Don't reintroduce `withFactories()` — it's gone in modern Laravel.

- [ ] **Step 3: Commit**

```bash
git add tests/TestCase.php
git commit -m "Drop laravel/legacy-factories shim from test bootstrap

withFactories() came from the legacy-factories package, which is dropped.
Class-based factories under tests/database/factories/ are autoloaded
via the Tests\\ PSR-4 mapping and need no explicit registration."
```

---

## Task 4: Delete stale CI workflows

**Files:**
- Delete: `.github/workflows/laravel7-tests.yml`
- Delete: `.github/workflows/laravel8-tests.yml`
- Delete: `.github/workflows/laravel9-tests.yml`

- [ ] **Step 1: Delete the files**

Run:

```bash
git rm .github/workflows/laravel7-tests.yml \
       .github/workflows/laravel8-tests.yml \
       .github/workflows/laravel9-tests.yml
```

- [ ] **Step 2: Commit**

```bash
git commit -m "Remove stale per-Laravel-version CI workflows

L7/L8/L9 are no longer supported; a single matrixed tests.yml
will replace them in the next commit."
```

---

## Task 5: Add the new `tests.yml` matrix workflow

**Files:**
- Create: `.github/workflows/tests.yml`

- [ ] **Step 1: Write the workflow**

Create `.github/workflows/tests.yml` with:

```yaml
name: tests

on:
  push:
    branches: [master, main]
  pull_request:
    branches: [master, main]

jobs:
  test:
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ubuntu-latest]
        php: ['8.2', '8.3', '8.4']
        laravel: ['12.*', '13.*']
        stability: [prefer-stable]
        include:
          - laravel: '12.*'
            testbench: '10.*'
          - laravel: '13.*'
            testbench: '11.*'

    name: P${{ matrix.php }} - L${{ matrix.laravel }} - ${{ matrix.stability }} - ${{ matrix.os }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, intl, fileinfo
          coverage: none

      - name: Setup problem matchers
        run: |
          echo "::add-matcher::${{ runner.tool_cache }}/php.json"
          echo "::add-matcher::${{ runner.tool_cache }}/phpunit.json"

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}" --no-interaction --no-update
          composer update --${{ matrix.stability }} --prefer-dist --no-interaction

      - name: List Installed Dependencies
        run: composer show -D

      - name: Run Tests
        run: vendor/bin/pest
```

Notes:

- Dropped `windows-latest` from the prior workflows' matrix — the package is Linux-only in practice and adding Windows doubles the job count. If you want Windows back, add `windows-latest` to `matrix.os`.
- Dropped the Code Climate test reporter integration (with its hardcoded reporter ID) since it tied to the original repo. Re-add later if the fork wants its own coverage reporting.
- Dropped `prefer-lowest` from the stability matrix — with only two Laravel majors and a single-major Testbench mapping each, `prefer-lowest` adds noise without surfacing real issues. Re-add if desired.

- [ ] **Step 2: Validate YAML locally**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/tests.yml'))"`

Expected: no output (parse succeeds). If `python3`/`yaml` is unavailable, skip this step.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "Add tests.yml workflow with L12/L13 x PHP 8.2/8.3/8.4 matrix

Replaces the deleted laravel{7,8,9}-tests.yml files. Ubuntu only.
prefer-stable only. No Code Climate integration."
```

---

## Task 6: Refresh PHPStan config and baseline

**Files:**
- Modify: `phpstan.neon.dist`
- Replace: `phpstan-baseline.neon`

- [ ] **Step 1: Update `phpstan.neon.dist`**

Replace the contents of `phpstan.neon.dist` with:

```neon
includes:
    - phpstan-baseline.neon

parameters:
    level: 4
    paths:
        - src
        - config
        - database
    tmpDir: build/phpstan
    checkOctaneCompatibility: true
    checkModelProperties: true
```

Notable diffs:

- Removed `checkMissingIterableValueType: false` — that key was removed in PHPStan 2 in favor of the strict-rules opt-in. Leaving it in causes a parse error.

- [ ] **Step 2: Delete the old baseline**

Run: `rm phpstan-baseline.neon`

- [ ] **Step 3: Try running PHPStan without a baseline**

Edit `phpstan.neon.dist` and temporarily comment out the `includes:` block:

```neon
# includes:
#     - phpstan-baseline.neon

parameters:
    level: 4
    ...
```

Run: `vendor/bin/phpstan analyse --no-progress`

Expected: either passes cleanly, or reports issues.

- [ ] **Step 4: Decide on baseline**

If the analyser passes cleanly:

- Leave the `includes:` block commented out (or remove those two lines entirely).
- Skip step 5.

If the analyser reports issues:

- Restore the `includes:` block in `phpstan.neon.dist`.
- Proceed to step 5.

- [ ] **Step 5: Regenerate the baseline (only if step 4 said there are issues)**

Run: `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon --allow-empty-baseline`

Expected: a new `phpstan-baseline.neon` file is written.

Then run: `vendor/bin/phpstan analyse --no-progress`

Expected: PASS (all reported issues are now in the baseline).

- [ ] **Step 6: Commit**

If a baseline was regenerated:

```bash
git add phpstan.neon.dist phpstan-baseline.neon
git commit -m "Refresh PHPStan config and regenerate baseline for Larastan 3

Drop checkMissingIterableValueType (removed in PHPStan 2). Regenerate
the baseline against the new toolchain."
```

If no baseline was needed:

```bash
git add phpstan.neon.dist
git rm phpstan-baseline.neon
git commit -m "Refresh PHPStan config; baseline no longer needed

Drop checkMissingIterableValueType (removed in PHPStan 2). Analyser
passes cleanly against the new toolchain, so the baseline is gone."
```

---

## Task 7: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Clean install**

Run:

```bash
rm -rf vendor composer.lock
composer install --no-interaction --prefer-dist
```

Expected: clean install.

- [ ] **Step 2: Full test run**

Run: `vendor/bin/pest`

Expected: all tests pass, no risky-test failures, no deprecation warnings causing failures.

- [ ] **Step 3: Static analysis**

Run: `vendor/bin/phpstan analyse --no-progress`

Expected: PASS.

- [ ] **Step 4: Commit the regenerated lockfile if it changed**

Run: `git status composer.lock`

If `composer.lock` is dirty after the clean install:

```bash
git add composer.lock
git commit -m "Refresh composer.lock after full reinstall"
```

If not, skip this commit.

- [ ] **Step 5: Push the branch**

Note from session: `git push` to the empire-medical remote was previously denied for this user. Confirm push permissions before running, or push to a fork.

Run: `git push -u origin empire-medical-customizations-l12-l13`

Expected: branch is pushed. If denied, pause and ask.

---

## Out of scope (do not do)

- Do not touch `src/**`. The branch's existing source-level customizations stand.
- Do not modify the `lockWatchers` table or model, even though `requestLock()` still uses `user_type`. That's a separately tracked follow-up.
- Do not update the README. Documentation refresh is out of scope.
- Do not touch `dependabot.yml` or other workflow files (`phpstan.yml`, `fix-php-code-style-issues.yml`, `dependency-review.yml`, `devskim.yml`, `enlightn.yml`, `dependabot-auto-merge.yml`, `update-changelog.yml`) unless one of them breaks against the new toolchain.
