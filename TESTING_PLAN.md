# Booked Plugin - Comprehensive Testing Plan

## Overview

This document outlines the complete testing strategy for the Booked plugin, organized by priority and test category. Following TDD principles, all tests should be written before implementing features.

---

## Current Test Coverage

✅ **Existing Tests (15 files)**:
- `AvailabilityCacheTest.php` - Cache layer testing
- `AvailabilityServiceTest.php` - Core availability calculation
- `BlackoutDateServiceTest.php` - Date blackout logic
- `BookingServiceTest.php` - Booking creation and management
- `CalendarSyncServiceTest.php` - Calendar integration
- `EmployeeServiceAssignmentTest.php` - Employee-service relationships
- `EmployeeTypeErrorTest.php` - Type safety
- `FrontendControllerTest.php` - Controller logic
- `LocationAddressTest.php` - Location data
- `RecurrenceServiceTest.php` - RFC 5545 recurrence
- `ReminderServiceTest.php` - Notification reminders
- `ReservationCustomFieldsTest.php` - Custom field handling
- `SoftLockServiceTest.php` - Temporary slot locking
- `TimezoneServiceTest.php` - Timezone handling
- `VirtualMeetingServiceTest.php` - Zoom/Meet integration

---

## Test Categories by Priority

### 🔴 HIGH PRIORITY (Implement First)

These tests cover critical business logic, data integrity, and race conditions that could cause significant issues in production.

#### 1. Integration Tests - Full Booking Flow
**File**: `tests/integration/BookingFlowTest.php`

**Test Cases**:
- Complete booking workflow: service selection → employee selection → date/time → customer info → confirmation
- Multi-step booking with validation errors at each step
- Booking with custom fields
- Booking with payment (Commerce integration)
- Booking cancellation flow
- Booking rescheduling flow
- Token-based booking management (view, cancel, reschedule)

**Why High Priority**: Tests the entire system working together, catches integration issues early.

---

#### 2. Capacity Management & Edge Cases
**File**: `tests/unit/CapacityManagementTest.php`

**Test Cases**:
- Single slot, single employee (basic scenario)
- Multiple employees, quantity-based booking (book 2 out of 3 available)
- Group booking scenarios (book entire capacity)
- Overbooking prevention (reject when capacity reached)
- Capacity with different service durations
- Capacity with employee schedules overlapping
- Partial capacity release on cancellation

**Why High Priority**: Prevents overbooking, which is a critical business failure.

---

#### 3. Concurrent Booking Race Conditions
**File**: `tests/integration/ConcurrentBookingTest.php`

**Test Cases**:
- Two users booking same slot simultaneously (mutex should allow only one)
- Soft lock acquisition during concurrent attempts
- Soft lock expiration and automatic release
- Mutex timeout handling
- Database transaction rollback on conflict
- Unique constraint violation handling

**Why High Priority**: Race conditions lead to double bookings, causing major customer issues.

---

#### 4. Timezone & DST Edge Cases
**File**: `tests/unit/TimezoneEdgeCasesTest.php`

**Test Cases**:
- Booking during DST transition (spring forward)
- Booking during DST transition (fall back)
- Cross-timezone bookings (employee in UTC+1, customer in UTC-5)
- Invalid timezone handling
- UTC±0 edge cases
- Midnight boundary transitions
- Date line crossing (UTC+12 to UTC-12)
- Slot display in user's local timezone
- Database storage in UTC verification

**Why High Priority**: Timezone bugs are extremely difficult to debug in production and affect user trust.

---

#### 5. Data Integrity & Cleanup
**File**: `tests/integration/DataIntegrityTest.php`

**Test Cases**:
- Orphaned reservation cleanup (employee deleted)
- Cascade deletes: Employee → Schedules → Reservations
- Cascade deletes: Service → Availability → Reservations
- Cascade deletes: Location → Employees → Reservations
- Soft lock garbage collection (expired locks removed)
- Expired token cleanup (calendar sync tokens)
- Database foreign key constraint verification
- Transaction rollback on partial failure
- Data consistency after failed migration

**Why High Priority**: Data integrity issues compound over time and are hard to fix retroactively.

---

### 🟡 MEDIUM PRIORITY (Implement Second)

These tests cover important functionality and performance characteristics.

#### 6. Integration Tests - Availability Calculation
**File**: `tests/integration/AvailabilityCalculationTest.php`

**Test Cases**:
- Complex scenario: 3 employees, 2 services, 1 location, various schedules
- Availability with overlapping employee schedules
- Availability with recurrence + blackouts + existing bookings
- Availability with buffer times (before/after service)
- Timezone-aware availability calculation
- Employee-specific availability
- Service-specific availability
- Location-specific availability
- Multi-day availability ranges
- Cache effectiveness verification

---

#### 7. Performance Benchmarks
**File**: `tests/performance/AvailabilityPerformanceTest.php`

