---
name: testing
description: Testing standards and practices using Pest PHP
---

# Testing Conventions (Pest PHP)

- **Framework**: Pest PHP v3 with Laravel plugin (`php artisan test --compact` or `./vendor/bin/pest`).
- **Database in Tests**: Use `Illuminate\Foundation\Testing\RefreshDatabase` trait in feature tests.
- **Structure**:
  - Group tests using `describe('feature or unit', function () { ... })`.
  - Use `it('does something specific', function () { ... })`.
  - Use Pest expectations: `expect($value)->toBe(...)`.
- **Coverage**:
  - Feature tests for all HTTP routes, authentication gates, and Inertia page responses.
  - Unit tests for complex financial calculations (e.g., installment schedules, interest, penalties).
