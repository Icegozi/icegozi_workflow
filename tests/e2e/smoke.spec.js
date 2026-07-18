import { expect, test } from '@playwright/test';

test('guest can open the landing page and navigate to login', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: /trực quan hóa luồng công việc/i })).toBeVisible();
    await page.getByRole('link', { name: 'Đăng nhập' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Đăng nhập để bắt đầu phiên làm việc')).toBeVisible();
    await expect(page.getByPlaceholder('Email hoặc tên đăng nhập')).toBeVisible();
    await expect(page.getByPlaceholder('Mật khẩu')).toBeVisible();
});
