# Configuration Guide - Booked

Complete configuration reference for the Booked plugin.

## Table of Contents

- [Configuration Files](#configuration-files)
- [General Settings](#general-settings)
- [Booking Settings](#booking-settings)
- [Email Configuration](#email-configuration)
- [Calendar Sync Settings](#calendar-sync-settings)
- [Virtual Meeting Settings](#virtual-meeting-settings)
- [Payment Integration](#payment-integration)
- [Performance Settings](#performance-settings)
- [Advanced Configuration](#advanced-configuration)

## Configuration Files

Booked can be configured via the control panel or configuration files.

### Plugin Configuration File

Create `config/booked.php` for environment-specific settings:

```php
<?php

return [
    // Enable/disable caching
    'enableCache' => true,

    // Cache duration in seconds
    'cacheDuration' => 3600,

    // Default timezone
    'timezone' => 'Europe/Zurich',

    // Minimum advance booking hours
    'minimumAdvanceBookingHours' => 24,

    // Maximum advance booking days
    'maximumAdvanceBookingDays' => 90,

    // Auto-confirm bookings
    'autoConfirm' => true,

    // Cancellation deadline (hours before)
    'cancellationDeadlineHours' => 24,

    // Enable virtual meetings
    'enableVirtualMeetings' => true,

    // Virtual meeting provider ('zoom' or 'teams')
    'virtualMeetingProvider' => 'zoom',

    // Enable Commerce integration
    'enableCommerce' => false,

    // Require deposit for bookings
    'requireDeposit' => false,

    // Deposit percentage (0-100)
    'depositPercentage' => 50,
];
```

### Environment-Specific Configuration

Use `.env` for sensitive credentials:

```bash
# Google Calendar
GOOGLE_CALENDAR_CLIENT_ID=your_client_id
GOOGLE_CALENDAR_CLIENT_SECRET=your_client_secret

# Microsoft Outlook
OUTLOOK_CALENDAR_CLIENT_ID=your_app_id
OUTLOOK_CALENDAR_CLIENT_SECRET=your_secret

# Zoom
ZOOM_ACCOUNT_ID=your_account_id
ZOOM_CLIENT_ID=your_client_id
ZOOM_CLIENT_SECRET=your_client_secret

# Microsoft Teams
TEAMS_CLIENT_ID=your_app_id
TEAMS_CLIENT_SECRET=your_secret
TEAMS_TENANT_ID=your_tenant_id
```

Reference in config file:

```php
return [
    'googleCalendarClientId' => getenv('GOOGLE_CALENDAR_CLIENT_ID'),
    'googleCalendarClientSecret' => getenv('GOOGLE_CALENDAR_CLIENT_SECRET'),
];
```

## General Settings

### Business Information

```php
'businessName' => 'Acme Booking Services',
'businessEmail' => 'bookings@acme.com',
'businessPhone' => '+1-555-0123',
'businessAddress' => '123 Main St, City, State 12345',
```

### Timezone Settings

```php
// System default timezone
'timezone' => 'America/New_York',

// Enable multi-timezone support
'enableMultiTimezone' => true,

// Display timezone selector in booking forms
'showTimezoneSelector' => true,
```

### Date & Time Format

```php
// Date format (PHP date format)
'dateFormat' => 'Y-m-d',

// Time format
'timeFormat' => 'H:i', // 24-hour
// 'timeFormat' => 'g:i A', // 12-hour

// Display format for users
'displayDateFormat' => 'F j, Y', // January 1, 2025
'displayTimeFormat' => 'g:i A',  // 3:00 PM
```

## Booking Settings

### Advance Booking Rules

```php
// Minimum hours in advance
'minimumAdvanceBookingHours' => 24,

// Maximum days in advance
'maximumAdvanceBookingDays' => 90,

// Allow same-day bookings
'allowSameDayBooking' => false,

// Same-day booking cutoff hour
'sameDayCutoffHour' => 12, // Noon
```

### Booking Confirmation

```php
// Auto-confirm bookings
'autoConfirm' => true,

// Require admin approval
'requireApproval' => false,

// Auto-cancel unconfirmed bookings after (hours)
'autoCancel UnconfirmedAfterHours' => 24,
```

### Cancellation Policy

```php
// Hours before cancellation allowed
'cancellationDeadlineHours' => 24,

// Allow customer cancellations
'allowCustomerCancellation' => true,

// Cancellation requires reason
'requireCancellationReason' => true,

// Auto-refund on cancellation (Commerce)
'autoRefundOnCancellation' => false,
```

### Capacity & Quantity

```php
// Enable quantity selection
'enableQuantitySelection' => true,

// Default max capacity
'defaultMaxCapacity' => 10,

// Overbooking protection
'preventOverbooking' => true,

// Double booking protection
'preventDoubleBooking' => true,
```

### Soft Locking (Race Condition Protection)

```php
// Enable soft lock during booking
'enableSoftLock' => true,

// Lock duration (seconds)
'softLockDuration' => 300, // 5 minutes

// Lock cleanup interval
'lockCleanupInterval' => 60, // 1 minute
```

## Email Configuration

### Email Templates

```php
'emailTemplates' => [
    // Confirmation email
    'confirmation' => [
        'enabled' => true,
        'subject' => 'Booking Confirmation - {{ reservation.service.title }}',
        'template' => 'booked/emails/confirmation',
    ],

    // Reminder email
    'reminder' => [
        'enabled' => true,
        'hoursBeforeHours' => 24,
        'subject' => 'Reminder: {{ reservation.service.title }} Tomorrow',
        'template' => 'booked/emails/reminder',
    ],

    // Cancellation email
    'cancellation' => [
        'enabled' => true,
        'subject' => 'Booking Cancelled - {{ reservation.service.title }}',
        'template' => 'booked/emails/cancellation',
    ],

    // Admin notification
    'adminNotification' => [
        'enabled' => true,
        'recipients' => ['admin@acme.com'],
        'subject' => 'New Booking: {{ reservation.service.title }}',
        'template' => 'booked/emails/admin-notification',
    ],
],
```

### Email Queue Settings

```php
// Use queue for email sending
'queueEmails' => true,

// Retry failed emails
'retryFailedEmails' => true,

// Max retry attempts
'maxEmailRetries' => 3,

// Retry delay (seconds)
'emailRetryDelay' => 300, // 5 minutes
```

## Calendar Sync Settings

### Google Calendar

```php
'googleCalendar' => [
    'enabled' => true,
    'clientId' => getenv('GOOGLE_CALENDAR_CLIENT_ID'),
    'clientSecret' => getenv('GOOGLE_CALENDAR_CLIENT_SECRET'),
    'redirectUri' => '@web/admin/booked/calendar/callback',

    // Sync direction
    'syncToGoogle' => true,    // Push bookings to Google
    'syncFromGoogle' => true,  // Pull events from Google

    // Sync interval (minutes)
    'syncInterval' => 15,

    // Calendar selection
    'calendar' => 'primary', // or specific calendar ID
],
```

### Microsoft Outlook

```php
'outlookCalendar' => [
    'enabled' => true,
    'clientId' => getenv('OUTLOOK_CALENDAR_CLIENT_ID'),
    'clientSecret' => getenv('OUTLOOK_CALENDAR_CLIENT_SECRET'),
    'tenantId' => getenv('OUTLOOK_TENANT_ID') ?? 'common',
    'redirectUri' => '@web/admin/booked/calendar/callback',

    // Sync settings
    'syncToOutlook' => true,
    'syncFromOutlook' => true,
    'syncInterval' => 15,
],
```

### Sync Behavior

```php
// Block time for external events
'blockExternalEvents' => true,

// Show external events in availability
'showExternalEvents' => false,

// Conflict resolution
'conflictResolution' => 'local', // 'local' or 'remote'

// Sync past events (days)
'syncPastDays' => 0,

// Sync future events (days)
'syncFutureDays' => 90,
```

## Virtual Meeting Settings

### Zoom Configuration

```php
'zoom' => [
    'enabled' => true,
    'accountId' => getenv('ZOOM_ACCOUNT_ID'),
    'clientId' => getenv('ZOOM_CLIENT_ID'),
    'clientSecret' => getenv('ZOOM_CLIENT_SECRET'),

    // Meeting settings
    'autoGenerateMeeting' => true,
    'waitingRoom' => true,
    'joinBeforeHost' => false,
    'muteUponEntry' => true,

    // Meeting duration
    'defaultDuration' => 60, // minutes

    // Recording
    'autoRecording' => 'none', // 'none', 'local', 'cloud'
],
```

### Microsoft Teams Configuration

```php
'teams' => [
    'enabled' => true,
    'clientId' => getenv('TEAMS_CLIENT_ID'),
    'clientSecret' => getenv('TEAMS_CLIENT_SECRET'),
    'tenantId' => getenv('TEAMS_TENANT_ID'),

    // Meeting settings
    'autoGenerateMeeting' => true,
    'lobbyBypass' => 'organization', // 'everyone', 'organization', 'organizer'
    'allowParticipantsToChangeName' => false,
],
```

### Meeting Defaults

```php
// Automatically create virtual meetings
'autoCreateVirtualMeeting' => true,

// Services that should have virtual meetings
'virtualMeetingServices' => [], // empty = all services

// Include meeting link in confirmation email
'includeMeetingLinkInEmail' => true,
```

## Payment Integration

### Craft Commerce Settings

```php
'commerce' => [
    'enabled' => true,

    // Product type for bookings
    'productTypeHandle' => 'bookings',

    // Variant field for service
    'variantField' => 'serviceId',

    // Order status after booking
    'pendingStatusHandle' => 'confirmed',
    'confirmedStatusHandle' => 'confirmed',
    'cancelledStatusHandle' => 'cancelled',

    // Line item description
    'lineItemDescription' => '{{ service.title }} - {{ date|date("F j, Y") }} at {{ time }}',
],
```

### Deposit Settings

```php
// Require deposit
'requireDeposit' => true,

// Deposit type
'depositType' => 'percentage', // 'percentage' or 'fixed'

// Deposit amount
'depositPercentage' => 50, // for percentage
'depositAmount' => 25.00,  // for fixed

// Deposit refund policy
'refundDeposit' => false,
'refundFullAmount' => false,

// Payment deadline
'paymentDeadlineHours' => 24,
```

### Pricing

```php
// Price includes tax
'priceIncludesTax' => true,

// Tax category
'taxCategoryId' => 1,

// Enable dynamic pricing
'enableDynamicPricing' => false,

// Price modifiers
'priceModifiers' => [
    'weekend' => 1.2,    // 20% surcharge
    'evening' => 1.15,   // 15% surcharge
    'holiday' => 1.5,    // 50% surcharge
],
```

## Performance Settings

### Caching

```php
// Enable availability caching
'enableCache' => true,

// Cache duration (seconds)
'cacheDuration' => 3600, // 1 hour

// Cache storage
'cacheComponent' => 'cache', // Yii cache component

// Tag-based cache invalidation
'useTaggedCache' => true,

// Warm cache on save
'warmCacheOnSave' => true,
```

### Database Optimization

```php
// Enable query optimization
'optimizeQueries' => true,

// Use eager loading
'eagerLoadRelations' => true,

// Batch size for bulk operations
'batchSize' => 100,

// Database connection pool
'connectionPoolSize' => 10,
```

### Background Processing

```php
// Use queue for heavy operations
'useQueueForSync' => true,
'useQueueForEmails' => true,
'useQueueForCleanup' => true,

// Queue priority
'queuePriority' => [
    'emails' => 100,
    'sync' => 50,
    'cleanup' => 10,
],
```

## Advanced Configuration

### Custom Fields

```php
// Enable custom fields
'enableCustomFields' => true,

// Custom field layout
'customFieldLayout' => 'default',

// Required custom fields
'requiredCustomFields' => ['notes', 'specialRequests'],
```

### Security

```php
// Rate limiting
'rateLimitEnabled' => true,
'rateLimitAttempts' => 10,
'rateLimitPeriod' => 3600, // 1 hour

// CSRF protection
'enableCsrfValidation' => true,

// API authentication
'apiAuthEnabled' => true,
'apiAuthMethod' => 'bearer', // 'bearer' or 'apikey'

// IP whitelist for API
'apiIpWhitelist' => [],
```

### Logging

```php
// Enable detailed logging
'enableLogging' => true,

// Log level
'logLevel' => 'info', // 'error', 'warning', 'info', 'debug'

// Log components
'logBookings' => true,
'logSync' => true,
'logEmails' => true,
'logErrors' => true,

// Log file path
'logPath' => '@storage/logs/booked.log',
```

### Webhooks

```php
// Enable webhooks
'enableWebhooks' => true,

// Webhook endpoints
'webhooks' => [
    'booking.created' => 'https://api.example.com/webhooks/booking-created',
    'booking.cancelled' => 'https://api.example.com/webhooks/booking-cancelled',
    'booking.confirmed' => 'https://api.example.com/webhooks/booking-confirmed',
],

// Webhook authentication
'webhookSecret' => getenv('WEBHOOK_SECRET'),

// Retry failed webhooks
'retryFailedWebhooks' => true,
'maxWebhookRetries' => 3,
```

### GraphQL

```php
// Enable GraphQL
'enableGraphQL' => true,

// GraphQL schema
'graphQLSchema' => 'public',

// Enable mutations
'enableGraphQLMutations' => true,

// Query depth limit
'graphQLMaxDepth' => 10,

// Query complexity limit
'graphQLMaxComplexity' => 1000,
```

## Control Panel Configuration

Most settings can be configured via the control panel:

1. Navigate to **Settings → Booked**
2. Select the appropriate tab:
   - **General** - Business info, timezone, formats
   - **Booking** - Advance booking, cancellation, capacity
   - **Email** - Templates, notifications, queue settings
   - **Calendar Sync** - Google/Outlook configuration
   - **Virtual Meetings** - Zoom/Teams configuration
   - **Payments** - Commerce integration, deposits
   - **Performance** - Caching, optimization
   - **Advanced** - Custom fields, security, logging

## Configuration Priority

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database settings (control panel)
3. Config file (`config/booked.php`)
4. Environment variables (`.env`)

## Example Complete Configuration

```php
<?php
// config/booked.php

return [
    // General
    'businessName' => 'Acme Spa & Wellness',
    'timezone' => 'America/New_York',
    'dateFormat' => 'Y-m-d',
    'timeFormat' => 'H:i',

    // Booking rules
    'minimumAdvanceBookingHours' => 24,
    'maximumAdvanceBookingDays' => 90,
    'autoConfirm' => true,
    'cancellationDeadlineHours' => 24,

    // Performance
    'enableCache' => true,
    'cacheDuration' => 3600,

    // Calendar sync
    'googleCalendar' => [
        'enabled' => true,
        'clientId' => getenv('GOOGLE_CALENDAR_CLIENT_ID'),
        'clientSecret' => getenv('GOOGLE_CALENDAR_CLIENT_SECRET'),
    ],

    // Virtual meetings
    'enableVirtualMeetings' => true,
    'virtualMeetingProvider' => 'zoom',
    'zoom' => [
        'enabled' => true,
        'accountId' => getenv('ZOOM_ACCOUNT_ID'),
        'clientId' => getenv('ZOOM_CLIENT_ID'),
        'clientSecret' => getenv('ZOOM_CLIENT_SECRET'),
    ],

    // Payments
    'enableCommerce' => true,
    'requireDeposit' => true,
    'depositPercentage' => 50,
];
```

## Next Steps

- [User Guide](USER_GUIDE.md) - Learn how to use Booked
- [Developer Guide](DEVELOPER_GUIDE.md) - Extend and customize
- [Event System](EVENT_SYSTEM.md) - Hook into the booking lifecycle
