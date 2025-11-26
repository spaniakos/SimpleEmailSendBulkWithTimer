# Laravel Email Bulk Sender

A Laravel 12 application for sending bulk emails with rate limiting, queue management, and comprehensive reporting.

## Features

- **User Authentication**: Simple user/password authentication using Laravel Breeze
- **SMTP Configuration**: Manage multiple SMTP configurations per user
- **Bulk Email Sending**: Send emails to multiple recipients from CSV files or pasted text
- **Queue System**: Database-based queue system for background email processing
- **Batch Processing**: Intelligent batch processing of emails (configurable batch size)
- **Rate Limiting**: Configurable rate limiting per sender domain
- **Real-time Reporting**: Track sent/total emails with progress indicators
- **Job Management**: Create, pause, resume, and delete email jobs
- **Tailwind CSS**: Modern, responsive UI built with Tailwind CSS

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js and NPM
- Database (MySQL, PostgreSQL, or SQLite)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd SimpleEmailSendBulkWithTimer
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install Node dependencies:
```bash
npm install
```

4. Copy environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure your `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_DRIVER=database

# Email Rate Limiting (optional - defaults shown)
MAIL_RATE_LIMIT_COUNT=1      # Number of emails to process per batch (also controls batch size)
EMAIL_RATE_LIMIT_SECONDS=10  # Time window in seconds between emails to the same domain
```

7. Run migrations:
```bash
php artisan migrate
```

8. Create initial user (optional):
```bash
# Interactive mode (will prompt for details)
php artisan user:create

# Non-interactive mode (provide all options)
php artisan user:create --email=admin@example.com --name="Admin User" --password=yourpassword
```

9. Build frontend assets:
```bash
npm run build
```

10. Start the development server:
```bash
php artisan serve
```

11. **Important**: Start the queue worker in a separate terminal:
```bash
php artisan queue:work
```

## Usage

### 1. Create an Account

You can create a user account in two ways:

**Option A: Using the Artisan Command (Recommended for initial setup)**
```bash
php artisan user:create
```

This will prompt you for:
- Email address
- Name
- Password (with confirmation)

Or provide all options at once:
```bash
php artisan user:create --email=user@example.com --name="John Doe" --password=securepassword
```

**Option B: Using the Web Interface**
Register a new account through the web interface or login with existing credentials.

### 2. Configure SMTP

1. Navigate to "SMTP Configurations"
2. Click "Add SMTP Configuration"
3. Enter your SMTP details:
   - Host (e.g., smtp.gmail.com)
   - Port (e.g., 587 for TLS, 465 for SSL)
   - Username and Password
   - Encryption type (TLS or SSL)
   - From email and name

### 3. Create an Email Job

1. Navigate to "Email Jobs"
2. Click "Create New Job"
3. Fill in the form:
   - Select an SMTP configuration
   - Enter email subject
   - Enter HTML formatted email content
   - Upload a CSV file OR paste email addresses
4. Click "Create Job"

### 4. Monitor Progress

- View job status and progress on the dashboard
- Click on a job to see detailed recipient status
- Jobs auto-refresh every 5 seconds while processing

### 5. Manage Jobs

- **Pause**: Temporarily stop sending emails
- **Resume**: Continue sending from where it left off
- **Delete**: Remove a job and all its data

## CSV Format

The CSV file can contain email addresses in the following formats:

- One email per line:
```
email1@example.com
email2@example.com
email3@example.com
```

- Comma-separated:
```
email1@example.com, email2@example.com, email3@example.com
```

- CSV with headers (email addresses will be extracted from any column):
```
name,email,company
John Doe,john@example.com,Company A
Jane Smith,jane@example.com,Company B
```

## Architecture

The application uses a **batch processing architecture** for efficient email sending:

- **ProcessEmailJob**: A single queue job that processes recipients in batches
- **Batch Size**: Controlled by `MAIL_RATE_LIMIT_COUNT` (default: 1)
- **Automatic Continuation**: The job automatically dispatches itself for the next batch when more recipients remain
- **Rate Limiting**: Built-in rate limiting per sender domain

### Benefits

- **Better Queue Management**: One job per email campaign instead of one job per recipient
- **Controlled Processing**: Processes emails in configurable batches
- **Smarter Rate Limiting**: Automatically waits when rate limits are hit
- **Pause/Resume Support**: Can pause and resume entire campaigns
- **Error Resilience**: Failed emails don't block the entire batch

## Rate Limiting

The system enforces a configurable rate limit per **sender domain** (not recipient domain) to prevent sending too many emails too quickly. By default, it's set to **1 email per 10 seconds per sender domain**.

### Configuration

You can customize the rate limiting and batch size in your `.env` file:

```env
MAIL_RATE_LIMIT_COUNT=1      # Number of emails to process per batch (also controls batch size)
EMAIL_RATE_LIMIT_SECONDS=10  # Time window in seconds between emails from the same sender domain
```

