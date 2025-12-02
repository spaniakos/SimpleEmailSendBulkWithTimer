import { test, expect } from '@playwright/test';

test.describe('SMTP Configuration', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/dashboard/);
  });

  test('user can navigate to SMTP configurations', async ({ page }) => {
    await page.goto('/smtp');
    
    await expect(page.locator('h2, h1')).toContainText(/smtp/i);
  });

  test('user can navigate to create SMTP configuration', async ({ page }) => {
    await page.goto('/smtp');
    
    const createLink = page.getByRole('link', { name: /create|add|new/i });
    if (await createLink.count() > 0) {
      await createLink.click();
      await expect(page).toHaveURL(/\/smtp\/create/);
    }
  });
});

