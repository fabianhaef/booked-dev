# Installation Guide - Booked

Complete installation instructions for the Booked plugin for Craft CMS.

## Table of Contents

- [Requirements](#requirements)
- [Installation Methods](#installation-methods)
- [Initial Setup](#initial-setup)
- [Database Migration](#database-migration)
- [Configuration](#configuration)
- [Optional Integrations](#optional-integrations)
- [Troubleshooting](#troubleshooting)

## Requirements

### System Requirements

- **Craft CMS**: 5.0 or later
- **PHP**: 8.2 or later with the following extensions:
  - PDO (MySQL or PostgreSQL)
  - mbstring
  - json
  - openssl
  - fileinfo
  - intl
- **Database**: MySQL 5.7+ or PostgreSQL 10+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Composer**: 2.0 or later

### Recommended

- PHP 8.3 for optimal performance
- MySQL 8.0 or PostgreSQL 14+
- Redis or Memcached for caching
- SSL certificate for production

## Installation Methods

### Method 1: Composer (Recommended)

1. Open your terminal and navigate to your Craft project directory:

```bash
cd /path/to/your/craft-project
```

2. Require the plugin via Composer:

```bash
composer require fabian/booked
```

3. Install the plugin:

```bash
php craft plugin/install booked
```

### Method 2: Control Panel Installation

1. Download the latest release from the [releases page](https://github.com/fabian/booked/releases)

2. Extract the archive to your `plugins/` directory

3. Navigate to **Settings → Plugins** in the Craft control panel

4. Find "Booked" and click **Install**

### Method 3: Manual Installation (Development)

1. Clone the repository:

```bash
cd plugins/
git clone https://github.com/fabian/booked.git
```

2. Install dependencies:

```bash
cd booked
composer install
```

3. Install via control panel or CLI:

```bash
php craft plugin/install booked
```

## Database Migration

The plugin will automatically create the necessary database tables during installation:

### Tables Created

- `booked_reservations` - Stores all bookings
- `booked_services` - Service definitions
- `booked_employees` - Employee/resource management
- `booked_schedules` - Recurring schedules
- `booked_availabilities` - One-time availability windows
- `booked_locations` - Location management
- `booked_blackout_dates` - Unavailable dates
- `booked_calendar_tokens` - OAuth tokens for calendar sync
- `booked_external_events` - Synced calendar events
- `booked_oauth_state_tokens` - OAuth state verification
- `booked_reservation_custom_fields` - Custom field data

### Indexes Created

The following composite indexes are created for optimal query performance:

```sql
-- Reservation lookup
idx_bookingdate_employeeid
idx_bookingdate_serviceid
idx_status_bookingdate
idx_userid_bookingdate

-- External events
idx_employeeid_startdate

-- Availability cache
idx_employee_service_date
```

### Manual Migration (if needed)

If you need to run migrations manually:

```bash
php craft migrate/all --plugin=booked
```

To check migration status:

```bash
php craft migrate/status --plugin=booked
```

## Initial Setup

### 1. Configure Basic Settings

Navigate to **Settings → Booked** in the control panel.

#### General Settings

- **Business Name**: Your business name (appears in emails)
- **Default Timezone**: Your primary business timezone
- **Date Format**: Preferred date format for display
- **Time Format**: 12-hour or 24-hour format

#### Booking Settings

- **Minimum Advance Booking**: Minimum hours in advance for bookings (e.g., 24 hours)
- **Maximum Advance Booking**: Maximum days in advance (e.g., 90 days)
- **Booking Confirmation**: Auto-confirm or require manual approval
- **Cancellation Policy**: Hours before cancellation is allowed

#### Notification Settings

- **System Email**: Email address for system notifications
- **Admin Notification**: Enable admin notifications for new bookings
- **Customer Confirmation**: Enable customer confirmation emails
- **Reminder Emails**: Enable automated reminder emails
- **Reminder Timing**: Hours before appointment (e.g., 24 hours)

### 2. Create Services

1. Navigate to **Booked → Services**
2. Click **New Service**
3. Configure:
   - **Title**: Service name
   - **Duration**: Length in minutes
   - **Price**: Cost (optional, for Commerce integration)
   - **Buffer Before**: Minutes before appointment
   - **Buffer After**: Minutes after appointment
   - **Description**: Service details (appears in booking form)
   - **Enabled**: Toggle availability

### 3. Create Employees/Resources

1. Navigate to **Booked → Employees**
2. Click **New Employee**
3. Configure:
   - **Name**: Employee name
   - **Email**: Email address (for notifications)
   - **Location**: Assign to location
   - **Services**: Select which services this employee provides
   - **Bio**: Optional employee bio

### 4. Define Locations

1. Navigate to **Booked → Locations**
2. Click **New Location**
3. Configure:
   - **Name**: Location name
   - **Address**: Full address
   - **Timezone**: Location timezone
   - **Coordinates**: Latitude/Longitude (for maps)

### 5. Set Up Schedules

1. Navigate to **Booked → Schedules**
2. Click **New Schedule**
3. Configure:
   - **Day of Week**: Select day(s)
   - **Start Time**: When availability begins
   - **End Time**: When availability ends
   - **Employees**: Assign to employees
   - **Services**: Filter by services (optional)
   - **Enabled**: Toggle active status

## Optional Integrations

### Google Calendar Sync

1. Create a Google Cloud Project:
   - Visit [Google Cloud Console](https://console.cloud.google.com)
   - Create new project
   - Enable Google Calendar API

2. Create OAuth 2.0 Credentials:
   - Go to **APIs & Services → Credentials**
   - Create OAuth 2.0 Client ID
   - Set redirect URI: `https://yourdomain.com/admin/booked/calendar/callback`

3. Configure in Booked:
   - Navigate to **Settings → Booked → Calendar Sync**
   - Enter Client ID
   - Enter Client Secret
   - Save settings

4. Connect employee calendars:
   - Go to **Booked → Employees**
   - Edit employee
   - Click **Connect Google Calendar**
   - Authorize access

### Microsoft Outlook Sync

1. Register app in Azure:
   - Visit [Azure Portal](https://portal.azure.com)
   - Go to **App Registrations**
   - Create new registration
   - Set redirect URI: `https://yourdomain.com/admin/booked/calendar/callback`

2. Configure permissions:
   - Add **Calendars.ReadWrite** permission
   - Grant admin consent

3. Configure in Booked:
   - Navigate to **Settings → Booked → Calendar Sync**
   - Enter Application ID
   - Enter Client Secret
   - Save settings

### Zoom Integration

1. Create Zoom App:
   - Visit [Zoom App Marketplace](https://marketplace.zoom.us)
   - Create Server-to-Server OAuth app
   - Note Account ID, Client ID, and Client Secret

2. Configure in Booked:
   - Navigate to **Settings → Booked → Virtual Meetings**
   - Select **Zoom** as provider
   - Enter Account ID
   - Enter Client ID
   - Enter Client Secret
   - Save settings

### Microsoft Teams Integration

1. Register app (same as Outlook sync above)

2. Add additional permissions:
   - **OnlineMeetings.ReadWrite**

3. Configure in Booked:
   - Navigate to **Settings → Booked → Virtual Meetings**
   - Select **Microsoft Teams** as provider
   - Use same credentials as Outlook sync

### Craft Commerce Integration

1. Ensure Craft Commerce is installed:

```bash
composer require craftcms/commerce
php craft plugin/install commerce
```

2. Configure in Booked:
   - Navigate to **Settings → Booked → Payments**
   - Enable **Commerce Integration**
   - Select **Product Type** for bookings
   - Configure **Deposit Settings** (optional)

3. Create order statuses for bookings

## Post-Installation

### 1. Test Booking Flow

1. Create a test service
2. Create a test employee with schedule
3. Navigate to frontend booking form
4. Complete a test booking
5. Verify:
   - Booking appears in control panel
   - Email confirmation sent
   - Calendar sync works (if configured)

### 2. Configure Templates

Copy example templates to your templates directory:

```bash
cp -r plugins/booked/templates/examples templates/bookings
```

Edit templates to match your design.

### 3. Set Up Cron Jobs (Optional)

For automated reminders and cleanup:

```bash
# Add to crontab
*/5 * * * * php /path/to/craft queue/run
0 * * * * php /path/to/craft booked/reminders/send
0 0 * * * php /path/to/craft booked/cleanup/expired
```

### 4. Configure Permissions

1. Navigate to **Settings → Users → User Groups**
2. Edit user groups that need booking access
3. Enable Booked permissions:
   - View bookings
   - Create bookings
   - Edit bookings
   - Delete bookings
   - Manage services
   - Manage employees
   - Access settings

## Troubleshooting

### Installation Issues

#### Plugin fails to install

```bash
# Clear cache and try again
php craft clear-caches/all
php craft plugin/install booked
```

#### Database errors during migration

```bash
# Check database connection
php craft db/backup

# Run migrations manually
php craft migrate/all --plugin=booked
```

### Common Issues

#### "Class not found" errors

```bash
# Regenerate autoloader
composer dump-autoload
php craft clear-caches/compiled-classes
```

#### Permissions errors

```bash
# Set correct permissions
chmod -R 755 storage/
chmod -R 755 web/cpresources/
```

#### Calendar sync not working

1. Verify redirect URI matches exactly
2. Check OAuth credentials are correct
3. Ensure SSL is enabled
4. Check server time is synchronized

#### Emails not sending

1. Verify email settings in **Settings → Email**
2. Check queue is running
3. Test with a simple test email
4. Check spam folder

### Performance Optimization

#### Enable caching

```php
// config/booked.php
return [
    'enableCache' => true,
    'cacheDuration' => 3600, // 1 hour
];
```

#### Use Redis for queue

```php
// config/app.php
return [
    'components' => [
        'queue' => [
            'class' => \yii\queue\redis\Queue::class,
            'redis' => 'redis',
        ],
    ],
];
```

### Getting Help

- **Documentation**: See [USER_GUIDE.md](USER_GUIDE.md) and [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)
- **Issues**: [GitHub Issues](https://github.com/fabian/booked/issues)
- **Support**: [Craft Discord](https://craftcms.com/discord) #booked channel

## Next Steps

- [Configuration Guide](CONFIGURATION.md) - Detailed configuration options
- [User Guide](USER_GUIDE.md) - Learn how to manage bookings
- [Developer Guide](DEVELOPER_GUIDE.md) - Customize and extend Booked

## Uninstallation

To completely remove Booked:

```bash
# Uninstall plugin (keeps data)
php craft plugin/uninstall booked

# Remove via Composer
composer remove fabian/booked

# To delete all data (WARNING: irreversible)
php craft booked/cleanup/purge-all
```