**Test Cases**:
- Availability calculation with 1000+ existing reservations
- Availability calculation with 50+ employees
- Availability calculation with complex recurrence rules
- Cache hit/miss ratio testing
- Query count optimization (detect N+1 queries)
- Database index effectiveness
- Batch operations performance (100+ bookings)

**File**: `tests/performance/ConcurrentLoadTest.php`
- 100 concurrent booking attempts
- 1000 concurrent availability lookups
- Mutex lock contention under load
- Soft lock cleanup under load

---

#### 8. Error Recovery & Resilience
**File**: `tests/unit/ErrorRecoveryTest.php`

**Test Cases**:
- Booking rollback on database failure
- Booking rollback on email sending failure
- Partial calendar sync failure (some events synced, some failed)
- Virtual meeting creation failure (booking succeeds, meeting fails)
- Email queue failure with retry logic
- External API timeout handling
- Circuit breaker for failing external services
- Graceful degradation when calendar sync unavailable

---

#### 9. Migration Tests
**File**: `tests/unit/MigrationTest.php`

**Test Cases**:
- Fresh install migration (all tables created)
- Upgrade migration from version X to Y
- Migration rollback capability
- Data integrity after migration
- Schema consistency across environments
- Index creation verification
- Foreign key creation verification

---

#### 10. Complex Recurrence Patterns
**File**: `tests/unit/RecurrenceAdvancedTest.php`

**Test Cases**:
- BYMONTHDAY (e.g., "every 15th of the month")
- BYWEEKNO (e.g., "week 1 and 3 of each month")
- Complex BYDAY patterns (e.g., "first and third Monday")
- Recurrence exceptions (EXDATE - exclude specific dates)
- Series modifications (edit this instance, edit all future, edit all)
- Recurrence end date vs COUNT
- Infinite recurrence handling
- Recurrence across year boundaries
- Recurrence with leap years

---

### 🟢 LOW PRIORITY (Nice to Have)

These tests provide additional confidence but are less critical for initial launch.

#### 11. Functional Tests - Frontend
**File**: `tests/functional/FrontendBookingCest.php`

**Test Cases**:
- Complete user journey via frontend form
- Form validation error display
- AJAX availability updates
- Soft lock countdown timer
- Multi-step wizard navigation
- Catalog view interaction
- Direct search view
- Email confirmation receipt
- Thank you page display

---

#### 12. Functional Tests - Control Panel
**File**: `tests/functional/CpManagementCest.php`

**Test Cases**:
- Create/edit/delete services via CP
- Create/edit/delete employees via CP
- Employee schedule management interface
- Bulk operations (bulk approve, bulk cancel, bulk reschedule)
- Calendar view drag-and-drop
- Dashboard KPI display
- Report generation and export
- Settings management

---

#### 13. Advanced Validation Tests
**File**: `tests/unit/BookingValidationTest.php`

