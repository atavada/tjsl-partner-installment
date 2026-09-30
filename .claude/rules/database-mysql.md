---
name: database-mysql
description: MySQL 8 database schema, migrations, and query conventions
---

# MySQL 8 Database Conventions

- **Database Engine**: MySQL 8.x with `utf8mb4_unicode_ci` collation.
- **Migrations**:
  - Always use foreignId/constrained for foreign keys with explicit cascade or restrict behavior.
  - Index columns frequently queried, filtered, or joined.
  - Never write destructive migrations (`migrate:fresh` is strictly barred in shared/production environments).
- **Eloquent & Queries**:
  - Eager load relationships (`with([...])`) to prevent N+1 queries.
  - Wrap multi-table modifications inside `DB::transaction(fn () => ...)`.
  - Use Eloquent scopes for common query filters.
- **Testing**: Tests run against a dedicated MySQL test database (`partner_program_installment_app_test`).
