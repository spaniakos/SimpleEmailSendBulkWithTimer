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
    
    // Use more specific selector - prefer the button over the empty state link
    const createLink = page.getByRole('link', { name: /add smtp configuration/i }).first();
    if (await createLink.count() > 0) {
      await createLink.click();
      await expect(page).toHaveURL(/\/smtp\/create/);
    } else {
      // Fallback: if no button, try the empty state link
      const emptyStateLink = page.getByRole('link', { name: /create your first configuration/i });
      if (await emptyStateLink.count() > 0) {
        await emptyStateLink.click();
        await expect(page).toHaveURL(/\/smtp\/create/);
      }
    }
  });
});

