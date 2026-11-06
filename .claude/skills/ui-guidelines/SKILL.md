---
name: ui-guidelines
description: The FugaziByUs design system and frontend conventions – layout, colours, components, forms, toasts, accessibility and dark mode. Use when creating or changing any Vue page, component or styling.
---

# UI guidelines

## Look & feel

- Clean and airy: generous whitespace, `rounded-2xl` cards with a `border` and `bg-card`, at most one accent colour (the brand violet, `text-primary` / `bg-primary`).
- Page width: wrap content in `mx-auto max-w-6xl px-4 sm:px-6` (forms and narrow pages use `max-w-3xl`).
- Headings: `text-4xl font-bold tracking-tight` (page title), `text-2xl/3xl font-bold tracking-tight` (sections), muted helper text with `text-muted-foreground`.
- Hero areas can use the `bg-hero-glow` utility. Don't add new gradients or colours; change tokens in `resources/css/app.css` instead.
- Icons come from `@lucide/vue`, sized `size-4` inline and `size-5` in feature tiles.

## Components

- Use `resources/js/components/ui/*` (shadcn-vue) first: Button, Input, Label, Textarea, Dialog, DropdownMenu, Sheet, Card, Badge, Skeleton, Spinner.
- Shared app components: `InputError`, `PasswordInput`, `UserInfo`, `AppLogo`, `auth/PasswordStrength`.
- Links and URLs always come from Wayfinder: `import { show } from '@/routes/contact'` then `<Link :href="show()">`. Forms use `<Form v-bind="store.form()">`.

## Forms

- `<Form>` from `@inertiajs/vue3` with `v-slot="{ errors, processing }"`. Each field is a `grid gap-2` with a `Label`, the input and `<InputError :message="errors.field">`.
- Disable the submit button while `processing` and show `<Spinner v-if="processing" />`.
- Use `reset-on-success` / `:reset-on-error` for password fields, and `:options="{ preserveScroll: true }"` on long pages.
- Give every interactive element a `data-test="..."` id if a test needs to find it.

## Feedback

- Success and info: the server flashes a toast. Validation, server and network errors are toasted automatically, so don't add extra error toasts.
- Loading: `Skeleton` for deferred data (`<Deferred>`), `Spinner` on buttons.
- Empty states: a short dashed-border box that explains what will appear and why it's empty.

## Accessibility & responsiveness

- Every input has a `<Label for>`. Icon-only buttons get an `aria-label`. Keep visible focus rings (don't remove `outline`).
- Mobile first: check 375px wide. The header collapses into the `Sheet` menu below `md`.
- Light **and** dark mode: use token classes (`bg-background`, `text-foreground`, `border`, `bg-muted`), never raw `white`/`black`/`gray-*`.
