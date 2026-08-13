import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

async function signIn(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await completeSignIn(page, 'E2E Visibility Team');
}

test('manages private events, reminders, preferences, and all Calendar views', async ({ page }) => {
    await signIn(page);
    await page.goto('/calendar?view=month&date=2026-08-13');

    await expect(page.getByRole('heading', { level: 1, name: /Kalendarz|Calendar/ })).toBeVisible();
    await expect(page.getByTestId('calendar-month-view')).toBeVisible();
    await expect(page.getByText('Weekly portfolio review').first()).toBeVisible();

    await page
        .getByRole('button', { name: /Utwórz wydarzenie|Create event/ })
        .first()
        .click();
    await page.getByLabel(/Tytuł|Title/).fill('E2E private Calendar event');
    await page.getByLabel(/Opis|Description/).fill('Private browser workflow details');
    await page.getByLabel(/Miejsce|Location/).fill('E2E room');
    await page.getByLabel(/Początek|Starts/).fill('2026-08-14');
    await page
        .getByLabel(/Godz.|Hour/)
        .first()
        .fill('11');
    await page.getByLabel(/Koniec|Ends/).fill('2026-08-14');
    await page
        .getByLabel(/Godz.|Hour/)
        .nth(1)
        .fill('12');
    await page.getByRole('button', { name: /Dodaj przypomnienie|Add reminder/ }).click();
    await page
        .getByLabel(/Minuty przed|Minutes before/)
        .last()
        .fill('60');
    await page
        .getByRole('button', { name: /Utwórz wydarzenie|Create event/ })
        .last()
        .click();

    await expect(page.getByText('E2E private Calendar event').first()).toBeVisible();

    for (const view of [
        { button: /^(Tydzień|Week)$/, testId: 'calendar-week-view' },
        { button: /^(Dzień|Day)$/, testId: 'calendar-day-view' },
        { button: /^Agenda$/, testId: 'calendar-agenda-view' },
        { button: /^(Miesiąc|Month)$/, testId: 'calendar-month-view' },
    ]) {
        await page.getByRole('button', { name: view.button, exact: true }).click();
        await expect(page.getByTestId(view.testId)).toBeVisible();
    }

    await page.getByRole('button', { name: /Ustawienia przypomnień|Reminder preferences/ }).click();
    await page.getByLabel(/Domyślne przypomnienie|Default reminder/).fill('30');
    await page.getByRole('button', { name: /Zapisz|Save/ }).click();
    await expect(page.getByText(/Zaktualizowano ustawienia kalendarza|Calendar preferences updated/)).toBeVisible();
});
