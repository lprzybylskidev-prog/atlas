import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

test('creates an in-person Meeting with pending invitation and Calendar presentation', async ({ page }, testInfo) => {
    const title = `E2E in-person planning ${testInfo.project.name}`;
    const location = `Room E2E-${testInfo.project.name}`;

    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await completeSignIn(page, 'E2E Visibility Team');
    await page.waitForLoadState('networkidle');

    await page
        .getByText(/Pulpity|Dashboards/, { exact: true })
        .first()
        .click();
    await page
        .getByRole('link', { name: /Spotkania|Meetings/, exact: true })
        .first()
        .click();
    await expect(page.getByRole('heading', { level: 1, name: /Spotkania|Meetings/ })).toBeVisible();
    await page.getByRole('button', { name: /Zaplanuj spotkanie|Schedule Meeting/ }).click();
    await page.getByLabel(/Tytuł|Title/).fill(title);
    await page.getByRole('combobox', { name: /Tryb spotkania|Meeting mode/ }).click();
    await page.getByRole('option', { name: /Stacjonarne|In person/ }).click();
    await page.getByLabel(/Miejsce fizyczne|Physical location/).fill(location);
    await page.getByLabel(/Początek|Starts at/).fill('2026-10-20');
    await page.getByLabel(/Koniec|Ends at/).fill('2026-10-20');
    await page
        .getByLabel(/Godz.|Hour/)
        .first()
        .fill('09');
    await page
        .getByLabel(/Godz.|Hour/)
        .nth(1)
        .fill('10');
    await page.getByRole('checkbox', { name: /Visibility User/ }).click();
    await page.getByRole('button', { name: /Utwórz spotkanie|Create Meeting/ }).click();

    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    await expect(page.getByText(/Stacjonarne|In person/).first()).toBeVisible();
    await expect(page.getByText(location)).toBeVisible();
    await expect(page.getByText(/Visibility User/)).toBeVisible();
    await expect(page.getByText(/Brak odpowiedzi|No response/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Dołącz online|Join online/ })).toHaveCount(0);

    await page.waitForLoadState('networkidle');
    await page
        .getByRole('link', { name: /Kalendarz|Calendar/, exact: true })
        .first()
        .click();
    await page.getByRole('button', { name: /^Agenda$/ }).click();
    await expect(page.getByTestId('calendar-agenda-view')).toBeVisible();
    await page.getByLabel(/Wybierz datę|Choose date/).fill('2026-10-20');
    await expect(page.getByText(title)).toBeVisible();
    await expect(page.getByText(/Stacjonarne|In person/).first()).toBeVisible();
    await expect(page.getByText(location)).toBeVisible();
});
