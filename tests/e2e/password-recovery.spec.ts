import { expect, test } from './support/test';

test.describe('password recovery', () => {
    test('exposes the complete neutral recovery initiation flow in both locales', async ({ page }) => {
        await page.goto('/login');
        await page.getByRole('link', { name: 'Nie pamiętasz hasła?' }).click();

        await expect(page).toHaveURL('/forgot-password');
        await expect(page.getByRole('heading', { level: 2, name: 'Odzyskaj dostęp do konta' })).toBeVisible();
        await page.getByLabel('Adres e-mail').fill('missing-recovery@example.test');
        await page.getByRole('button', { name: 'Wyślij instrukcję' }).click();
        await expect(page.getByText(/Jeśli konto może otrzymać wiadomość/)).toBeVisible();

        await page.getByRole('button', { name: 'Zmień język' }).click();
        await expect(page.getByRole('heading', { level: 2, name: 'Recover account access' })).toBeVisible();
        await expect(page.getByText('The response will not reveal whether the account exists.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Send instructions' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Back to login' })).toBeVisible();
    });
});
