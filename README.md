# Booked - Advanced Booking System for Craft CMS

A comprehensive booking and reservation management plugin for Craft CMS, designed with flexibility, performance, and developer experience in mind.

## Features

### Core Booking System
- **Service Management**: Create and manage multiple service types with custom durations, pricing, and availability
- **Employee/Resource Scheduling**: Assign employees to services with individual schedules and locations
- **Multi-Location Support**: Manage bookings across multiple physical locations with timezone handling
- **Flexible Availability**: Define recurring schedules, one-time availability windows, and blackout dates
- **Capacity Management**: Support for group bookings with configurable capacity limits

### Advanced Features
- **Recurring Bookings**: Full recurrence pattern support (RRULE) for daily, weekly, monthly, and yearly schedules
- **Calendar Sync**: Two-way sync with Google Calendar and Microsoft Outlook
- **Virtual Meetings**: Automatic Zoom/Microsoft Teams meeting creation for online appointments
- **Payment Integration**: Native Craft Commerce integration with configurable deposits and payment flows
- **Email Notifications**: Customizable email templates for confirmations, reminders, and cancellations
- **Custom Fields**: Extend bookings with custom field data collection

### Performance & Scalability
- **Intelligent Caching**: Tag-based cache invalidation for optimal performance
- **Database Optimization**: Composite indexes and query optimization for large datasets
- **Background Processing**: Queue-based email sending and calendar sync
- **Soft Locking**: Race condition protection for concurrent bookings
- **Timezone Support**: Automatic timezone conversion for global bookings

### Developer Experience
- **Event System**: Comprehensive event hooks for custom business logic
- **GraphQL Support**: Full GraphQL API for headless implementations
- **RESTful API**: Query and manage bookings programmatically
- **Extensible Architecture**: Service-based design for easy customization
- **Type Safety**: Full property and method type definitions

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later
- MySQL 5.7+ or PostgreSQL 10+
- Composer

## Quick Start

### Installation

```bash
composer require fabian/booked
php craft plugin/install booked
```

### Basic Configuration

1. Navigate to **Settings → Booked** in the Craft control panel
2. Configure your booking settings:
   - Business hours and timezone
   - Email notification settings
   - Payment integration (optional)
   - Calendar sync credentials (optional)

3. Create your first service:
   - Go to **Booked → Services**
   - Click "New Service"
   - Set duration, pricing, and buffer times

4. Add employees/resources:
   - Go to **Booked → Employees**
   - Create employee profiles
   - Assign to services and locations

5. Define availability:
   - Go to **Booked → Schedules**
   - Set recurring weekly schedules
   - Or create one-time availability windows

### Frontend Implementation

Basic booking form example:

```twig
{# Get available services #}
{% set services = craft.booked.services().all() %}

<form action="{{ actionUrl('booked/booking/create') }}" method="post">
    {{ csrfInput() }}

    {# Service selection #}
    <select name="serviceId" required>
        {% for service in services %}
            <option value="{{ service.id }}">
                {{ service.title }} - {{ service.duration }} min - {{ service.price|currency }}
            </option>
        {% endfor %}
    </select>

    {# Date and time selection #}
    <input type="date" name="date" required>

    {# Get available slots via AJAX #}
    <div id="available-slots"></div>

    {# Customer information #}
    <input type="text" name="userName" placeholder="Your Name" required>
    <input type="email" name="userEmail" placeholder="Your Email" required>
    <input type="tel" name="userPhone" placeholder="Your Phone">

    <button type="submit">Book Appointment</button>
</form>
```

Fetch available slots with JavaScript:

```javascript
const serviceId = document.querySelector('[name="serviceId"]').value;
const date = document.querySelector('[name="date"]').value;

fetch(`/actions/booked/availability/get-slots?serviceId=${serviceId}&date=${date}`)
    .then(response => response.json())
    .then(data => {
        const slotsContainer = document.getElementById('available-slots');
        slotsContainer.innerHTML = data.slots.map(slot => `
            <label>
                <input type="radio" name="startTime" value="${slot.time}" required>
                ${slot.time}
            </label>
        `).join('');
    });
```

## Documentation

- [Installation Guide](INSTALLATION.md) - Detailed installation and setup instructions
- [Configuration Guide](CONFIGURATION.md) - Complete configuration reference
- [User Guide](USER_GUIDE.md) - End-user documentation for managing bookings
- [Developer Guide](DEVELOPER_GUIDE.md) - API reference and extension guide
- [Event System](EVENT_SYSTEM.md) - Comprehensive event system documentation

## Key Concepts

### Subtractive Availability Model

Booked uses a subtractive availability model:

```
Available Slots = Working Hours - (Bookings + Buffers + Blackouts + External Events)
```

This approach ensures:
- Accurate slot calculation
- Automatic buffer enforcement
- No double-booking across services
- External calendar integration

### Event-Driven Architecture

Hook into the booking lifecycle with events:

```php
use yii\base\Event;
use fabian\booked\services\BookingService;
use fabian\booked\events\BeforeBookingSaveEvent;

Event::on(
    BookingService::class,
    BookingService::EVENT_BEFORE_BOOKING_SAVE,
    function(BeforeBookingSaveEvent $event) {
        // Custom validation
        if (!$event->reservation->userEmail) {
            $event->isValid = false;
            $event->data['errorMessage'] = 'Email is required';
        }

        // Send to external CRM
        $crm->createLead([
            'name' => $event->reservation->userName,
            'email' => $event->reservation->userEmail,
        ]);
    }
);
```

### GraphQL Support

Query bookings via GraphQL:

```graphql
query {
  reservations(
    bookingDate: "2025-12-26"
    status: "confirmed"
  ) {
    id
    userName
    userEmail
    startTime
    endTime
    service {
      title
      duration
      price
    }
    employee {
      title
      email
    }
  }
}
```

## Support

- **Documentation**: [Full documentation](DEVELOPER_GUIDE.md)
- **Issues**: [GitHub Issues](https://github.com/fabian/booked/issues)
- **Discussions**: [GitHub Discussions](https://github.com/fabian/booked/discussions)

## Roadmap

See [PLAN.md](PLAN.md) for the complete feature roadmap and implementation timeline.

### Completed
- ✅ Core booking system
- ✅ Multi-location support
- ✅ Recurring schedules (RRULE)
- ✅ Calendar sync (Google/Outlook)
- ✅ Virtual meetings (Zoom/Teams)
- ✅ Payment integration (Craft Commerce)
- ✅ Performance optimization
- ✅ Event system

### In Progress
- 🔄 Testing & Documentation (Phase 5.3)

### Planned
- GraphQL mutations
- Advanced reporting
- Mobile app API
- Webhook notifications

## License

Copyright © Fabian. All rights reserved.

## Credits

Developed by Fabian for Craft CMS.

Built with:
- [Craft CMS](https://craftcms.com)
- [Yii Framework](https://www.yiiframework.com)
- [Google Calendar API](https://developers.google.com/calendar)
- [Microsoft Graph API](https://developer.microsoft.com/graph)
