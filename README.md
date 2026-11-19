[![Tests](https://github.com/spaniakos/SimpleEmailSendBulkWithTimer/actions/workflows/tests.yml/badge.svg)](https://github.com/spaniakos/SimpleEmailSendBulkWithTimer/actions)
[![CI](https://github.com/spaniakos/SimpleEmailSendBulkWithTimer/actions/workflows/ci.yml/badge.svg)](https://github.com/spaniakos/SimpleEmailSendBulkWithTimer/actions)
[![Lint](https://github.com/spaniakos/SimpleEmailSendBulkWithTimer/actions/workflows/lint.yml/badge.svg)](https://github.com/spaniakos/SimpleEmailSendBulkWithTimer/actions)
![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)
![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green.svg)
![Code Style: Laravel Pint](https://img.shields.io/badge/Code_Style-Laravel_Pint-orange)

# Laravel Email Bulk Sender

A Laravel 12 application for sending bulk emails with rate limiting, queue management, and comprehensive reporting. Perfect for marketing campaigns, newsletters, and transactional emails.

## 🚀 Features

- **Bulk Email Sending**: Send emails to thousands of recipients efficiently
- **Rate Limiting**: Configurable account-wide and recipient-domain rolling limits
- **Queue Management**: Database-based queue system for reliable background processing
- **Batch Processing**: Intelligent batch processing with automatic continuation
- **Job Management**: Create, pause, resume, and delete email jobs
- **Real-time Progress**: Track sent/total emails with live progress indicators
- **SMTP Management**: Manage multiple SMTP configurations per user
- **CSV Import**: Import recipients from CSV files or paste email addresses
- **Reusable Mailing Lists**: Import, organize, and reuse contacts across email jobs
- **Rich HTML Editor**: TinyMCE WYSIWYG editor for creating beautiful emails
- **File Uploads**: Upload images and PDFs directly in the email editor
- **User Authentication**: Secure authentication with Laravel Breeze
- **Comprehensive Testing**: Full test coverage with unit, functional, smoke, and E2E tests


## 📋 Requirements

- PHP 8.3 or higher
- Composer
- Node.js 18+ and NPM
- Database (MySQL 5.7+, PostgreSQL 10+, or SQLite 3.8+)

## 📦 Installation

1. Clone the repository:
```bash
git clone https://github.com/spaniakos/SimpleEmailSendBulkWithTimer.git
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

# Rate-limit defaults for accounts before they save custom settings
MAIL_MAX_EMAILS_PER_WINDOW=6
MAIL_RATE_LIMIT_WINDOW_SECONDS=60
MAIL_MAX_EMAILS_PER_DOMAIN=1
MAIL_DOMAIN_RATE_LIMIT_SECONDS=240
```

7. Run migrations:
```bash
php artisan migrate
```

8. Create initial user (optional):

**Option A: Using Artisan Command (Recommended)**
```bash
# Interactive mode (will prompt for details)
php artisan user:create

# Non-interactive mode (provide all options)
php artisan user:create --email=admin@example.com --name="Admin User" --password=yourpassword
```

**Option B: Direct Database Insert**

If you prefer to create a user directly in the database:

1. Generate a password hash using PHP:

**Method 1: One-liner command (Recommended)**
```bash
php artisan tinker --execute="echo Hash::make('your-password-here');"
```

**Method 2: Interactive tinker**
```bash
php artisan tinker
```
Then in tinker:
```php
Hash::make('your-password-here')
```
Copy the hashed password and exit tinker with `exit`.

2. Insert the user directly into the database:
```sql
INSERT INTO users (name, email, password, email_verified_at, created_at, updated_at)
VALUES (
    'Admin User',
    'admin@example.com',
    'paste-hashed-password-here',
    NOW(),
    NOW(),
    NOW()
);
```

Or using MySQL command line:
```bash
mysql -u your_username -p your_database -e "INSERT INTO users (name, email, password, email_verified_at, created_at, updated_at) VALUES ('Admin User', 'admin@example.com', 'paste-hashed-password-here', NOW(), NOW(), NOW());"
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

## 💻 Usage

### 1. Create an Account

You can create a user account in two ways:

**Option A: Using the Artisan Command (Recommended)**
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

**Option B: Direct Database Insert**

If you prefer to create a user directly in the database:

1. Generate a password hash using PHP:

**Method 1: One-liner command (Recommended)**
```bash
php artisan tinker --execute="echo Hash::make('your-password-here');"
```

**Method 2: Interactive tinker**
```bash
php artisan tinker
```
Then in tinker:
```php
Hash::make('your-password-here')
```
Copy the hashed password and exit tinker with `exit`.

2. Insert the user directly into the database:
```sql
INSERT INTO users (name, email, password, email_verified_at, created_at, updated_at)
VALUES (
    'Admin User',
    'admin@example.com',
    'paste-hashed-password-here',
    NOW(),
    NOW(),
    NOW()
);
```

Or using MySQL command line:
```bash
mysql -u your_username -p your_database -e "INSERT INTO users (name, email, password, email_verified_at, created_at, updated_at) VALUES ('Admin User', 'admin@example.com', 'paste-hashed-password-here', NOW(), NOW(), NOW());"
```

**Note**: Registration is not available through the web interface. Use one of the methods above to create users.

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

## 📄 CSV Format

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

## 🏗️ Architecture

The application uses a **batch processing architecture** for efficient email sending:

- **ProcessEmailJob**: A single queue job that processes recipients in batches
- **Rate Limits**: Account-wide and recipient-domain rolling windows are configurable in Email Settings
- **Automatic Continuation**: The job automatically dispatches itself for the next batch when more recipients remain
- **Rate Limiting**: Built-in account-wide and recipient-domain rolling limits

### Benefits

- **Better Queue Management**: One job per email campaign instead of one job per recipient
- **Controlled Processing**: Processes emails in configurable batches
- **Smarter Rate Limiting**: Automatically waits when rate limits are hit
- **Pause/Resume Support**: Can pause and resume entire campaigns
- **Error Resilience**: Failed emails don't block the entire batch

## ⏱️ Rate Limiting

The system enforces two account-wide rolling limits: a maximum number of attempts across all campaigns in a time window, and a maximum number for each **recipient domain** (such as gmail.com) in its own window. The conservative defaults allow up to 6 attempts per 60 seconds overall and 1 attempt per recipient domain per 240 seconds.

### Configuration

Rate limits can be changed in **Email Settings** while campaigns are queued or processing. Active workers read current settings whenever they reserve the next recipient; no queue restart is required. `.env` variables provide defaults for accounts that have not saved their own settings:

```env
MAIL_MAX_EMAILS_PER_WINDOW=6
MAIL_RATE_LIMIT_WINDOW_SECONDS=60
MAIL_MAX_EMAILS_PER_DOMAIN=1
MAIL_DOMAIN_RATE_LIMIT_SECONDS=240
```

### How It Works

- **Rolling global limit**: Applies across all campaigns owned by the account
- **Rolling recipient-domain limit**: Applies to the domain after `@` in each recipient address
- **Domain interleaving**: A capped destination is skipped while other eligible recipient domains can continue
- **Safe setting changes**: Each reservation reads settings from the database and is serialized with settings updates; an in-flight SMTP send may finish under the settings used when its slot was reserved
- **Automatic queuing**: When a limit is reached, pending work is rescheduled for reevaluation
- **Deliverability**: Rate limits help control volume but cannot guarantee acceptance or prevent spam filtering; sender authentication, reputation, complaint rates, and list quality still matter

## 🔄 Queue Worker

**Important**: The queue worker must be running for emails to be sent. Start it with:

```bash
php artisan queue:work
```

For production, use a process manager like Supervisor to keep the queue worker running.

## 🗄️ Database Configuration

All queues, sessions, and cache are stored in the database:
- `QUEUE_CONNECTION=database` - Queue jobs stored in `jobs` table
- `SESSION_DRIVER=database` - Sessions stored in `sessions` table
- `CACHE_DRIVER=database` - Cache stored in `cache` table

## 🔒 Security

- SMTP passwords are encrypted using Laravel's Crypt facade
- User authentication required for all routes
- Policies ensure users can only access their own data
- CSRF protection on all forms

## 🧪 Testing

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

## 📝 Architecture Notes

### Batch Processing System

The application uses `ProcessEmailJob` for efficient email processing:

- **One job per campaign**: When you create an email job, only one `ProcessEmailJob` is dispatched
- **Rate limiting**: Reserves each send against account-wide and recipient-domain rolling limits
- **Self-dispatching**: Automatically dispatches itself for the next batch when more recipients remain
- **Rate limiting**: Built-in account-wide and recipient-domain limits with automatic delays

### Deprecated Code

- **SendEmailJob**: The old implementation (one job per recipient) is deprecated but kept for backward compatibility
- New email jobs automatically use `ProcessEmailJob`
- Old jobs using `SendEmailJob` will continue to work but new jobs will use the batch processing system

## 🐛 Troubleshooting

### Emails not sending
- Ensure the queue worker is running: `php artisan queue:work`
- Check SMTP credentials are correct
- Verify the queue connection is set to `database` in `.env`
- Check application logs: `storage/logs/laravel.log`
- Test SMTP connection using the "Test Connection" button
- Check queue status on the dashboard to see if jobs are waiting

### Rate limiting issues
- The system automatically handles account-wide and recipient-domain rate limits
- Pending addresses in a capped domain are skipped so other destinations can proceed
- Check the job status to see pending emails
- Update the rolling rate values in **Email Settings**; workers use them without restart

### Authorization errors
- Ensure you're logged in
- Verify you own the resource you're trying to access
- Check application logs for detailed error messages

## 📚 API Documentation

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

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT License](LICENSE).
