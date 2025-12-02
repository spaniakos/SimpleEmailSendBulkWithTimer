# Testing Guide

This project includes comprehensive test coverage with multiple test types. All tests are automatically run via GitHub Actions on every push and pull request.

## Test Types

### 1. **Smoke Tests** (`tests/Smoke/`)
Quick sanity checks to ensure the application boots and basic functionality works:
- Application boots successfully
- Database connection works
- Authentication routes are accessible
- Protected routes require authentication
- Queue system is configured
- Email configuration exists

**Run smoke tests:**
```bash
php artisan test --testsuite=Smoke
```

### 2. **Unit Tests** (`tests/Unit/`)
Test individual components in isolation:
- Model methods
- Helper functions
- Utility classes

**Run unit tests:**
```bash
php artisan test --testsuite=Unit
```

### 3. **Functional Tests** (`tests/Feature/`)
Test complete features and workflows:
- Email job creation and management
- SMTP configuration CRUD
- Authentication flows
- Authorization (users can only access their own data)
- CSV parsing and recipient creation

**Run functional tests:**
```bash
php artisan test --testsuite=Feature
```

### 4. **E2E Tests** (`tests/E2E/`)
End-to-end browser tests using Playwright:
- User registration and login
- Email job creation workflow
- SMTP configuration management
- Full user journeys

**Run E2E tests:**
```bash
# Install Playwright browsers first (one-time setup)
npx playwright install --with-deps

# Run tests
npm run test:e2e

# Run with UI mode (interactive)
npm run test:e2e:ui
```

### 5. **Code Style (Pint)**
Laravel Pint ensures code follows PSR-12 style guide:

**Check code style:**
```bash
./vendor/bin/pint --test
```

**Fix code style:**
```bash
./vendor/bin/pint
```

## Running All Tests

```bash
# Run all PHP tests (smoke, unit, functional)
php artisan test

# Run all tests including E2E
php artisan test && npm run test:e2e
```

## GitHub Actions CI/CD

All tests run automatically on push and pull requests via GitHub Actions:

1. **Pint** - Code style check
2. **Unit Tests** - Fast unit tests
3. **Functional Tests** - Feature tests with database
4. **Smoke Tests** - Basic sanity checks
5. **E2E Tests** - Full browser tests with Playwright

View test results in the "Actions" tab of your GitHub repository.

## Test Configuration

### PHPUnit Configuration
- Location: `phpunit.xml`
- Test suites: Unit, Feature, Smoke
- Uses SQLite in-memory database for tests
- Environment: `APP_ENV=testing`

### Playwright Configuration
- Location: `playwright.config.js`
- Browser: Chromium (Desktop Chrome)
- Base URL: `http://localhost:8000`
- Auto-starts Laravel server for tests

## Test Data

### Factories
- `UserFactory` - Creates test users
- `SmtpConfigurationFactory` - Creates SMTP configs
- `EmailJobFactory` - Creates email jobs
- `EmailRecipientFactory` - Creates recipients

### Seeders
- `DatabaseSeeder` - Creates test user (`test@example.com` / `password`) for E2E tests

## Writing Tests

### PHPUnit Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_something_works(): void
    {
        // Arrange
        $user = User::factory()->create();
        
        // Act
        $response = $this->actingAs($user)->get('/jobs');
        
        // Assert
        $response->assertStatus(200);
    }
}
```

### Playwright Test Example

```javascript
import { test, expect } from '@playwright/test';

test('user can do something', async ({ page }) => {
  await page.goto('/jobs');
  await expect(page.locator('h1')).toBeVisible();
});
```

## Best Practices

1. **Use RefreshDatabase** - Ensures clean database state for each test
2. **Use Factories** - Create test data consistently
3. **Test User Isolation** - Each test should use its own user/data
4. **Keep Tests Fast** - Unit tests should be < 1 second, functional < 5 seconds
5. **Clear Test Names** - Use descriptive test method names
6. **One Assertion Per Test** - When possible, focus each test on one behavior

## Troubleshooting

### E2E Tests Failing
- Ensure Laravel server is running: `php artisan serve`
- Check that database is seeded: `php artisan db:seed`
- Verify test user exists: `test@example.com` / `password`

### Database Issues
- Tests use SQLite in-memory database
- Each test gets a fresh database (RefreshDatabase trait)
- If tests interfere, check for shared state

### Playwright Browser Issues
- Reinstall browsers: `npx playwright install --with-deps`
- Clear cache: `rm -rf playwright/.cache`
