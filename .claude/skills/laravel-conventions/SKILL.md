---
name: laravel-conventions
description: How backend code is written in FugaziByUs – controllers, Form Requests, models, migrations, toasts and routes. Use when adding or changing any PHP in app/, routes/ or database/.
---

# Laravel conventions

## Request → response

- **Routes** live in `routes/web.php`. Name every route (`->name('contact.store')`). Group authenticated routes under `middleware('auth')`. Rate-limit public POST routes (see the `contact` limiter in `FortifyServiceProvider`).
- **Controllers** stay thin: validate with a Form Request, call the model or a small action, flash a toast, then redirect (POST/PATCH/PUT) or render an Inertia page (GET).
- **Form Requests** (`app/Http/Requests`) hold all validation. Normalise input in `prepareForValidation()` (trim strings, lowercase emails). Reuse rules from `app/Concerns/*ValidationRules.php`. Add friendly `messages()` for any rule a non-technical user might trip over.
- **Toasts:** `Inertia::flash('toast', ['type' => 'success'|'info'|'warning'|'error', 'message' => __('…')])`, then `return to_route(...)`. Don't toast validation errors; the frontend does that automatically.
- **Inertia pages:** `Inertia::render('PageName', [...props])`. Use `Inertia::defer(fn () => ...)` for slow or optional data.

## Models & data

- Use PHP attributes for mass assignment and hidden fields: `#[Fillable([...])]`, `#[Hidden([...])]`, `#[Appends([...])]`.
- Add a `@property` docblock for every column (PHPStan relies on it).
- Use a factory for every model (`database/factories`). Tests build data through factories.
- Migrations: one per change, with a descriptive name, `down()` that reverses `up()`, foreign keys with explicit `nullOnDelete()`/`cascadeOnDelete()`, and indexes on columns you filter or sort by. **Never edit a migration that has already been merged**; add a new one.

## Auth (Fortify)

- Login and registration happen in modals. `/login` and `/register` redirect to `/?auth=login|register`.
- Post-auth redirects and toasts come from `app/Http/Responses/*Response.php`.
- Password strength comes from `Password::defaults()` in `AppServiceProvider`, so every password field uses `$this->passwordRules()`.

## Style

- PSR-12 via Pint (`composer lint`). Typed parameters and return types everywhere. PHPStan must pass (`composer types:check`).
- Prefer clear names over comments. Comment only the _why_ of something unusual.
