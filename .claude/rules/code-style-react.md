---
name: code-style-react
description: React, TypeScript, and Inertia code style standards
---

# React & TypeScript Code Style

- **Stack**: React 19 + TypeScript 5.7+ + Inertia.js v2 + Tailwind CSS v4.
- **Components**: Functional components with explicit prop types. PascalCase filenames and component names.
- **Inertia Usage**:
  - Navigation: Use `<Link href={...}>` from `@inertiajs/react`.
  - Forms: Use `useForm` hook with typed state.
  - Page Props: Strongly type page props using TypeScript interfaces or types.
- **UI Components**: Reuse components in `resources/js/components/ui/` (Radix UI + Tailwind + Lucide icons).
- **Styling**: Use utility-first Tailwind classes. Utilize `cn()` helper from `resources/js/lib/utils` for conditional classes.
- **Linting & Formatting**: Follow ESLint and Prettier configs (`npm run lint`, `npm run format`).
