# Contributing to Numen

Thanks for taking the time to contribute. This doc covers everything you need to go from zero to pull request.

---

## First-Time Contributors

Never contributed to an open source project before? Numen is a good place to start. The codebase is a standard Laravel 12 app — if you've written Laravel before, you'll feel at home immediately.

Look for issues labeled [`good first issue`](https://github.com/byte5labs/numen/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22) — these are scoped to be approachable without deep knowledge of the pipeline internals.

When in doubt, open a discussion or comment on an issue before writing code. Saves everyone time.

---

## Local Development Setup

### Prerequisites

- PHP 8.4+ with extensions: `pdo_sqlite`, `redis` (or use `QUEUE_CONNECTION=database`)
- Composer 2.x
- Node.js 18+ and npm
- Redis (optional — can use database queue driver for dev)
- An Anthropic API key (minimum), or OpenAI / Azure

### Setup

```bash
# Fork the repo on GitHub, then clone your fork
git clone https://github.com/YOUR_USERNAME/numen.git
cd numen

# Add the upstream remote
git remote add upstream https://github.com/byte5labs/numen.git

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Configure
cp .env.example .env
php artisan key:generate
```

Edit `.env` — at minimum set your API key:

```env
ANTHROPIC_API_KEY=sk-ant-your-key-here

# If you don't have Redis, use database queue:
QUEUE_CONNECTION=database
```

```bash
# Database setup
touch database/database.sqlite
php artisan migrate

# If using database queue:
php artisan queue:table
php artisan migrate

# Seed demo data
php artisan db:seed --class=DemoSeeder

# Run the app (three terminals)
php artisan serve
php artisan queue:work
npm run dev
```

Visit `http://localhost:8000`. You're in.

### Running Tests

```bash
php artisan test
```

Or with coverage (requires Xdebug or PCOV):

```bash
php artisan test --coverage
```

For a specific test file:

```bash
php artisan test tests/Feature/ContentApiTest.php
```

### Code Style

We use [Laravel Pint](https://laravel.com/docs/pint) for code formatting. It's already in `composer.json`.

```bash
# Check for style issues
./vendor/bin/pint --test

# Fix all issues
./vendor/bin/pint
```

Pint runs against the `laravel` preset. Don't submit PRs with Pint failures — CI will catch them.

---

## Pull Request Process

### 1. Fork and Branch

```bash
# Make sure your main is up to date
git checkout main
git pull upstream main

# Create a feature branch
git checkout -b feat/your-feature-name

# Or for bug fixes:
git checkout -b fix/what-you-are-fixing
```

Branch naming convention:
- `feat/` — new features
- `fix/` — bug fixes
- `docs/` — documentation only
- `refactor/` — code changes without behavior change
- `test/` — test additions or fixes
- `chore/` — dependency updates, CI config

### 2. Write Your Code

A few guidelines:

- **Tests for new behavior.** If you add a new pipeline stage type, new API endpoint, or provider — add a test.
- **One thing per PR.** Focused PRs get reviewed faster. Don't bundle unrelated changes.
- **Don't break the API surface.** The `/api/v1/*` routes and `ContentResource` response shape are public contracts. Changes that break these need a deprecation path and a major version bump.
- **PHPDoc for public methods.** Especially on `Agent`, `LLMProvider`, and anything in `app/Services/AI/`.
- **Use the LLM layer, not direct HTTP.** AI calls go through `LLMManager` (or the `Agent` base class). Don't instantiate providers directly.

### 3. Before You Push

```bash
# Run tests
php artisan test

# Fix code style
./vendor/bin/pint

# Make sure nothing is obviously broken
php artisan route:list
php artisan config:clear
```

### 4. Open the PR

Push your branch and open a pull request against `main` on the upstream repo.

PR description should include:
- **What** — what does this change do?
- **Why** — why is this needed?
- **How to test** — steps to verify the change works
- **Breaking changes** — if any, call them out explicitly

Small PRs are reviewed faster. If your change is large, open a draft PR early and ask for feedback on the approach before writing all the code.

---

## Issue Reporting

### Bug Reports

Include:
- PHP version (`php --version`)
- Laravel version (`php artisan --version`)
- Which AI provider you're using
- Steps to reproduce (minimal)
- Expected vs actual behavior
- Relevant log output (check `storage/logs/laravel.log`)

### Feature Requests

Open an issue and describe:
- What problem you're trying to solve
- How you'd expect the feature to work
- Any alternatives you've considered

If it's a large feature, consider opening a discussion first.

---

## Code of Conduct

We follow the [Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/). Short version: be respectful, be constructive, assume good intent.

Issues and PRs where people are dismissive or hostile get closed. This is a small project; we can afford to be strict about this.

---

## Project Structure Reference

```
app/
├── Agents/            # AI agents — extend Agent to add new personas
├── Pipelines/         # Pipeline execution engine
├── Services/AI/       # LLM layer: LLMManager, providers, cost tracking
├── Models/            # 16 Eloquent models
├── Jobs/              # Queue jobs (one per pipeline stage)
├── Events/            # Pipeline and content lifecycle events
└── Http/Controllers/
    ├── Api/           # Public + authenticated REST API
    └── Admin/         # Inertia.js admin controllers
config/
└── numen.php         # All Numen config — start here for any AI behavior
database/
├── migrations/        # Schema
└── seeders/           # DemoSeeder for local dev
tests/
├── Feature/           # HTTP + integration tests
└── Unit/              # Unit tests for services, agents
```

### Adding a New AI Agent Type

1. Create `app/Agents/Types/YourAgent.php` extending `Agent`
2. Implement `execute(AgentTask $task): AgentResult`
3. Register in `AgentFactory` (add a new `match` arm)
4. Add a pipeline stage that references your agent type
5. Write a test

### Adding a New LLM Provider

1. Create `app/Services/AI/Providers/YourProvider.php` implementing `LLMProvider`
2. Bind it in `AppServiceProvider`
3. Register it in `LLMManager`'s provider map
4. Add env vars + config keys in `config/numen.php`
5. Update `.env.example`

---

## Questions?

Open an issue tagged `question`, or start a GitHub Discussion. We're a small team — response times vary, but we read everything.
