import { expect, test } from '@playwright/test';

const appName = process.env.PLAYWRIGHT_APP_NAME || process.env.VITE_APP_NAME || 'Ic_go-wf';

test('guest can open the landing page and navigate to login', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(appName);
    await expect(page.getByRole('heading', { name: /biến kế hoạch thành/i })).toBeVisible();
    await expect(page.getByTitle(/chuyển chế độ/i)).toHaveCount(0);
    await expect(page.getByRole('button', { name: /tạo board đầu tiên/i })).toBeVisible();
    await expect(page.getByLabel('Minh họa board công việc')).toBeVisible();
    await expect(page.getByRole('banner').getByRole('link', { name: 'Cách hoạt động' })).toHaveCount(1);
    await page.getByRole('banner').getByRole('link', { name: 'Đăng nhập' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Đăng nhập để bắt đầu phiên làm việc')).toBeVisible();
    await expect(page.getByPlaceholder('Email hoặc tên đăng nhập')).toBeVisible();
    await expect(page.getByPlaceholder('Mật khẩu')).toBeVisible();
});

test('guest landing page remains within the mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');

    await expect(page.getByLabel('Minh họa board công việc')).toBeVisible();
    await expect(page.locator('.lp-mini-column--fade')).toBeHidden();
    expect(await page.locator('html').evaluate(
        (element) => element.scrollWidth <= window.innerWidth
    )).toBe(true);
    expect(await page.locator('html').evaluate(
        (element) => getComputedStyle(element).overflowY
    )).toBe('auto');
    expect(await page.locator('body').evaluate(
        (element) => getComputedStyle(element).overflowY
    )).toBe('visible');
});

test('landing signup form carries the email to registration', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: /những thay đổi nhỏ/i })).toBeVisible();
    await expect(page.getByText(/ưu đãi khởi động/i)).toBeVisible();
    await page.getByLabel('Email công việc').fill('team@example.com');
    await page.getByRole('button', { name: /tạo tài khoản/i }).click();

    await expect(page).toHaveURL(/\/register\?email=team%40example\.com$/);
    await expect(page.getByPlaceholder('Email')).toHaveValue('team@example.com');
});
