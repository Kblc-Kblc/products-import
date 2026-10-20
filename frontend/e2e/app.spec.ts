import { Page, expect, test } from '@playwright/test';
import path from 'node:path';

const EMAIL = process.env['E2E_EMAIL'] ?? 'admin@example.com';
const PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin123';
const XLSX = path.resolve(__dirname, '../../import example (2) Фул.xlsx');

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(EMAIL);
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page.getByRole('button', { name: 'Выйти' })).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('guest is redirected to login and wrong password is rejected', async ({ page }) => {
  await page.goto('/products');
  await expect(page).toHaveURL(/\/login\?returnUrl=%2Fproducts/);

  await page.getByLabel('Email').fill(EMAIL);
  await page.getByLabel('Пароль').fill('wrong-password');
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page.getByRole('alert')).toHaveText('Неверный email или пароль');
});

test('import: upload xlsx, see progress finish and error report', async ({ page }) => {
  await login(page);
  await page.getByRole('link', { name: 'Импорт' }).click();

  await page.locator('input[type=file]').setInputFiles(XLSX);
  await page.getByRole('button', { name: 'Импортировать' }).click();

  await expect(page.getByText('завершён', { exact: true })).toBeVisible({ timeout: 45_000 });
  await expect(page.getByText(/Обработано 40 из 40/)).toBeVisible();
  // The sample file has a few dead image links; they are reported, not fatal
  await expect(page.getByRole('heading', { name: 'Отчёт об ошибках' })).toBeVisible();
});

test('list: server pagination and filters', async ({ page }) => {
  await login(page);
  await expect(page).toHaveURL(/\/products$/);
  const items = page.locator('mat-nav-list a');
  await expect(items).toHaveCount(20);

  await page.getByRole('button', { name: 'Next page' }).click();
  await expect(page).toHaveURL(/page=2/);
  await expect(items).toHaveCount(20);

  await page.getByLabel('Название').fill('бермуды');
  await page.getByLabel('Цена от, ₽').fill('1000');
  await page.getByRole('button', { name: 'Найти' }).click();
  await expect(page).toHaveURL(/name=/);
  await expect(page).not.toHaveURL(/page=/);
  await expect(items).toHaveCount(1);
  await expect(items.first()).toContainText('Бермуды мужские, Grigio/Verde');
});

test('card: fields, attributes and images', async ({ page }) => {
  await login(page);
  await page.getByLabel('Название').fill('Grigio/Verde');
  await page.getByRole('button', { name: 'Найти' }).click();
  await page.locator('mat-nav-list a').first().click();

  await expect(page).toHaveURL(/\/products\/\d+$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Бермуды мужские, Grigio/Verde');
  await expect(page.getByText('33.33%')).toBeVisible();
  await expect(page.getByText('Бренд')).toBeVisible();
  await expect(page.getByText('OMSA', { exact: true })).toBeVisible();
  const image = page.getByRole('region', { name: 'Изображения' }).locator('img').first();
  await expect(image).toBeVisible();
  await expect.poll(() => image.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
});

test('unknown product shows not found', async ({ page }) => {
  await login(page);
  await page.goto('/products/999999');
  await expect(page.getByRole('alert')).toHaveText('Товар не найден');
});
