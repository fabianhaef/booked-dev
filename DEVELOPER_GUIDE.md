# Developer Guide - Booked

Comprehensive guide for developers extending and customizing the Booked plugin.

## Table of Contents

- [Architecture](#architecture)
- [Services API](#services-api)
- [Event System](#event-system)
- [Element Types](#element-types)
- [GraphQL API](#graphql-api)
- [Template Variables](#template-variables)
- [Custom Extensions](#custom-extensions)
- [Testing](#testing)
- [Best Practices](#best-practices)

## Architecture

### Service-Based Design

Booked uses a service-based architecture for separation of concerns:

```
fabian\booked\
├── elements/          # Element types (Reservation, Service, Employee, etc.)
├── services/          # Business logic services
├── controllers/       # HTTP controllers
├── events/            # Event classes
├── records/           # Active Record models
├── queue/             # Background jobs
└── helpers/           # Utility classes
```

### Core Services

| Service | Purpose |
|---------|---------|
| `BookingService` | Create, update, cancel bookings |
| `AvailabilityService` | Calculate available time slots |
| `CalendarSyncService` | Sync with external calendars |
| `VirtualMeetingService` | Generate meeting links |
| `ReminderService` | Send automated reminders |
| `RecurrenceService` | Handle recurring patterns |
| `BlackoutDateService` | Manage unavailable dates |

Access services via the plugin instance:

```php
use fabian\booked\Booked;

$booking = Booked::getInstance()->booking;
$availability = Booked::getInstance()->availability;
$calendar = Booked::getInstance()->calendarSync;
```

## Services API

### BookingService

Create and manage bookings.

#### Create Booking

```php
use fabian\booked\Booked;

$bookingService = Booked::getInstance()->booking;

$reservation = $bookingService->createReservation([
    'serviceId' => 1,
    'employeeId' => 2,
    'locationId' => 1,
    'bookingDate' => '2025-12-26',
    'startTime' => '14:00',
    'endTime' => '15:00',
    'userName' => 'John Doe',
    'userEmail' => 'john@example.com',
    'userPhone' => '+1-555-0123',
    'notes' => 'First time customer',
    'quantity' => 1,
    'source' => 'web',
]);

if ($reservation) {
    echo "Booking created: {$reservation->id}";
} else {
    echo "Booking failed";
}
```

#### Cancel Booking

```php
$success = $bookingService->cancelReservation(
    $reservation,
    'Customer requested cancellation',
    true // Send notification
);
```

#### Update Booking

```php
$reservation->startTime = '15:00';
$reservation->endTime = '16:00';

$success = Craft::$app->elements->saveElement($reservation);
```

### AvailabilityService

Calculate available time slots.

#### Get Available Slots

```php
use fabian\booked\Booked;

$availabilityService = Booked::getInstance()->availability;

$slots = $availabilityService->getAvailableSlots(
    date: '2025-12-26',
    employeeId: 2,           // Optional
    locationId: 1,           // Optional
    serviceId: 1,            // Optional
    requestedQuantity: 1,    // Optional
    userTimezone: 'America/New_York' // Optional
);

foreach ($slots as $slot) {
    echo "{$slot['time']} - {$slot['endTime']} ({$slot['employeeName']})\n";
}
```

#### Check Slot Availability

```php
$isAvailable = $availabilityService->isSlotAvailable(
    date: '2025-12-26',
    startTime: '14:00',
    endTime: '15:00',
    employeeId: 2,
    serviceId: 1,
    requestedQuantity: 1
);
```

### CalendarSyncService

Sync with external calendars.

#### Get Authorization URL

```php
use fabian\booked\elements\Employee;

$employee = Employee::find()->id(2)->one();
$authUrl = Booked::getInstance()->calendarSync->getAuthUrl(
    $employee,
    'google' // or 'outlook'
);

// Redirect user to $authUrl for OAuth
```

#### Handle OAuth Callback

```php
$success = Booked::getInstance()->calendarSync->handleCallback(
    $stateToken, // From query parameter
    $code        // From query parameter
);
```

#### Sync to External Calendar

```php
use fabian\booked\elements\Reservation;

$reservation = Reservation::find()->id(123)->one();

$success = Booked::getInstance()->calendarSync->syncToExternal($reservation);
```

#### Sync from External Calendar

```php
$count = Booked::getInstance()->calendarSync->syncFromExternal(
    $employee,
    'google'
);

echo "Synced {$count} events";
```

### VirtualMeetingService

Create virtual meeting links.

#### Generate Meeting

```php
$virtualMeeting = Booked::getInstance()->virtualMeeting;

$meetingData = $virtualMeeting->createMeeting(
    reservation: $reservation,
    provider: 'zoom', // or 'teams'
    options: [
        'waitingRoom' => true,
        'muteUponEntry' => true,
    ]
);

if ($meetingData) {
    echo "Join URL: {$meetingData['joinUrl']}";
    echo "Meeting ID: {$meetingData['meetingId']}";
}
```

### RecurrenceService

Handle recurring patterns.

#### Parse RRULE

```php
$recurrence = Booked::getInstance()->recurrence;

$occurrences = $recurrence->getOccurrences(
    rruleString: 'FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=10',
    startDate: '2025-12-26',
    endDate: '2026-01-26',
    limit: 50
);

foreach ($occurrences as $date) {
    echo $date->format('Y-m-d') . "\n";
}
```

#### Check if Date Occurs

```php
$occurs = $recurrence->occursOn(
    rrule: 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
    date: '2025-12-26',
    startDate: new DateTime('2025-01-01')
);
```

## Event System

Booked fires events at critical points in the booking lifecycle. See [EVENT_SYSTEM.md](EVENT_SYSTEM.md) for complete documentation.

### Available Events

**BookingService Events:**
- `EVENT_BEFORE_BOOKING_SAVE` - Before saving a booking
- `EVENT_AFTER_BOOKING_SAVE` - After booking is saved
- `EVENT_BEFORE_BOOKING_CANCEL` - Before canceling a booking
- `EVENT_AFTER_BOOKING_CANCEL` - After booking is canceled

**CalendarSyncService Events:**
- `EVENT_BEFORE_CALENDAR_SYNC` - Before syncing to external calendar
- `EVENT_AFTER_CALENDAR_SYNC` - After calendar sync completes

**AvailabilityService Events:**
- `EVENT_BEFORE_AVAILABILITY_CHECK` - Before calculating availability
- `EVENT_AFTER_AVAILABILITY_CHECK` - After availability is calculated

### Event Handler Example

```php
use yii\base\Event;
use fabian\booked\services\BookingService;
use fabian\booked\events\BeforeBookingSaveEvent;

Event::on(
    BookingService::class,
    BookingService::EVENT_BEFORE_BOOKING_SAVE,
    function(BeforeBookingSaveEvent $event) {
        // Access event data
        $reservation = $event->reservation;
        $isNew = $event->isNew;
        $bookingData = $event->bookingData;

        // Custom validation
        if ($reservation->userEmail && !filter_var($reservation->userEmail, FILTER_VALIDATE_EMAIL)) {
            $event->isValid = false;
            $event->data['errorMessage'] = 'Invalid email address';
            return;
        }

        // Send to external CRM
        $crm = new CRMService();
        $crm->createLead([
            'name' => $reservation->userName,
            'email' => $reservation->userEmail,
            'phone' => $reservation->userPhone,
        ]);

        // Modify reservation data
        $reservation->notes = 'CRM Lead ID: ' . $crm->getLeadId();

        // Log to custom system
        Craft::info("New booking created by {$reservation->userName}", 'custom-booking-log');
    }
);
```

### Register Events in Plugin

Create a custom module or plugin:

```php
// modules/CustomBookingModule.php
namespace modules;

use Craft;
use yii\base\Event;
use yii\base\Module as BaseModule;
use fabian\booked\services\BookingService;
use fabian\booked\events\AfterBookingSaveEvent;

class CustomBookingModule extends BaseModule
{
    public function init()
    {
        parent::init();

        // Register event handlers
        Event::on(
            BookingService::class,
            BookingService::EVENT_AFTER_BOOKING_SAVE,
            [$this, 'handleBookingSaved']
        );
    }

    public function handleBookingSaved(AfterBookingSaveEvent $event)
    {
        if ($event->success && $event->isNew) {
            // Send Slack notification
            $this->sendSlackNotification($event->reservation);

            // Update analytics
            $this->trackBookingEvent($event->reservation);
        }
    }

    private function sendSlackNotification($reservation)
    {
        // Implementation
    }

    private function trackBookingEvent($reservation)
    {
        // Implementation
    }
}
```

Bootstrap in `config/app.php`:

```php
return [
    'modules' => [
        'custom-booking' => \modules\CustomBookingModule::class,
    ],
    'bootstrap' => ['custom-booking'],
];
```

## Element Types

### Reservation Element

```php
use fabian\booked\elements\Reservation;

// Query bookings
$reservations = Reservation::find()
    ->bookingDate('2025-12-26')
    ->status('confirmed')
    ->employeeId(2)
    ->all();

// Access properties
foreach ($reservations as $reservation) {
    echo $reservation->userName;
    echo $reservation->userEmail;
    echo $reservation->startTime;
    echo $reservation->endTime;

    // Related elements
    $service = $reservation->getService();
    $employee = $reservation->getEmployee();
    $location = $reservation->getLocation();
}

// Create new reservation
$reservation = new Reservation();
$reservation->serviceId = 1;
$reservation->employeeId = 2;
$reservation->bookingDate = '2025-12-26';
$reservation->startTime = '14:00';
$reservation->endTime = '15:00';
$reservation->userName = 'John Doe';
$reservation->userEmail = 'john@example.com';

$success = Craft::$app->elements->saveElement($reservation);
```

### Service Element

```php
use fabian\booked\elements\Service;

// Query services
$services = Service::find()
    ->enabled()
    ->orderBy('title ASC')
    ->all();

// Access properties
foreach ($services as $service) {
    echo $service->title;
    echo $service->duration;
    echo $service->price;
    echo $service->bufferBefore;
    echo $service->bufferAfter;

    // Related elements
    $employees = $service->getEmployees();
}
```

### Employee Element

```php
use fabian\booked\elements\Employee;

// Query employees
$employees = Employee::find()
    ->locationId(1)
    ->status('active')
    ->all();

// Access properties
foreach ($employees as $employee) {
    echo $employee->title;
    echo $employee->email;

    // Related elements
    $location = $employee->getLocation();
    $services = $employee->getServices();
}
```

### Element Query Methods

```php
// Reservation queries
Reservation::find()
    ->bookingDate('2025-12-26')
    ->startTime('14:00')
    ->endTime('15:00')
    ->serviceId(1)
    ->employeeId(2)
    ->locationId(1)
    ->status('confirmed') // or ['confirmed', 'pending']
    ->userId(10)
    ->userEmail('john@example.com')
    ->limit(10)
    ->orderBy('bookingDate DESC, startTime ASC')
    ->all();

// Service queries
Service::find()
    ->enabled()
    ->price(['>=', 50])
    ->duration(['<=', 60])
    ->all();

// Employee queries
Employee::find()
    ->status('active')
    ->locationId(1)
    ->serviceId(1) // Employees offering this service
    ->all();
```

## GraphQL API

### Queries

```graphql
# Get all services
query GetServices {
  services {
    id
    title
    duration
    price
    description
    enabled
    employees {
      id
      title
      email
    }
  }
}

# Get available slots
query GetAvailableSlots($date: String!, $serviceId: Int) {
  availableSlots(date: $date, serviceId: $serviceId) {
    time
    endTime
    employeeId
    employeeName
    duration
  }
}

# Get reservations
query GetReservations($date: String, $status: [String]) {
  reservations(bookingDate: $date, status: $status) {
    id
    bookingDate
    startTime
    endTime
    userName
    userEmail
    status
    service {
      title
      price
    }
    employee {
      title
    }
  }
}

# Get employee schedules
query GetEmployeeSchedules($employeeId: Int!) {
  schedules(employeeId: $employeeId) {
    id
    dayOfWeek
    startTime
    endTime
    enabled
    employees {
      title
    }
  }
}
```

### Mutations (if enabled)

```graphql
mutation CreateBooking($input: CreateReservationInput!) {
  createReservation(input: $input) {
    id
    bookingDate
    startTime
    userName
    userEmail
  }
}

mutation CancelBooking($id: Int!, $reason: String) {
  cancelReservation(id: $id, reason: $reason) {
    success
    message
  }
}
```

### Custom GraphQL Resolvers

```php
// Custom GraphQL type
use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\Type;

class AvailabilityStatsType extends ObjectType
{
    public function getName(): string
    {
        return 'AvailabilityStats';
    }

    public function getFieldDefinitions(): array
    {
        return [
            'totalSlots' => Type::int(),
            'availableSlots' => Type::int(),
            'bookedSlots' => Type::int(),
            'utilizationRate' => Type::float(),
        ];
    }
}
```

## Template Variables

### Craft Template Tags

```twig
{# Get services #}
{% set services = craft.booked.services().all() %}

{# Get available slots #}
{% set slots = craft.booked.availability.getSlots({
    date: '2025-12-26',
    serviceId: 1,
    employeeId: 2
}) %}

{# Get reservations #}
{% set reservations = craft.booked.reservations()
    .bookingDate('2025-12-26')
    .status('confirmed')
    .all() %}

{# Get employees #}
{% set employees = craft.booked.employees()
    .locationId(1)
    .all() %}

{# Get locations #}
{% set locations = craft.booked.locations().all() %}
```

### Custom Template Variables

Register custom template variables:

```php
use Craft;
use yii\base\Event;
use craft\web\twig\variables\CraftVariable;

Event::on(
    CraftVariable::class,
    CraftVariable::EVENT_INIT,
    function(Event $event) {
        $variable = $event->sender;
        $variable->set('customBooking', CustomBookingVariable::class);
    }
);
```

Use in templates:

```twig
{% set stats = craft.customBooking.getBookingStats('2025-12-26') %}
Total bookings: {{ stats.total }}
Revenue: {{ stats.revenue|currency }}
```

## Custom Extensions

### Custom Booking Source

Track where bookings come from:

```php
namespace modules\booking;

use fabian\booked\events\BeforeBookingSaveEvent;

class BookingSourceTracker
{
    public static function track(BeforeBookingSaveEvent $event)
    {
        $request = Craft::$app->request;

        // Track referrer
        if ($referrer = $request->referrer) {
            $event->reservation->setFieldValue('referrerUrl', $referrer);
        }

        // Track UTM parameters
        $utmSource = $request->getQueryParam('utm_source');
        $utmMedium = $request->getQueryParam('utm_medium');
        $utmCampaign = $request->getQueryParam('utm_campaign');

        if ($utmSource) {
            $event->reservation->setFieldValue('utmSource', $utmSource);
            $event->reservation->setFieldValue('utmMedium', $utmMedium);
            $event->reservation->setFieldValue('utmCampaign', $utmCampaign);
        }
    }
}
```

### Dynamic Pricing

Implement time-based pricing:

```php
namespace modules\booking;

use fabian\booked\events\BeforeBookingSaveEvent;

class DynamicPricing
{
    public static function apply(BeforeBookingSaveEvent $event)
    {
        $reservation = $event->reservation;
        $service = $reservation->getService();

        if (!$service) {
            return;
        }

        $basePrice = $service->price;
        $multiplier = 1.0;

        // Weekend surcharge
        $dayOfWeek = date('w', strtotime($reservation->bookingDate));
        if (in_array($dayOfWeek, [0, 6])) {
            $multiplier = 1.2; // 20% surcharge
        }

        // Evening surcharge
        $hour = (int) substr($reservation->startTime, 0, 2);
        if ($hour >= 18) {
            $multiplier *= 1.15; // Additional 15%
        }

        // Peak season
        $month = date('n', strtotime($reservation->bookingDate));
        if (in_array($month, [6, 7, 8])) { // Summer
            $multiplier *= 1.1; // Additional 10%
        }

        // Apply dynamic price
        $dynamicPrice = $basePrice * $multiplier;
        $reservation->setFieldValue('dynamicPrice', $dynamicPrice);

        Craft::info("Dynamic pricing: {$basePrice} -> {$dynamicPrice} (multiplier: {$multiplier})", __METHOD__);
    }
}
```

### Custom Notification Channels

Send notifications via SMS, Slack, etc.:

```php
namespace modules\booking;

use fabian\booked\events\AfterBookingSaveEvent;

class CustomNotifications
{
    public static function send(AfterBookingSaveEvent $event)
    {
        if (!$event->success || !$event->isNew) {
            return;
        }

        $reservation = $event->reservation;

        // Send SMS
        self::sendSMS($reservation);

        // Send Slack notification
        self::sendSlack($reservation);

        // Send webhook
        self::sendWebhook($reservation);
    }

    private static function sendSMS($reservation)
    {
        $twilio = new \Twilio\Rest\Client(
            getenv('TWILIO_SID'),
            getenv('TWILIO_TOKEN')
        );

        $twilio->messages->create(
            $reservation->userPhone,
            [
                'from' => getenv('TWILIO_FROM_NUMBER'),
                'body' => "Booking confirmed for {$reservation->bookingDate} at {$reservation->startTime}. Details: {$reservation->getConfirmationUrl()}"
            ]
        );
    }

    private static function sendSlack($reservation)
    {
        $webhook = getenv('SLACK_WEBHOOK_URL');

        $data = [
            'text' => "New Booking: {$reservation->service->title}",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*New Booking Received*\n" .
                                  "Customer: {$reservation->userName}\n" .
                                  "Service: {$reservation->service->title}\n" .
                                  "Date: {$reservation->bookingDate} at {$reservation->startTime}\n" .
                                  "Employee: {$reservation->employee->title}"
                    ]
                ]
            ]
        ];

        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private static function sendWebhook($reservation)
    {
        $webhookUrl = getenv('CUSTOM_WEBHOOK_URL');

        if (!$webhookUrl) {
            return;
        }

        $data = [
            'event' => 'booking.created',
            'timestamp' => time(),
            'data' => [
                'id' => $reservation->id,
                'service' => $reservation->service->title,
                'customer' => [
                    'name' => $reservation->userName,
                    'email' => $reservation->userEmail,
                    'phone' => $reservation->userPhone,
                ],
                'booking' => [
                    'date' => $reservation->bookingDate,
                    'startTime' => $reservation->startTime,
                    'endTime' => $reservation->endTime,
                ],
            ],
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . hash_hmac('sha256', json_encode($data), getenv('WEBHOOK_SECRET'))
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
```

## Testing

### Unit Tests

```php
namespace tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\elements\Service;
use fabian\booked\elements\Employee;

class AvailabilityServiceTest extends Unit
{
    private AvailabilityService $service;

    protected function _before()
    {
        $this->service = new AvailabilityService();
    }

    public function testGetAvailableSlots()
    {
        $slots = $this->service->getAvailableSlots(
            date: '2025-12-26',
            serviceId: 1
        );

        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
        $this->assertArrayHasKey('time', $slots[0]);
    }

    public function testIsSlotAvailable()
    {
        $available = $this->service->isSlotAvailable(
            date: '2025-12-26',
            startTime: '14:00',
            endTime: '15:00',
            serviceId: 1
        );

        $this->assertIsBool($available);
    }
}
```

### Integration Tests

```php
namespace tests\integration;

use Codeception\Test\Unit;
use fabian\booked\Booked;

class BookingFlowTest extends Unit
{
    public function testCompleteBookingFlow()
    {
        // 1. Check availability
        $slots = Booked::getInstance()->availability->getAvailableSlots(
            date: '2025-12-26',
            serviceId: 1
        );

        $this->assertNotEmpty($slots);

        // 2. Create booking
        $reservation = Booked::getInstance()->booking->createReservation([
            'serviceId' => 1,
            'employeeId' => $slots[0]['employeeId'],
            'bookingDate' => '2025-12-26',
            'startTime' => $slots[0]['time'],
            'endTime' => $slots[0]['endTime'],
            'userName' => 'Test User',
            'userEmail' => 'test@example.com',
        ]);

        $this->assertNotNull($reservation);
        $this->assertNotNull($reservation->id);

        // 3. Verify slot is no longer available
        $slotsAfter = Booked::getInstance()->availability->getAvailableSlots(
            date: '2025-12-26',
            serviceId: 1
        );

        $this->assertLessThan(count($slots), count($slotsAfter));

        // 4. Cancel booking
        $cancelled = Booked::getInstance()->booking->cancelReservation(
            $reservation,
            'Test cancellation'
        );

        $this->assertTrue($cancelled);
    }
}
```

## Best Practices

### 1. Use Events for Custom Logic

Don't modify core plugin files. Use events instead:

```php
// ❌ Bad
class BookingService extends Component
{
    public function createReservation(array $data): ?Reservation
    {
        // Modified core method
        $this->sendToCustomCRM($data); // Don't do this
        // ...
    }
}

// ✅ Good
Event::on(
    BookingService::class,
    BookingService::EVENT_AFTER_BOOKING_SAVE,
    function($event) {
        $this->sendToCustomCRM($event->reservation);
    }
);
```

### 2. Validate Input

Always validate user input:

```php
Event::on(
    BookingService::class,
    BookingService::EVENT_BEFORE_BOOKING_SAVE,
    function(BeforeBookingSaveEvent $event) {
        // Validate email
        if (!filter_var($event->reservation->userEmail, FILTER_VALIDATE_EMAIL)) {
            $event->isValid = false;
            $event->data['errorMessage'] = 'Invalid email';
            return;
        }

        // Validate phone
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $event->reservation->userPhone)) {
            $event->isValid = false;
            $event->data['errorMessage'] = 'Invalid phone number';
            return;
        }
    }
);
```

### 3. Handle Errors Gracefully

```php
try {
    $reservation = Booked::getInstance()->booking->createReservation($data);

    if (!$reservation) {
        Craft::error('Booking creation failed', 'booking');
        return $this->asJson(['success' => false, 'error' => 'Unable to create booking']);
    }

    return $this->asJson(['success' => true, 'id' => $reservation->id]);

} catch (\Exception $e) {
    Craft::error('Booking error: ' . $e->getMessage(), 'booking');
    return $this->asJson(['success' => false, 'error' => 'An error occurred']);
}
```

### 4. Optimize Queries

Use eager loading for related elements:

```php
// ❌ Bad (N+1 problem)
$reservations = Reservation::find()->all();
foreach ($reservations as $reservation) {
    echo $reservation->getService()->title; // Extra query per reservation
}

// ✅ Good
$reservations = Reservation::find()
    ->with(['service', 'employee', 'location'])
    ->all();

foreach ($reservations as $reservation) {
    echo $reservation->service->title; // No extra queries
}
```

### 5. Use Caching

Cache expensive operations:

```php
$cacheKey = 'availability:' . $date . ':' . $serviceId;
$slots = Craft::$app->cache->getOrSet($cacheKey, function() use ($date, $serviceId) {
    return Booked::getInstance()->availability->getAvailableSlots($date, null, null, $serviceId);
}, 3600); // Cache for 1 hour
```

## Resources

- [Event System Documentation](EVENT_SYSTEM.md) - Complete event reference
- [Configuration Guide](CONFIGURATION.md) - All configuration options
- [Craft CMS Documentation](https://craftcms.com/docs) - Craft CMS reference
- [GitHub Repository](https://github.com/fabian/booked) - Source code

## Support

- [GitHub Issues](https://github.com/fabian/booked/issues) - Bug reports and feature requests
- [GitHub Discussions](https://github.com/fabian/booked/discussions) - Community support
- [Craft Discord](https://craftcms.com/discord) - Real-time chat (#booked channel)
