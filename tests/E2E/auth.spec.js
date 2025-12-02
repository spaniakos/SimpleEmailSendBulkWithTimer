import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
  test('user can login', async ({ page }) => {
    // Note: This assumes a user exists. In real tests, you'd seed the database first.
    await page.goto('/login');

    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');

    await page.click('button[type="submit"]');

    // Should redirect to dashboard after login
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('protected routes redirect to login', async ({ page }) => {
    await page.goto('/dashboard');

    // Should redirect to login
    await expect(page).toHaveURL(/\/login/);
  });
});

