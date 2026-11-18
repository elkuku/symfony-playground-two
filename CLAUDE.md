# CLAUDE.md

@AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

An opinionated Symfony 8.x project template for user management with social OAuth login (Google, GitHub, GitLab). In `dev` it uses a form-based login; in `prod` it uses social login only.

## Commands

### Setup
```bash
bin/install   # Full setup: Docker, migrations, fixtures
bin/start     # Start dev environment (Docker + Symfony server)
bin/stop      # Stop Docker containers
```

`bin/install`, `bin/start`, `bin/stop`, `bin/deploy` and `bin/functions.sh` are installed by the `elkuku/symfony-utils` Flex recipe (custom endpoint `elkuku/recipes`, configured in `composer.json`). Change them in `elkuku/symfony-utils` (`resources/bin/`), not here. `bin/deploy` runs on the server; `PHP_BIN`/`COMPOSER_BIN` override the PHP and Composer binaries.

### Testing & Quality (run via `symfony php` / `symfony console` to pick up correct PHP)
```bash
make tests                              # Full test suite: PHPUnit + PHPStan + Rector (dry-run) + PHP-CS-Fixer
vendor/bin/phpunit --testdox tests/Path/To/SomeTest.php  # Single test file
vendor/bin/phpstan --memory-limit=256M  # Static analysis (level 8+)
vendor/bin/rector process --dry-run     # Check modernization suggestions
vendor/bin/php-cs-fixer check --diff    # Check code style
vendor/bin/php-cs-fixer fix             # Auto-fix code style
```

`make tests` drops and recreates the test database before running PHPUnit (uses `APP_ENV=test`). Run it after every code change; running PHPUnit alone against a reused test database gives false failures.

### Maintenance
```bash
composer check       # Check outdated assets, PHP deps, and recipes
composer db-diagram  # Regenerate assets/docs/database.svg
symfony console user-admin  # Interactive user management CLI
```

## Architecture

### Entities & Enums
- **`User`** (`user` table, always quoted in raw SQL because `USER` is reserved in PostgreSQL) — single entity, implements `UserInterface`. Uses `identifier` (unique string) as the username. OAuth IDs (`googleId`, `gitHubId`, `gitLabId`) are nullable. Role is stored via `UserRole` enum.
- **`UserRole`** enum — `ROLE_USER` (default) and `ROLE_ADMIN`.

### Controllers
Single-action controller pattern under `src/Controller/`:
- `User/` — CRUD (Index, Create, Read, Update, Delete), each in its own class
- `Security/` — OAuth authenticators (Google, GitHub, GitLab) plus `LoginFormController` and `Logout`
- `System/` — `AboutController`, `LogviewController`
- `ProfileController`, `DefaultController`

Access control uses `#[IsGranted()]` attributes.

### Security
Multi-authenticator: form login (dev only) + OAuth2 via `knpuniversity/oauth2-client-bundle`. Remember-me is enabled.

There are intentionally two Google logins (`GoogleAuthenticator` = OAuth redirect, `GoogleIdentityAuthenticator` = Sign in with Google). Projects built from the template remove one; see `doc/google-login.md`. Don't remove either in the template itself.

### Frontend
Asset Mapper (no Webpack), Bootstrap, Stimulus, Twig Components, UX Icons.

### Database
PostgreSQL via Docker Compose. Doctrine ORM with migrations. Fixtures in `src/DataFixtures/` create one regular user and one admin.

### Custom Bundle
`KuKuSFSystemToolsBundle` — provides system utility endpoints (about, log viewer).

## Environment Variables
OAuth credentials go in `.env.local` (gitignored):
- `OAUTH_GOOGLE_ID`, `OAUTH_GOOGLE_SECRET`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_API_KEY`, `GOOGLE_AUTH_CONFIG`
- `OAUTH_GITHUB_CLIENT_ID`, `OAUTH_GITHUB_CLIENT_SECRET`
- `OAUTH_GITLAB_CLIENT_ID`, `OAUTH_GITLAB_CLIENT_SECRET`
