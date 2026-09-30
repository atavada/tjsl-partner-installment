# partner-program-installment-app

Project guidelines and development conventions for Claude Code.

## Tech Stack
- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Inertia.js v2 + React 19 + TypeScript + Tailwind CSS v4
- **Database**: MySQL 8.x (`partner_program_installment_app` / `partner_program_installment_app_test`)
- **Testing**: Pest PHP v3
- **Tooling**: Laravel Boost, Vite, ESLint, Prettier, Pint

## Commands

### Development
- `composer dev` — Run dev server, queue worker, log tailing, and Vite concurrently
- `php artisan serve` — Serve Laravel API/backend
- `npm run dev` — Start Vite dev server

### Testing & Quality
- `php artisan test --compact` — Run test suite with Pest
- `./vendor/bin/pest` — Run Pest directly
- `npm run types` — TypeScript type checking (`tsc --noEmit`)
- `npm run lint` — ESLint verification and fix
- `npm run format` — Prettier formatting
- `./vendor/bin/pint --dirty` — PHP code styling with Laravel Pint
- `npm run build` — Production assets build

### Database
- `php artisan migrate` — Run pending database migrations
- `php artisan db:show` — Inspect database connection and tables

## Architecture & Conventions
- Detailed conventions are maintained in `.claude/rules/`:
  - `code-style-php.md`: PHP strict types, Pint formatting, modern syntax
  - `code-style-react.md`: React 19, TypeScript, Inertia hooks, Tailwind v4
  - `api-conventions.md`: RESTful routes, Form Requests, JSON resources
  - `database-mysql.md`: MySQL 8 standards, foreign keys, indexing
  - `testing.md`: Pest PHP test conventions, RefreshDatabase
  - `security.md`: Mass assignment protection, authorization policies, secret hygiene
- Specialized subagents configured in `.claude/agents/`:
  - `code-reviewer`: Comprehensive full-stack review
  - `migration-reviewer`: Schema and MySQL migration validation
  - `pest-test-writer`: Pest unit and feature tests authoring
  - `inertia-ui-builder`: React 19 + Inertia UI construction
