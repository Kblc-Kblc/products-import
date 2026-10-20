import { expect, test as setup } from '@playwright/test';
import { EMAIL, PASSWORD } from './credentials';

// Log in once and reuse the session: logging in per test trips the login rate limit (10/min per IP)
setup('authenticate', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill(EMAIL);
  await page.getByLabel('Пароль', { exact: true }).fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page.getByRole('button', { name: 'Выйти' })).toBeVisible();
  await page.context().storageState({ path: 'e2e/.auth/admin.json' });
});