**Test Cases**:
- Past date rejection
- Invalid time range (end before start)
- Email format validation
- Phone number format validation (international)
- Custom field validation (required, min/max, pattern)
- Service-specific validation rules
- Employee availability validation
- Location capacity validation
- Booking window restrictions (e.g., can't book within 24h)
- Maximum advance booking (e.g., can't book more than 3 months ahead)

---

#### 14. API & Controller Tests
**File**: `tests/unit/BookingControllerTest.php`

**Test Cases**:
- AJAX availability endpoint response format
- AJAX booking submission endpoint
- Rate limiting enforcement (IP and email)
- CSRF token validation
- Request parameter sanitization
- JSON response structure
- Error response format standardization
- HTTP status code correctness

---

#### 15. Notification & Email Tests
**File**: `tests/unit/NotificationQueueTest.php`

**Test Cases**:
- Confirmation email sent on booking
- Cancellation email sent on cancellation
- Reminder scheduled for 24h before
- Reminder scheduled for 1h before
- Notification batching for efficiency
- Notification retry on failure
- Email template rendering with variables
- SMS notification (if enabled)
- Owner notification on booking
- ICS calendar attachment generation

---

#### 16. Commerce Integration Tests
**File**: `tests/unit/CommerceIntegrationTest.php`

**Test Cases**:
- Purchasable interface implementation
- Add booking to cart
- Order-reservation linking
- Payment status handling
- Refund handling on cancellation
- Tax calculation
- Discount application
- Order completion workflow
- Abandoned cart handling

---

#### 17. Calendar Sync Edge Cases
**File**: `tests/integration/CalendarSyncEdgeCasesTest.php`

**Test Cases**:
- Token expiration and refresh
- OAuth flow interruption
- Webhook signature validation
- Duplicate event prevention
- Event update propagation
- Event deletion handling
- Conflict resolution (Craft vs Google)
- Multiple calendar provider support
- Sync frequency throttling

---

#### 18. Security Tests
**File**: `tests/unit/SecurityTest.php`

**Test Cases**:
- SQL injection prevention
- XSS prevention in booking forms
- CSRF token validation
- Rate limiting bypass attempts
- Token tampering detection
- Permission enforcement (CP access)
- API authentication
- Sensitive data encryption (tokens, credentials)

---

#### 19. Accessibility & UI Tests
**File**: `tests/functional/AccessibilityTest.php`

**Test Cases**:
- Keyboard navigation
- Screen reader compatibility
- ARIA labels
- Color contrast
- Focus management
- Form error announcement

---

#### 20. Mutation Testing
**Setup**: Using Infection/Infection

```bash
composer require --dev infection/infection
```

**Purpose**: Verify that your tests actually catch bugs by mutating the code and ensuring tests fail.

---

## Test Infrastructure

### Test Fixtures & Factories

Create reusable test data builders:

```
tests/_support/
├── factories/
│   ├── ServiceFactory.php          # Create test services
│   ├── EmployeeFactory.php         # Create test employees
│   ├── LocationFactory.php         # Create test locations
│   ├── ScheduleFactory.php         # Create test schedules
│   ├── ReservationFactory.php      # Create test reservations
│   └── AvailabilityFactory.php     # Create test availability
├── fixtures/
│   ├── ServiceFixture.php          # Service fixture data
│   ├── EmployeeFixture.php         # Employee fixture data
│   └── ReservationFixture.php      # Reservation fixture data
└── traits/
    ├── CreatesBookings.php         # Helper trait for tests
    ├── CreatesEmployees.php        # Helper trait for tests
    └── MocksExternalApis.php       # Helper trait for mocking
```

### Mock Services

Create centralized mock services for consistent testing:

```
tests/_support/mocks/
├── MockCalendarSyncService.php
├── MockVirtualMeetingService.php
├── MockEmailService.php
└── MockPaymentGateway.php
```

---

## Test Organization

```
tests/
├── unit/                           # ✅ Isolated component tests (EXISTING)
│   ├── services/
│   ├── elements/
│   ├── models/
│   └── validators/
├── integration/                    # 🔴 Component interaction tests (HIGH PRIORITY)
│   ├── BookingFlowTest.php
│   ├── ConcurrentBookingTest.php
│   ├── AvailabilityCalculationTest.php
│   ├── DataIntegrityTest.php
│   └── CalendarSyncEdgeCasesTest.php
├── functional/                     # 🟢 End-to-end tests (LOW PRIORITY)
│   ├── FrontendBookingCest.php
│   └── CpManagementCest.php
├── performance/                    # 🟡 Performance benchmarks (MEDIUM PRIORITY)
│   ├── AvailabilityPerformanceTest.php
│   └── ConcurrentLoadTest.php
├── fixtures/                       # Test data
│   ├── services.php
│   ├── employees.php
│   └── reservations.php
└── _support/                       # ✅ Test utilities (EXISTING)
    ├── factories/
    ├── fixtures/
    ├── mocks/
    └── traits/
```

---

## Implementation Order

### Phase 1: High Priority Tests (Week 1)
1. ✅ Set up test infrastructure (factories, fixtures, traits)
2. ✅ Integration test: Full booking flow
3. ✅ Unit test: Capacity management edge cases
4. ✅ Integration test: Concurrent booking race conditions
5. ✅ Unit test: Timezone & DST edge cases
6. ✅ Integration test: Data integrity & cleanup

### Phase 2: Medium Priority Tests (Week 2)
7. ⏳ Integration test: Complex availability calculation
8. ⏳ Performance tests: Availability and concurrent load
9. ⏳ Unit test: Error recovery and resilience
10. ⏳ Unit test: Migration tests
11. ⏳ Unit test: Advanced recurrence patterns

### Phase 3: Low Priority Tests (Week 3+)
12. ⏳ Functional tests: Frontend booking
13. ⏳ Functional tests: CP management
14. ⏳ Unit tests: Validation, API, notifications
15. ⏳ Security tests
16. ⏳ Mutation testing setup

---

## Success Metrics

- **Code Coverage**: Aim for >85% coverage
- **Test Execution Time**: Keep under 2 minutes for unit tests
- **Test Reliability**: 0% flaky tests
- **Mutation Score**: >70% (using Infection)
- **Critical Path Coverage**: 100% coverage on booking flow, availability calculation, race conditions

---

## Continuous Integration

### GitHub Actions / GitLab CI

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: vendor/bin/codecept run unit
      - run: vendor/bin/codecept run integration
```

---

## Notes

- All tests follow TDD: Write test first, then implement feature
- Use descriptive test names that explain the scenario
- Each test should be independent (no shared state)
- Mock external dependencies (APIs, payment gateways)
- Use factories for test data creation (avoid hard-coded values)
- Keep tests fast (use in-memory databases where possible)
- Document complex test scenarios with comments

---

## Resources

- [Codeception Documentation](https://codeception.com/docs)
- [Craft CMS Testing Guide](https://craftcms.com/docs/5.x/testing/)
- [PHPUnit Best Practices](https://phpunit.de/manual/current/en/index.html)
- [Infection Mutation Testing](https://infection.github.io/)
