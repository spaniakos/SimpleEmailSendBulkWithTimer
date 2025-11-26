# Testing Documentation

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter SmtpConfigurationTest
php artisan test --filter EmailJobTest
php artisan test --filter SendEmailJobTest

# Run with verbose output
php artisan test -v

# Run specific test method
php artisan test --filter test_user_can_create_smtp_configuration
```

## Test Coverage

### SmtpConfigurationTest (9 tests)
- User can view SMTP configurations index
- User can create SMTP configuration
- User can view their own SMTP configuration
- User cannot view other users' SMTP configuration
- User can update their own SMTP configuration
- User cannot update other users' SMTP configuration
- User can delete their own SMTP configuration
- User cannot delete other users' SMTP configuration
- SMTP configuration password is encrypted

### EmailJobTest (11 tests)
- User can view email jobs index
- User can create email job with CSV text
- User can create email job with CSV file
- User can view their own email job
- User cannot view other users' email job
- User can update their own email job
- User can delete their own email job
- User can pause email job
- User can resume paused email job
- CSV parsing extracts unique emails
- Email job extracts domains correctly

### SendEmailJobTest (4 tests)
- Send email job can be dispatched
- Rate limiting prevents multiple emails per domain
- Email job updates recipient status on success
- Email job updates counters on success

## Test Environment

Tests use:
- **RefreshDatabase** trait - Each test runs in a transaction that is rolled back
- **Factories** - Generate test data for models
- **Mail::fake()** - Fakes email sending for testing
- **Queue::fake()** - Fakes queue jobs for testing
- **Cache::flush()** - Clears cache between tests

## Writing New Tests

When adding new features, create corresponding tests:

1. Create test file: `php artisan make:test FeatureNameTest`
2. Use factories to create test data
3. Test both success and failure cases
4. Test authorization (users can only access their own data)
5. Test validation rules
6. Use appropriate assertions

## Example Test Structure

```php
public function test_feature_works_correctly(): void
{
    // Arrange
    $user = User::factory()->create();
    $resource = Resource::factory()->create(['user_id' => $user->id]);

    // Act
    $response = $this->actingAs($user)->get("/resource/{$resource->id}");

    // Assert
    $response->assertStatus(200);
    $response->assertViewIs('resource.show');
}
```

