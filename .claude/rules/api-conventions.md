---
name: api-conventions
description: HTTP routing and API conventions for Inertia and endpoints
---

# API & Routing Conventions

- **Routing**: Group routes logically in `routes/web.php` with named routes (e.g., `installments.index`, `installments.store`).
- **Ziggy**: Use `route('route.name')` helper in frontend for URL generation.
- **Responses**:
  - Inertia responses: `Inertia::render('Path/To/Page', [...props])`.
  - Redirects: Use `redirect()->route('...')->with('success', '...')` for stateful updates.
  - API / JSON endpoints: Return API Resources (`JsonResource`) with standard HTTP status codes (200, 201, 400, 401, 403, 404, 422).
- **Validation**: Always validate using Form Requests (`php artisan make:request ...`). Never validate raw input inside controller actions.
