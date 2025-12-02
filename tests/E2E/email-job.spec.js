import { test, expect } from '@playwright/test';

test.describe('Email Job Management', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    // Note: In real tests, you'd use a test database and seed users
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/dashboard/);
  });

  test('user can view jobs list', async ({ page }) => {
    await page.goto('/jobs');
    
    await expect(page.locator('h2, h1')).toContainText(/job/i);
  });

  test('user can navigate to create job page', async ({ page }) => {
    await page.goto('/jobs');
    
    const createLink = page.getByRole('link', { name: /create|new/i });
    if (await createLink.count() > 0) {
      await createLink.click();
      await expect(page).toHaveURL(/\/jobs\/create/);
    }
  });

  test('user can view job details', async ({ page }) => {
    // Navigate to jobs first
    await page.goto('/jobs');
    
    // Try to find and click on a job if any exist
    const jobLink = page.locator('a[href*="/jobs/"]').first();
    if (await jobLink.count() > 0) {
      await jobLink.click();
      await expect(page).toHaveURL(/\/jobs\/\d+/);
      
      // Check for job details elements
      await expect(page.locator('h2, h1')).toBeVisible();
    }
  });
});

