import { expect, test } from '@playwright/test';

// Each run uses a fresh email so tests never collide.
const unique = () =>
    `e2e.${Date.now()}.${Math.floor(Math.random() * 1e6)}@fugazibyus.test`;
const PASSWORD = 'E2e-Smoke-Test-1!';

test('homepage shows the hero and the AI stats widget', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toContainText(
        'builds itself',
    );
    await expect(page.getByTestId('ai-stats-widget')).toBeVisible();
    await expect(page.getByText('Spent on AI')).toBeVisible();
});

test('a visitor can register, set their name and log out', async ({ page }) => {
    await page.goto('/?auth=register');

    const modal = page.getByTestId('auth-modal');
    await expect(modal).toBeVisible();
    await modal.getByLabel('Email address').fill(unique());
    await modal.getByLabel('Password', { exact: true }).fill(PASSWORD);
    await modal.getByLabel('Confirm password').fill(PASSWORD);
    await modal.getByRole('button', { name: 'Create account' }).click();

    await expect(page).toHaveURL(/\/account$/);
    await expect(page.getByText('Your account is ready')).toBeVisible();

    await page.getByLabel('First name').fill('Eve');
    await page.getByLabel('Last name').fill('Tester');
    await page.getByTestId('save-profile').click();
    await expect(page.getByText('Your details have been saved.')).toBeVisible();

    await page.getByTestId('account-logout').click();
    await expect(page.getByText('You have been logged out.')).toBeVisible();
});

test('wrong login details show an error', async ({ page }) => {
    await page.goto('/?auth=login');
    const modal = page.getByTestId('auth-modal');
    await modal.getByLabel('Email address').fill('nobody@fugazibyus.test');
    await modal
        .getByLabel('Password', { exact: true })
        .fill('Not-The-Password-1!');
    await modal.getByTestId('login-button').click();
    await expect(
        modal.getByText('These credentials do not match our records.'),
    ).toBeVisible();
});

test('the contact page shows its heading and title', async ({ page }) => {
    await page.goto('/contact');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        'Get in touch with us',
    );
    await expect(page).toHaveTitle(/^Contact - /);
});

test('the contact form sends a message', async ({ page }) => {
    await page.goto('/contact');
    await page.getByLabel('Your name').fill('Smoke Test');
    await page.getByLabel('Email address').fill(unique());
    await page.getByLabel('Subject').fill('Smoke test');
    await page.getByLabel('Message').fill('Automated smoke test from CI.');
    await page.getByTestId('contact-submit').click();
    await expect(page.getByText('Your message has been sent')).toBeVisible();
});

test('the contact form marks its required fields', async ({ page }) => {
    await page.goto('/contact');
    await expect(page.getByTestId('required-fields-note')).toHaveText(
        '* Required fields',
    );

    for (const label of ['Your name', 'Email address', 'Subject', 'Message']) {
        // The asterisk is hidden from screen readers, so the accessible name stays exact.
        const field = page.getByRole('textbox', { name: label, exact: true });
        await expect(field).toHaveAttribute('required');
        await expect(
            page.locator(`label[for="${await field.getAttribute('id')}"]`),
        ).toContainText('*');
    }

    await expect(page.locator('label[for="website"]')).not.toContainText('*');
    await expect(page.locator('form').getByTestId('required-mark')).toHaveCount(
        5,
    );

    // The note sits below the fields, on the submit button's row, to its left,
    // and the button stays on the right.
    const form = await page.locator('form').boundingBox();
    const note = await page.getByTestId('required-fields-note').boundingBox();
    const submit = await page.getByTestId('contact-submit').boundingBox();
    const message = await page.getByLabel('Message').boundingBox();
    expect(note!.y).toBeGreaterThan(message!.y + message!.height);
    expect(note!.y + note!.height / 2).toBeGreaterThan(submit!.y);
    expect(note!.y + note!.height / 2).toBeLessThan(submit!.y + submit!.height);
    expect(note!.x).toBeCloseTo(form!.x, 0);
    expect(note!.x + note!.width).toBeLessThan(submit!.x);
    expect(submit!.x + submit!.width).toBeCloseTo(form!.x + form!.width, 0);
});

test('contact FAQ answers open and close by mouse and keyboard', async ({
    page,
}) => {
    await page.goto('/contact');
    const faq = page.getByTestId('contact-faq');
    const questions = faq.getByRole('button');
    await expect(questions).toHaveCount(4);
    for (const question of await questions.all()) {
        await expect(question).toHaveAttribute('aria-expanded', 'false');
    }

    const question = faq.getByRole('button', { name: 'Who reads my message?' });
    const answer = faq.getByText('Real people on the FugaziByUs team.');
    await question.click();
    await expect(question).toHaveAttribute('aria-expanded', 'true');
    await expect(answer).toBeVisible();
    await question.click();
    await expect(question).toHaveAttribute('aria-expanded', 'false');
    await expect(answer).toBeHidden();

    await question.focus();
    await page.keyboard.press('Enter');
    await expect(answer).toBeVisible();
    await page.keyboard.press('Space');
    await expect(answer).toBeHidden();
});
