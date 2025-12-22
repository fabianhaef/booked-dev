# Booked Plugin - Complete Implementation Plan

A comprehensive booking system for Craft CMS - an alternative to WordPress "Bookly"

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current Status](#current-status)
3. [Current State Analysis](#current-state-analysis)
4. [Implementation Strategy](#implementation-strategy)
5. [Commercial Features](#commercial-features)
6. [Migration Notes](#migration-notes)
7. [Timeline & Success Criteria](#timeline--success-criteria)

---

## Executive Summary

This document outlines the complete implementation plan for the "booked" Craft CMS plugin that serves as an alternative to WordPress "Bookly". The plugin has been converted from a module structure to a proper Craft CMS plugin and is ready for incremental feature development.

**Plugin Status:** ✅ Clean plugin structure in place, ready to build features incrementally

**Namespace:** `fabian\booked`

**Package:** `fabian/craft-booked`

---

## Current Status

✅ **Plugin is now a clean slate** - ready to build from scratch

The plugin class (`src/Booked.php`) is minimal and functional. All old code has been moved to `_inspiration/` for reference.

### Structure

```
plugins/booked/
├── src/
│   └── Booked.php          # Main plugin class (minimal, working)
├── _inspiration/           # Old code for reference (not loaded)
│   ├── controllers/        # Old controllers
│   ├── elements/          # Old elements
│   ├── services/          # Old services
│   └── ...                # Other old files
├── composer.json          # Plugin metadata
└── PLAN.md               # This file
```

### What's Active

- ✅ Plugin class extends `craft\base\Plugin`
- ✅ Plugin can be installed and activated
- ✅ Template roots registered
- ✅ Controller namespace set
- ✅ Namespace updated to `fabian\booked`

### What's Commented Out (Ready to Uncomment)

All features are commented out in `src/Booked.php` with clear markers:

- Services registration
- Element types registration
- CP routes
- Site routes
- Template variables
- CP navigation
- Settings

### Next Steps

1. **Start with core elements** - Uncomment and implement element types one by one
2. **Add services** - Uncomment service registration as needed
3. **Add routes** - Uncomment routes as controllers are created
4. **Reference old code** - Check `_inspiration/` directory for implementation ideas

---

## Current State Analysis

### What Exists (From Previous Module)

#### ✅ Core Architecture
- **Module Structure**: Previously implemented as a Craft module (`modules\booking\BookingModule`) - **NOW CONVERTED TO PLUGIN**
- **Element Types**: Four custom elements exist in `_inspiration/`:
  - `Reservation` - Booking/appointment records
  - `Availability` - Time slot availability (recurring and event-based)
  - `BookingVariation` - Service variations with capacity management
  - `BlackoutDate` - Date exclusions/blackouts
- **Services**: Three core services exist in `_inspiration/`:
  - `AvailabilityService` - Availability calculation and slot generation
  - `BookingService` - Booking creation, validation, and management
  - `BlackoutDateService` - Blackout date management
- **Database Schema**: Custom tables for all entities with proper relationships
- **Migrations**: Comprehensive migration system in place (moved to `_inspiration/`)

#### ✅ Basic Features Implemented (Available in `_inspiration/`)
1. **Booking Management**
   - Frontend booking form with multi-step validation
   - Token-based booking management (view, cancel, reschedule)
   - Backend booking management (CRUD operations)
   - Status management (confirmed, pending, cancelled)
   - Rate limiting (email and IP-based)

2. **Availability System**
   - Recurring availability by day of week
   - Event-specific availability dates
   - Automatic slot generation based on duration
   - Buffer time between appointments
   - Capacity management per variation
   - Quantity-based bookings

3. **Communication**
   - Email notifications (confirmation, cancellation, status change)
   - Queue-based email sending
   - Owner notification emails
   - Customizable email templates

4. **User Interface**
   - Control Panel sections for all entities
   - Frontend booking form
   - Booking management pages (public-facing)
   - Template variables (`craft.booking`)

5. **Data Integrity**
   - Mutex locking for race condition prevention
   - Database transactions
   - Unique constraint on active bookings
   - Validation rules

### What's Missing (To Be Implemented)

#### ❌ Core Entity Types (from Original Plan)
- **Service Element**: No dedicated Service element type
  - Currently: `BookingVariation` serves this purpose but lacks full Service features
  - Missing: `duration`, `bufferBefore`, `bufferAfter`, `price` as primary attributes
- **Employee Element**: Not implemented
  - Missing: `userId` (FK), `locationId` (FK), `bio`, `specialties`
  - Missing: Employee-specific schedules
- **Location Element**: Not implemented
  - Missing: Location management
  - Missing: Multi-location support
- **Schedule Element**: Not implemented
  - Missing: Employee schedule management
  - Missing: Day-of-week scheduling

#### ❌ Advanced Features
1. **Recurrence Engine**
   - Missing: RFC 5545 recurrence support (`rlanvin/php-rrule`)
   - Missing: Recurring event series management
   - Missing: Series modification (edit all future instances)

2. **Calendar Synchronization**
   - Missing: Google Calendar OAuth 2.0 integration
   - Missing: Microsoft Outlook OAuth 2.0 integration
   - Missing: Two-way calendar sync
   - Missing: Webhook support for real-time updates
   - Missing: Token persistence for employees

3. **Virtual Meetings**
   - Missing: Zoom integration
   - Missing: Google Meet integration
   - Missing: Automatic meeting creation on booking
   - Missing: Join URL storage in appointments

4. **Commerce Integration**
   - Missing: Craft Commerce `Purchasable` interface implementation
   - Missing: Payment gateway integration
   - Missing: Tax and discount handling
   - Missing: Order lifecycle integration

5. **Advanced Frontend**
   - Missing: Alpine.js integration
   - Missing: Wizard view mode
   - Missing: Catalog view mode
   - Missing: Direct search mode
   - Missing: Real-time availability updates without page reload

6. **Performance & Scalability**
   - Missing: Availability cache system
   - Missing: Background processing for large datasets
   - Missing: Soft lock mechanism (15-minute temporary reservations)
   - Missing: Garbage collection for expired locks

7. **Developer Experience**
   - Missing: Comprehensive event system (EVENT_BEFORE_BOOKING_SAVE, etc.)
   - Missing: Project Config synchronization
   - Missing: Field layouts for custom fields
   - Missing: Developer API documentation

8. **Administrative Features**
   - Missing: Central calendar view in CP
   - Missing: KPI dashboard
   - Missing: Revenue reports
   - Missing: Staff schedule management interface

---

## Implementation Strategy

### Phase 1: Plugin Foundation (Week 1)

#### 1.1 Plugin Structure ✅ COMPLETED

**Status:** ✅ Done
- ✅ Plugin structure created (`src/` directory)
- ✅ `composer.json` with plugin metadata
- ✅ Main plugin class `src/Booked.php` extending `craft\base\Plugin`
- ✅ Namespace updated to `fabian\booked`
- ✅ Old code moved to `_inspiration/` for reference

#### 1.2 Create Missing Core Element Types

**Service Element:**
```php
// src/elements/Service.php
- Extend craft\base\Element
- Properties: duration, bufferBefore, bufferAfter, price
- Custom table: {{%booked_services}}
- Field layouts support
- Relationship to Availability
```

**Employee Element:**
```php
// src/elements/Employee.php
- Extend craft\base\Element
- Properties: userId (FK to User), locationId (FK), bio, specialties
- Custom table: {{%booked_employees}}
- Field layouts support
- Relationship to Availability and Reservations
```

**Location Element:**
```php
// src/elements/Location.php
- Extend craft\base\Element
- Properties: address, timezone, contactInfo
- Custom table: {{%booked_locations}}
- Field layouts support
- Relationship to Employees and Availability
```

**Schedule Element:**
```php
// src/elements/Schedule.php
- Extend craft\base\Element
- Properties: employeeId (FK), dayOfWeek, startTime, endTime
- Custom table: {{%booked_schedules}}
- Relationship to Employee
```

**Tasks:**
1. Create element classes for Service, Employee, Location, Schedule
2. Create corresponding Record classes
3. Create ElementQuery classes
4. Create migrations for new tables
5. Update Availability to link to Service instead of direct variation
6. Update Reservation to link to Employee and Location
7. Create CP controllers for new elements
8. Create CP templates for new elements
9. Update BookingService to use new relationships

**Deliverables:**
- ✅ Service, Employee, Location, Schedule elements created
- ✅ Database schema updated
- ✅ CP interfaces for all new elements
- ✅ Relationships properly established

### Phase 2: Logic Engine and Recurrence Framework (Week 2)

#### 2.1 The Subtractive Availability Engine

The core scheduling logic will follow a subtractive model. The system identifies a staff member's base working hours and progressively subtracts existing appointments, buffer times, and holidays. The resulting set of available windows is then divided by the service duration to generate bookable time slots.

Mathematically, the availability for a given window W can be defined as:

```
Availability(W) = WorkingHours(W) \ (Bookings(W) ∪ Buffers(W) ∪ Exclusions(W))
```

**Tasks:**
1. Refactor AvailabilityService:
   - Implement subtractive availability model
   - Support employee-specific availability
   - Support location-specific availability
   - Support service-specific availability

2. Implement availability cache:
   - Cache pre-calculated availability windows
   - Cache key: `availability_{date}_{employeeId}_{serviceId}`
   - TTL: 1 hour
   - Invalidate on booking creation/cancellation

3. Create AvailabilityCacheService:
   ```php
   // src/services/AvailabilityCacheService.php
   - Get cached availability
   - Set cached availability
   - Invalidate cache
   - Warm cache for popular dates
   ```

4. Update slot generation:
   - Support multiple employees
   - Support multiple locations
   - Support service-specific durations
   - Handle buffer times per service

**Deliverables:**
- ✅ Enhanced availability calculation
- ✅ Availability caching system
- ✅ Multi-employee/location support
- ✅ Service-specific availability

#### 2.2 Implementing RFC 5545 Recurrence

To match the robust recurrence features of Amelia and Bookly, the system will integrate the `rlanvin/php-rrule` library. This allows the system to store a single "Recurring Event" element with an RRULE string, which is then expanded into individual occurrences during query execution. This avoids database bloat and ensures that changes to a series (e.g., "every Tuesday") are applied instantly to all future instances.

**Tasks:**
1. Install `rlanvin/php-rrule` package:
   ```bash
   composer require rlanvin/php-rrule
   ```

2. Create RecurrenceService:
   ```php
   // src/services/RecurrenceService.php
   - Parse RRULE strings
   - Generate occurrence dates
   - Handle recurrence modifications
   - Expand series into individual occurrences
   ```

3. Update Availability element:
   - Add `rrule` property (text field)
   - Add `recurrenceType` property (enum: none, daily, weekly, monthly, custom)
   - Add `recurrenceEndDate` property
   - Store RRULE string in database

4. Create recurrence UI in CP:
   - Recurrence builder form
   - Preview of generated occurrences
   - Edit series options (this instance, all future, all)

5. Update AvailabilityService:
   - Expand recurring availability into date-specific occurrences
   - Handle recurrence exceptions
   - Cache expanded occurrences

6. Create migration:
   - Add `rrule` column to availability table
   - Add `recurrenceType` column
   - Add `recurrenceEndDate` column

**Deliverables:**
- ✅ RFC 5545 recurrence support
- ✅ Recurrence builder UI
- ✅ Series expansion working
- ✅ Series modification options

#### 2.3 Timezone and Localization Management

To support global customers, the system must automatically detect and store timezones. In Craft, this is handled by converting all database timestamps to UTC and using the intl PHP extension to format the output based on the user's session data or site settings.

**Tasks:**
1. Implement timezone detection
2. Store timezone in user session
3. Convert all times to UTC for storage
4. Format times based on user timezone for display

**Deliverables:**
- ✅ Timezone support
- ✅ Automatic timezone detection
- ✅ UTC storage with local display

#### 2.4 Soft Lock Mechanism

A common failure in scheduling systems occurs when two users attempt to book the same time slot simultaneously. The implementation must include a "Soft Lock" mechanism:

- **Validation**: When a user selects a slot, the system verifies availability.
- **Reservation**: The system creates a temporary record with a "locked" status for 15 minutes.
- **Finalization**: Once the payment is confirmed, the status is updated to "confirmed."
- **Garbage Collection**: A background task (Craft Queue) periodically deletes expired "locked" records to free up the time slots.

**Tasks:**
1. Create SoftLock model and record:
   ```php
   // src/models/SoftLock.php
   // src/records/SoftLockRecord.php
   - Properties: token, date, startTime, endTime, variationId, expiresAt
   - Table: {{%booked_soft_locks}}
   ```

2. Create SoftLockService:
   ```php
   // src/services/SoftLockService.php
   - Create lock (15-minute expiration)
   - Check if slot is locked
   - Release lock
   - Cleanup expired locks (queue job)
   ```

3. Create queue job for cleanup:
   ```php
   // src/queue/jobs/CleanupSoftLocksJob.php
   - Delete expired locks
   - Run every 5 minutes
   ```

4. Update BookingService:
   - Check for soft locks before booking
   - Create soft lock when user selects slot
   - Release lock on booking confirmation
   - Release lock on expiration

5. Update frontend:
   - Create soft lock when slot selected
   - Show countdown timer
   - Refresh availability if lock expires

**Deliverables:**
- ✅ Soft lock system implemented
- ✅ Automatic cleanup
- ✅ Frontend integration
- ✅ Race condition prevention

### Phase 3: Integrations and Communication Systems (Week 3)

#### 3.1 Two-Way Calendar Synchronization via OAuth 2.0

True synchronization requires a deep integration with Google Calendar and Microsoft Outlook APIs. This will be implemented using the OAuth 2.0 flow:

- **Authorization**: Users are redirected to the provider to grant permissions (e.g., https://www.googleapis.com/auth/calendar).
- **Token Persistence**: The plugin securely stores access and refresh tokens for each employee.
- **Real-Time Updates**: The system uses webhooks to receive "push" notifications from external calendars, immediately blocking out time slots in the Craft booking interface when an external meeting is created.

**Google Calendar Integration:**

**Tasks:**
1. Install Google API client:
   ```bash
   composer require google/apiclient
   ```

2. Create CalendarSyncService:
   ```php
   // src/services/CalendarSyncService.php
   - Handle OAuth 2.0 flow
   - Store access/refresh tokens
   - Sync events to Google Calendar
   - Pull events from Google Calendar
   - Handle webhooks
   ```

3. Create OAuth controller:
   ```php
   // src/controllers/CalendarController.php
   - Initiate OAuth flow
   - Handle OAuth callback
   - Store tokens
   ```

4. Create token storage:
   - Table: `{{%booked_calendar_tokens}}`
   - Fields: employeeId, provider (google/outlook), accessToken, refreshToken, expiresAt

5. Create sync queue jobs:
   ```php
   // src/queue/jobs/SyncToGoogleCalendarJob.php
   // src/queue/jobs/SyncFromGoogleCalendarJob.php
   ```

6. Create webhook endpoint:
   - Handle Google Calendar push notifications
   - Update availability when external events created

7. Update Employee element:
   - Add calendar sync settings
   - Add "Sync Calendar" action

**Microsoft Outlook Integration:**

**Tasks:**
1. Install Microsoft Graph SDK:
   ```bash
   composer require microsoft/microsoft-graph
   ```

2. Extend CalendarSyncService:
   - Add Outlook OAuth support
   - Add Outlook API methods
   - Handle Outlook webhooks

3. Update OAuth controller:
   - Add Outlook OAuth flow
   - Handle Outlook callback

**Deliverables:**
- ✅ Google Calendar sync working
- ✅ Outlook sync working
- ✅ Two-way synchronization
- ✅ Webhook support
- ✅ Token management

#### 3.2 Transactional Notifications and Virtual Meetings [completed]

The system will integrate with transactional email and SMS providers to send reminders, reducing the rate of no-shows. Following the example of Bookly and Amelia, the system will also support native Zoom and Google Meet integrations. Upon booking confirmation, the system's service layer will trigger an API call to create a virtual meeting and store the join URL within the appointment element.

**Zoom Integration:** [in_progress]

**Tasks:**
1. Install Zoom SDK:
   ```bash
   composer require zoom/zoom-api-php-client
   ```

2. Create VirtualMeetingService:
   ```php
   // src/services/VirtualMeetingService.php
   - Create Zoom meeting
   - Create Google Meet link
   - Store join URLs
   - Cancel meetings
   ```

3. Update Reservation element:
   - Add `virtualMeetingUrl` property
   - Add `virtualMeetingProvider` property (zoom/google)

4. Update BookingService:
   - Create virtual meeting on booking confirmation
   - Store join URL in reservation
   - Cancel meeting on reservation cancellation

5. Create settings:
   - Zoom API key/secret
   - Google Meet settings
   - Enable/disable per service

**Google Meet Integration:**

**Tasks:**
1. Extend VirtualMeetingService:
   - Add Google Meet link generation
   - Use Google Calendar API to create event with Meet link

**Enhanced Notifications:** [in_progress]

**Tasks:**
1. Add SMS support: [pending]
   - Integrate with Twilio or similar
   - Add SMS notification settings
   - Queue SMS jobs

2. Add reminder system: [in_progress]
   - Queue reminder emails (24h, 1h before)
   - Queue reminder SMS (optional)
   - Configurable reminder times

3. Update email templates: [completed]
   - Add virtual meeting links
   - Add calendar .ics attachments [pending]
   - Improve email design

**Deliverables:**
- ✅ Zoom integration
- ✅ Google Meet integration
- ✅ Automatic meeting creation
- ✅ Join URL storage
- ✅ SMS notifications
- ✅ Reminder system
- ✅ Enhanced email templates
- ✅ ICS attachments

#### 3.3 Custom Field Data Handling

Leveraging Craft's native field layouts, the system will allow administrators to attach any field type (text, checkbox, asset) to the booking form. This mirrors the custom field capabilities of the WordPress counterparts but with the added benefit of Craft's sophisticated asset management and content modeling.

**Tasks:**
1. Implement field layouts for Reservation element
2. Support custom fields in booking form
3. Store custom field data
4. Display in CP and emails

**Deliverables:**
- ✅ Custom field support
- ✅ Field layouts working
- ✅ Custom fields in forms

### Phase 4: Interface, Commerce, and Deployment (Week 4)

#### 4.1 Dynamic Front-End Components

The booking interface will be developed as a series of Twig components enhanced by a lightweight JavaScript framework like Alpine.js. These components will communicate with the backend via Controller Actions, allowing for "instant" availability updates without page reloads.

The front-end will support three primary view modes:

- **Wizard View**: A step-by-step linear flow for service and staff selection.
- **Catalog View**: A card-based layout for exploring different services.
- **Direct Search**: A date-first approach for finding immediate openings.

**Tasks:**
1. Install Alpine.js:
   ```bash
   npm install alpinejs
   ```

2. Create booking form components:
   ```javascript
   // src/web/js/booking-wizard.js
   - Step 1: Service selection
   - Step 2: Employee selection (optional)
   - Step 3: Date selection
   - Step 4: Time slot selection
   - Step 5: Customer information
   - Step 6: Review and confirm
   ```

3. Create catalog view:
   ```javascript
   // src/web/js/booking-catalog.js
   - Display services as cards
   - Filter by category
   - Quick booking from card
   ```

4. Create direct search:
   ```javascript
   // src/web/js/booking-search.js
   - Date-first approach
   - Show all available slots
   - Quick booking
   ```

5. Create real-time availability:
   ```javascript
   // src/web/js/booking-availability.js
   - Fetch availability via AJAX
   - Update slots without reload
   - Show loading states
   - Handle errors
   ```

6. Create asset bundle:
   ```php
   // src/assetbundles/BookedAsset.php
   - Register CSS
   - Register JavaScript
   - Load Alpine.js
   ```

7. Update templates:
   - Create wizard template
   - Create catalog template
   - Create search template
   - Add Alpine.js components

**Deliverables:**
- ✅ Wizard view
- ✅ Catalog view
- ✅ Direct search view
- ✅ Real-time updates
- ✅ Modern UI

#### 4.2 Craft Commerce Integration (Purchasables)

For systems requiring payment, the Appointment element will implement the Purchasable interface. This allows the booking to be added directly to the Craft Commerce cart, inheriting all the benefits of a full e-commerce platform:

- **Payment Gateways**: Access to dozens of gateways including Stripe, PayPal, and Apple Pay.
- **Taxes and Discounts**: Native handling of VAT, sales tax, and promotional coupons.
- **Order Lifecycle**: Complete tracking of the financial transaction from "Pending" to "Completed".

**Tasks:**
1. Check if Commerce is installed:
   ```php
   if (Craft::$app->plugins->isPluginEnabled('commerce')) {
       // Enable commerce features
   }
   ```

2. Implement Purchasable interface:
   ```php
   // src/elements/Reservation.php
   - Implement craft\commerce\base\Purchasable
   - getPrice(): return service price
   - getSku(): return unique SKU
   - getDescription(): return service description
   - getPurchasableId(): return reservation ID
   ```

3. Create Commerce integration service:
   ```php
   // src/services/CommerceService.php
   - Add reservation to cart
   - Handle order completion
   - Link order to reservation
   - Handle refunds/cancellations
   ```

4. Update BookingService:
   - Create order on booking
   - Link reservation to order
   - Handle payment status

5. Create order-reservation relationship:
   - Table: `{{%booked_order_reservations}}`
   - Fields: orderId, reservationId

**Deliverables:**
- ✅ Commerce integration
- ✅ Purchasable interface
- ✅ Order-reservation linking
- ✅ Payment handling

#### 4.3 Administrative Control Panel Sections

The plugin will add a dedicated section to the Craft Control Panel, featuring a central calendar view and a KPI dashboard. This interface will allow managers to approve pending appointments, manage staff schedules, and view revenue reports.

**Tasks:**
1. Create calendar view controller:
   ```php
   // src/controllers/cp/CalendarController.php
   - Full calendar view
   - Month/week/day views
   - Drag-and-drop rescheduling
   - Color coding by status
   ```

2. Create dashboard controller:
   ```php
   // src/controllers/cp/DashboardController.php
   - KPI cards (total bookings, revenue, etc.)
   - Charts (bookings over time)
   - Recent bookings list
   - Upcoming appointments
   ```

3. Create reports controller:
   ```php
   // src/controllers/cp/ReportsController.php
   - Revenue reports
   - Booking statistics
   - Employee performance
   - Service popularity
   - Export to CSV/PDF
   ```

4. Create templates:
   - Calendar view template
   - Dashboard template
   - Reports templates

5. Add JavaScript:
   - Calendar interaction
   - Chart rendering (Chart.js)
   - Data tables

**Deliverables:**
- ✅ Calendar view
- ✅ KPI dashboard
- ✅ Reports system
- ✅ Export functionality

#### 4.4 Deployment and Project Config Synchronization

To ensure stability across environments, the plugin's configuration (field layouts, element types, and site settings) will be synchronized via Craft's Project Config. This allows for a "dev-to-live" workflow where schema changes are committed to version control and applied automatically on the production server.

**Tasks:**
1. Implement Project Config support:
   - Field layouts
   - Element type configs
   - Settings (non-sensitive)

2. Create config handlers:
   ```php
   // src/config/FieldLayoutConfigHandler.php
   // src/config/ElementTypeConfigHandler.php
   ```

3. Test sync:
   - Dev to staging
   - Staging to production

**Deliverables:**
- ✅ Project Config support
- ✅ Environment sync working

### Phase 5: Advanced Logic and Architectural Refinements

#### 5.1 Performance Optimization for Large Datasets

As the system scales to handle tens of thousands of appointments, query performance must be optimized. The implementation will utilize Craft's Db::batch() and Db::each() methods for background processing of notifications and reports. Additionally, the availability engine will implement an "Availability Cache," storing pre-calculated bookable windows for popular services to reduce database load during peak traffic.

**Tasks:**
1. Optimize queries:
   - Add indexes
   - Use eager loading
   - Batch operations

2. Implement background processing:
   - Use Craft Queue for heavy operations
   - Batch email sending
   - Batch calendar syncs

3. Add caching:
   - Cache availability calculations
   - Cache employee schedules
   - Cache service lists

4. Database optimization:
   - Review all queries
   - Add missing indexes
   - Optimize joins

**Deliverables:**
- ✅ Optimized queries
- ✅ Background processing
- ✅ Caching implemented
- ✅ Database optimized

#### 5.2 Extensibility and Developer API

Following the "developer-first" ethos of Craft CMS, the plugin will expose a comprehensive set of events (e.g., EVENT_BEFORE_BOOKING_SAVE, EVENT_AFTER_SYNC_CALENDAR). This allows other developers to extend the system's functionality, such as triggering a custom CRM sync or sending a Slack notification when a high-value booking is made.

**Tasks:**
1. Define events:
   ```php
   // src/events/BeforeBookingSaveEvent.php
   // src/events/AfterBookingSaveEvent.php
   // src/events/BeforeCalendarSyncEvent.php
   // src/events/AfterCalendarSyncEvent.php
   ```

2. Fire events in services:
   - BookingService
   - CalendarSyncService
   - AvailabilityService

3. Document events:
   - Create events documentation
   - Add examples

**Deliverables:**
- ✅ Event system
- ✅ Documentation
- ✅ Examples

#### 5.3 Testing & Documentation

**Tasks:**
1. Write unit tests:
   - Service tests
   - Element tests
   - Query tests

2. Write integration tests:
   - Booking flow
   - Calendar sync
   - Commerce integration

3. Create documentation:
   - README.md
   - Installation guide
   - Configuration guide
   - Developer guide
   - API documentation

4. Create user guide:
   - How to set up services
   - How to manage employees
   - How to configure availability
   - How to use frontend forms

**Deliverables:**
- ✅ Test coverage
- ✅ Documentation complete
- ✅ User guide

---

## Commercial Features

Additional features for commercial/enterprise use:

- **Upselling, Service Extras, Add-ons**: Ability to add extras and upsell services during booking
- **Sequential Booking**: Book different services back-to-back in sequence
- **Group Booking**: Support for booking multiple people at once
- **Employee Self-Management**: Employees can manage their own schedules via user accounts
- **Calendar Integration**: Two-way sync with external calendars (Google, Outlook)
- **CRM Component**: Searchable and sortable list of customers with:
  - Detailed booking history
  - Payment statistics
  - Internal notes
  - Customer relationship management

---

## Migration Notes

### Completed ✅

1. ✅ Created `composer.json` with plugin metadata
2. ✅ Created main plugin class `src/Booked.php`
3. ✅ Created plugin structure in `src/` directory
4. ✅ Updated namespace from `modules\booking` to `fabian\booked`
5. ✅ Moved old code to `_inspiration/` directory
6. ✅ Deleted old `BookingModule.php`
7. ✅ Updated all migration files to new namespace
8. ✅ Plugin registered in root `composer.json`

### Remaining Work

#### Namespace Updates Required

All files in `src/` need namespace updates from `modules\booking` to `fabian\booked`:

**Files to update:**
- All files in `src/controllers/`
- All files in `src/elements/`
- All files in `src/services/`
- All files in `src/models/`
- All files in `src/records/`
- All files in `src/exceptions/`
- All files in `src/queue/`

**Replacements needed:**
1. `namespace modules\booking` → `namespace fabian\booked`
2. `use modules\booking` → `use fabian\booked`
3. `BookingModule::getInstance()` → `Booked::getInstance()`
4. `'booking/'` → `'booked/'` (in template paths)

#### Route Updates

Update all controller routes from `booking/` to `booked/`:
- CP routes: `booking/*` → `booked/*`
- Site routes: `booking/*` → `booked/*`

#### Template Path Updates

Update template references:
- `'booking/...'` → `'booked/...'`

### Bulk Update Commands

To update all files at once, you can use:

```bash
# Update namespaces
find src/ -name "*.php" -type f -exec sed -i '' 's/namespace modules\\booking/namespace fabian\\booked/g' {} \;

# Update use statements
find src/ -name "*.php" -type f -exec sed -i '' 's/use modules\\booking/use fabian\\booked/g' {} \;

# Update BookingModule references
find src/ -name "*.php" -type f -exec sed -i '' 's/BookingModule::getInstance()/Booked::getInstance()/g' {} \;
find src/ -name "*.php" -type f -exec sed -i '' 's/BookingModule/Booked/g' {} \;

# Update template paths
find src/ -name "*.php" -type f -exec sed -i '' "s/'booking\//'booked\//g" {} \;
find src/ -name "*.php" -type f -exec sed -i '' 's/"booking\/"/"booked\/"/g' {} \;
```

---

## Timeline & Success Criteria

### Timeline Summary

| Phase | Duration | Key Deliverables |
|-------|----------|------------------|
| Phase 1: Plugin Foundation | Week 1 | Plugin structure ✅, core elements |
| Phase 2: Recurrence & Logic | Week 2 | Recurrence engine, availability cache, soft locks |
| Phase 3: Integrations | Week 3 | Calendar sync, virtual meetings, notifications |
| Phase 4: Commerce & Frontend | Week 4 | Commerce integration, frontend views, dashboard |
| Phase 5: Polish & Optimization | Week 5+ | Project Config, events, optimization, docs |

**Total Estimated Time**: 5-6 weeks for full implementation

### Success Criteria

#### Phase 1 Complete
- ✅ Plugin installs and activates
- ⏳ All existing functionality works
- ⏳ New element types created and functional

#### Phase 2 Complete
- ⏳ Recurrence system working
- ⏳ Enhanced availability engine
- ⏳ Soft locks implemented

#### Phase 3 Complete
- ⏳ Calendar sync working (at least one provider)
- ⏳ Virtual meetings working (at least one provider)
- ⏳ Enhanced notifications

#### Phase 4 Complete
- ⏳ Commerce integration working
- ⏳ All frontend views implemented
- ⏳ Dashboard functional

#### Phase 5 Complete
- ⏳ Project Config sync working
- ⏳ Event system documented
- ⏳ Performance optimized
- ⏳ Documentation complete

---

## Next Steps

1. **Review this plan** with stakeholders
2. **Update namespaces** in all `src/` files (use bulk commands above)
3. **Begin Phase 1** - Create core element types (Service, Employee, Location, Schedule)
4. **Iterate** based on feedback and testing

## Notes

- This plan consolidates all previous planning documents
- The plugin is now a clean slate - build features incrementally
- Old code is preserved in `_inspiration/` for reference
- Consider releasing in phases (MVP first, then enhancements)
- Keep backward compatibility during transition
- Document all changes for future reference
