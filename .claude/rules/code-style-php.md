---
name: code-style-php
description: PHP and Laravel coding style standards for this project
---

# PHP & Laravel Code Style

- **PHP Version**: PHP 8.2+ with strict typing where appropriate (`declare(strict_types=1);`).
- **Formatting**: Laravel Pint (`./vendor/bin/pint --dirty`) is enforced. Adhere to PSR-12 and Laravel presets.
- **Modern PHP Features**: Use constructor property promotion, match expressions, nullsafe operators, typed properties, and explicit return types.
- **Naming Conventions**:
  - Controllers: PascalCase ending with `Controller` (e.g., `InstallmentController`).
  - Models: PascalCase singular (e.g., `Installment`, `Partner`).
  - Migrations: `YYYY_MM_DD_HHMMSS_create_table_name_table.php` (snake_case plural table names).
  - Methods: camelCase (e.g., `calculateSchedule`).
  - Variables: camelCase (e.g., `$loanAmount`).
- **Architecture**: Keep controllers thin. Extract domain logic into actions or service classes. Use Form Requests for request validation.