### How It Works

- **Batch Processing**: The system processes `MAIL_RATE_LIMIT_COUNT` recipients per batch
- **Rate Limiting**: After sending an email, the system waits `EMAIL_RATE_LIMIT_SECONDS` before sending the next email from the same sender domain
- **Automatic Queuing**: If rate limit is hit during batch processing, the job dispatches itself again after the delay
- **Domain-Based**: Rate limiting is based on the sender's domain (from SMTP configuration), not recipient domains
- **Example**: With `MAIL_RATE_LIMIT_COUNT=1` and `EMAIL_RATE_LIMIT_SECONDS=10`, the system will send 1 email, wait 10 seconds, then send the next email from the same sender domain

## Queue Worker

**Important**: The queue worker must be running for emails to be sent. Start it with:

```bash
php artisan queue:work
```

For production, use a process manager like Supervisor to keep the queue worker running.

## Database Configuration

All queues, sessions, and cache are stored in the database:
- `QUEUE_CONNECTION=database` - Queue jobs stored in `jobs` table
- `SESSION_DRIVER=database` - Sessions stored in `sessions` table
- `CACHE_DRIVER=database` - Cache stored in `cache` table

## Security

- SMTP passwords are encrypted using Laravel's Crypt facade
- User authentication required for all routes
- Policies ensure users can only access their own data
- CSRF protection on all forms

## Testing

The application includes comprehensive test coverage:

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter SmtpConfigurationTest
php artisan test --filter EmailJobTest
php artisan test --filter SendEmailJobTest

# Run with coverage (requires Xdebug)
php artisan test --coverage
```

### Test Coverage

- **SmtpConfigurationTest**: Tests SMTP configuration CRUD operations and authorization
- **EmailJobTest**: Tests email job creation, CSV parsing, and job management
- **SendEmailJobTest**: Tests email sending, rate limiting, and job execution (Note: SendEmailJob is deprecated, new jobs use ProcessEmailJob)
- **Authorization Tests**: Ensures users can only access their own data

All tests use database transactions and factories for clean test data.

## Architecture Notes

### Batch Processing System

The application uses `ProcessEmailJob` for efficient email processing:

- **One job per campaign**: When you create an email job, only one `ProcessEmailJob` is dispatched
- **Batch processing**: Processes recipients in batches (controlled by `MAIL_RATE_LIMIT_COUNT`)
- **Self-dispatching**: Automatically dispatches itself for the next batch when more recipients remain
- **Rate limiting**: Built-in rate limiting per sender domain with automatic delays

### Deprecated Code

- **SendEmailJob**: The old implementation (one job per recipient) is deprecated but kept for backward compatibility
- New email jobs automatically use `ProcessEmailJob`
- Old jobs using `SendEmailJob` will continue to work but new jobs will use the batch processing system

## Troubleshooting

### Emails not sending
- Ensure the queue worker is running: `php artisan queue:work`
- Check SMTP credentials are correct
- Verify the queue connection is set to `database` in `.env`
- Check application logs: `storage/logs/laravel.log`
- Test SMTP connection using the "Test Connection" button
- Check queue status on the dashboard to see if jobs are waiting

### Rate limiting issues
- The system automatically handles rate limiting per sender domain
- Emails are processed in batches and queued when rate limits are hit
- Check the job status to see pending emails
- Adjust `MAIL_RATE_LIMIT_COUNT` to process more emails per batch
- Adjust `EMAIL_RATE_LIMIT_SECONDS` in `.env` to change the delay between emails

### Authorization errors
- Ensure you're logged in
- Verify you own the resource you're trying to access
- Check application logs for detailed error messages

## API Documentation

### SMTP Configuration Endpoints

- `GET /smtp` - List all SMTP configurations (user's own)
- `GET /smtp/create` - Show create form
- `POST /smtp` - Create new SMTP configuration
- `GET /smtp/{id}` - View SMTP configuration details
- `GET /smtp/{id}/edit` - Show edit form
- `PUT /smtp/{id}` - Update SMTP configuration
- `DELETE /smtp/{id}` - Delete SMTP configuration
- `POST /smtp/test-connection` - Test SMTP connection (AJAX)

### Email Job Endpoints

- `GET /jobs` - List all email jobs (user's own)
- `GET /jobs/create` - Show create form
- `POST /jobs` - Create new email job
- `GET /jobs/{id}` - View job details and progress
- `GET /jobs/{id}/edit` - Show edit form
- `PUT /jobs/{id}` - Update email job
- `DELETE /jobs/{id}` - Delete email job
- `POST /jobs/{id}/pause` - Pause email job
- `POST /jobs/{id}/resume` - Resume paused email job

## License

This project is open-sourced software licensed under the MIT license.
