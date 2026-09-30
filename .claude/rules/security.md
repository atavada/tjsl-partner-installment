---
name: security
description: Security guidelines and data protection rules
---

# Security Rules

- **Mass Assignment**: Always define `$fillable` on Eloquent models. Never use `$guarded = []` on models handling user input.
- **Authorization**: Enforce authorization using Laravel Policies and Gates (`$this->authorize(...)` or `Gate::authorize(...)`).
- **Secrets & Credentials**:
  - Never commit `.env` or hardcode credentials, API keys, or tokens.
  - Mask sensitive data in logs and never pass unneeded sensitive fields to frontend Inertia props.
- **Sanitization & Escaping**: Rely on Blade and React's automatic escaping. Avoid raw HTML or dangerouslySetInnerHTML.
