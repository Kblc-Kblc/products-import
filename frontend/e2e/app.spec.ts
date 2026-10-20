import { Page, expect, test } from '@playwright/test';
import path from 'node:path';

const EMAIL = process.env['E2E_EMAIL'] ?? 'admin@example.com';
const PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin123';
const XLSX = path.resolve(__dirname, '../../import example (2) Фул.xlsx');

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(EMAIL);
  await page.getByLabel('Пароль', { exact: true }).fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page.getByRole('button', { name: 'Выйти' })).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('guest is redirected to login and wrong password is rejected', async ({ page }) => {
  await page.goto('/products');
  await expect(page).toHaveURL(/\/login\?returnUrl=%2Fproducts/);

  await page.getByLabel('Email').fill(EMAIL);
  await page.getByLabel('Пароль', { exact: true }).fill('wrong-password');
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page.getByRole('alert')).toContainText('Неверный email или пароль');
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
  const items = page.locator('a.product-row');
  await expect(items).toHaveCount(20);

  await page.getByRole('button', { name: 'Следующая страница' }).click();
  await expect(page).toHaveURL(/page=2/);
  await expect(items).toHaveCount(20);
  await expect(page.getByText('21 – 40 из 40')).toBeVisible();

  // "К списку" on the card returns to the same page
  await items.first().click();
  await page.getByRole('link', { name: 'К списку' }).click();
  await expect(page).toHaveURL(/\/products\?page=2$/);

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
  await page.locator('a.product-row').first().click();

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

test('import progress is visible in the header on other pages, then a toast links to the report', async ({ page }) => {
  // Hold the job in "processing" for the first polls so the header bar can be observed deterministically
  let polls = 0;
  await page.route(/\/api\/imports\/\d+$/, async (route) => {
    const response = await route.fetch();
    const job = await response.json();
    if (++polls <= 3) Object.assign(job, { status: 'processing', processed: 10, total: 40 });
    await route.fulfill({ response, json: job });
  });

  await login(page);
  await page.getByRole('link', { name: 'Импорт' }).click();
  await page.locator('input[type=file]').setInputFiles(XLSX);
  await page.getByRole('button', { name: 'Импортировать' }).click();
  await expect(page.getByText('выполняется', { exact: true })).toBeVisible();

  // Go to a product card: the header keeps showing the running import
  await page.goto('/products/1');
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  const headerProgress = page.getByRole('status').filter({ hasText: 'Подробнее' });
  await expect(headerProgress).toContainText('10 из 40');

  // When it finishes: header bar disappears, toast offers the report
  await expect(page.getByText(/Импорт завершён: создано \d+, обновлено \d+, ошибок \d+/)).toBeVisible({ timeout: 45_000 });
  await expect(headerProgress).toBeHidden();
  await page.getByRole('button', { name: 'Отчёт' }).click();
  await expect(page).toHaveURL(/\/import$/);
  await expect(page.getByRole('heading', { name: 'Отчёт об ошибках' })).toBeVisible();
});

test('network failure shows a toast instead of failing silently', async ({ page }) => {
  await login(page);
  await page.route('**/api/products?**', (route) => route.abort('internetdisconnected'));
  await page.getByLabel('Название').fill('что угодно');
  await page.getByRole('button', { name: 'Найти' }).click();

  await expect(page.getByText('Нет связи с сервером. Проверьте подключение к сети.')).toBeVisible();
  await expect(page.getByRole('alert')).toContainText('Не удалось загрузить товары');
});
