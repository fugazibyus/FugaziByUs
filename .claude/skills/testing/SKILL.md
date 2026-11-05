---
name: testing
description: How to test FugaziByUs changes – PHPUnit feature tests, factories, Inertia assertions, Playwright smoke tests, and the full pre-push check. Use whenever you write or change code, before every commit and push.
---

# Testing

## What to write

- **Every behaviour change gets a PHPUnit feature test** in `tests/Feature/<Area>Test.php`. Name tests as sentences: `test_users_can_change_their_email_with_their_password()`.
- **Bug fix:** write a failing test that reproduces the bug _first_, then fix it.
- Cover the happy path, validation failures (`assertSessionHasErrors('field')`), authorization (guests and other users), and rate limits where relevant.
- Build data through factories (`User::factory()->create()`). The factory password is `Fugazi-Test-123!`.

## Useful assertions

```php
$this->actingAs($user)->patch(route('account.profile.update'), [...])
    ->assertSessionHasNoErrors()
    ->assertRedirect(route('account.show'))
    ->assertInertiaFlash('toast.type', 'success');

$this->get(route('home'))->assertInertia(fn (Assert $page) => $page
    ->component('Home')
    ->loadDeferredProps(fn (Assert $reload) => $reload->has('aiStats')));
```

## Browser smoke tests

`tests/e2e/*.spec.ts` (Playwright) cover the key journeys on desktop and mobile. Add or extend a spec when you change one of those journeys. Find elements by role, label or `data-test` (`page.getByTestId('contact-submit')`).

## Before every push

Run the full check (see the Commands table in CLAUDE.md) and fix everything it reports. Never push red, and never weaken or delete a test to make it pass. If a test is genuinely wrong, explain why in the PR.

Safety: tests only run against in-memory SQLite or a MySQL database whose name ends in `_test`. `tests/TestCase.php` refuses anything else.
