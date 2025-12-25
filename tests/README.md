# Booked Plugin Test Suite

This project uses a **Hybrid Testing Approach** with two separate test suites optimized for different testing scenarios.

## Test Suites

### 1. Unit Tests (`unit`) - Fast & Lightweight ⚡

**Purpose**: Test pure logic without database dependencies

**Use for**:
- Algorithm testing (timezone calculations, recurrence logic, date math)
- Service method logic
- Data transformations and validations
- Helper functions and utilities

**Characteristics**:
- ✅ **Fast** - No database, no Craft CMS overhead
- ✅ **Isolated** - Tests run independently with mocked dependencies
- ✅ **Simple Setup** - Minimal bootstrap with mock Craft class

**Bootstrap**: `tests/_bootstrap_unit.php`

**Example Test**:
```php
<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\TimezoneService;

class TimezoneServiceTest extends Unit
{
    protected $tester;
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TimezoneService();
    }

    public function testConvertToUtc()
    {
        $date = '2025-01-01';
        $time = '09:00';
        $timezone = 'Europe/Zurich';

        $utcDateTime = $this->service->convertToUtc($date, $time, $timezone);

        $this->assertEquals('UTC', $utcDateTime->getTimezone()->getName());
        $this->assertEquals('2025-01-01 08:00:00', $utcDateTime->format('Y-m-d H:i:s'));
    }
}
```

**Run Unit Tests**:
```bash
# Run all unit tests
ddev composer test:unit

# Or using codecept directly
ddev exec "./vendor/bin/codecept run unit -c plugins/booked/codeception.yml"

# Run specific test
ddev exec "./vendor/bin/codecept run unit:tests/unit/TimezoneServiceTest.php -c plugins/booked/codeception.yml"
```

---

### 2. Integration Tests (`integration`) - Full CMS 🔌

**Purpose**: Test complete workflows with database and Craft CMS

**Use for**:
- Booking workflows end-to-end
- Element creation and relationships
- Database transactions
- Service integration
- Complex business logic spanning multiple services

**Characteristics**:
- ✅ **Complete** - Full Craft CMS environment with database
- ✅ **Realistic** - Tests actual element saves, queries, and relationships
- ✅ **Transactional** - Each test runs in a transaction (auto-rollback)
- ⚠️ **Slower** - Database setup and Craft initialization add overhead

**Bootstrap**: `tests/_bootstrap_integration.php`

**Database**: SQLite test database at `tests/_craft/storage/test.sqlite`

**Example Test**:
```php
<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use fabian\booked\elements\Reservation;

class BookingFlowTest extends Unit
{
    protected $tester;

    public function testCreateBooking()
    {
        // Arrange: Create test data using helper
        $service = $this->tester->createService(['title' => 'Consultation', 'duration' => 60]);
        $employee = $this->tester->createEmployee(['title' => 'Dr. Smith']);

        // Act: Create reservation
        $reservation = $this->tester->createReservation([
            'serviceId' => $service->id,
            'employeeId' => $employee->id,
            'bookingDate' => '2025-12-26',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ]);

        // Assert
        $this->assertNotNull($reservation->id);
        $this->assertEquals('confirmed', $reservation->status);
    }
}
```

**Run Integration Tests**:
```bash
# Run all integration tests
ddev composer test:integration

# Or using codecept directly
ddev exec "./vendor/bin/codecept run integration -c plugins/booked/codeception.yml"

# Run specific test
ddev exec "./vendor/bin/codecept run integration:tests/integration/BookingFlowTest.php -c plugins/booked/codeception.yml"
```

---

## Test Structure

```
tests/
├── _bootstrap_unit.php              # Lightweight bootstrap for unit tests
├── _bootstrap_integration.php       # Full Craft bootstrap for integration tests
├── _craft/
│   ├── config/
│   │   ├── db.php                   # SQLite database config
│   │   ├── test.php                 # Test environment overrides
│   │   └── general.php              # General Craft config
│   └── storage/                     # Test database and runtime files
├── _support/
│   ├── Helper/
│   │   ├── Unit.php                 # Unit test helpers
│   │   └── Integration.php          # Integration test helpers
│   ├── UnitTester.php               # Generated unit tester
│   ├── IntegrationTester.php        # Generated integration tester
│   └── PluginTestHelper.php         # Mock plugin setup utilities
├── unit/                            # Unit tests
│   ├── TimezoneServiceTest.php
│   ├── RecurrenceServiceTest.php
│   └── ...
└── integration/                     # Integration tests
    ├── BookingFlowTest.php
    ├── AvailabilityCalculationTest.php
    └── ...
```

---

## Helper Methods

### Unit Test Helpers

Available via `$this->tester` in unit tests:

```php
// Create mock DateTime
$dt = $this->tester->createDateTime('2025-12-25 10:00:00');

// Assert time equality (ignores seconds)
$this->tester->assertTimeEquals('10:00', $actualTime);

// Assert array has required keys
$this->tester->assertArrayHasKeys(['time', 'employeeId'], $slot);
```

### Integration Test Helpers

Available via `$this->tester` in integration tests:

```php
// Create test elements
$employee = $this->tester->createEmployee([
    'title' => 'John Doe',
    'email' => 'john@example.com',
]);

$service = $this->tester->createService([
    'title' => 'Massage',
    'duration' => 60,
    'bufferBefore' => 15,
]);

$location = $this->tester->createLocation([
    'title' => 'Main Office',
    'timezone' => 'Europe/Zurich',
]);

$reservation = $this->tester->createReservation([
    'serviceId' => $service->id,
    'employeeId' => $employee->id,
    'bookingDate' => '2025-12-26',
]);
```

---

## Running Tests

### All Tests
```bash
ddev composer test
```

### Unit Tests Only (Fast)
```bash
ddev exec "./vendor/bin/codecept run unit -c plugins/booked/codeception.yml"
```

### Integration Tests Only
```bash
ddev exec "./vendor/bin/codecept run integration -c plugins/booked/codeception.yml"
```

### With Verbose Output
```bash
ddev exec "./vendor/bin/codecept run unit -v -c plugins/booked/codeception.yml"
```

### Specific Test File
```bash
ddev exec "./vendor/bin/codecept run unit:tests/unit/TimezoneServiceTest.php -c plugins/booked/codeception.yml"
```

### With Code Coverage
```bash
ddev exec "./vendor/bin/codecept run --coverage --coverage-html -c plugins/booked/codeception.yml"
```

---

## Writing New Tests

### When to Write Unit Tests

✅ **DO use unit tests for**:
- Pure functions and algorithms
- Date/time calculations
- String manipulation and formatting
- Validation logic
- Mathematical calculations
- Array transformations

❌ **DON'T use unit tests for**:
- Database queries
- Element creation
- Complete booking workflows
- Multi-service interactions

### When to Write Integration Tests

✅ **DO use integration tests for**:
- Complete user workflows
- Database interactions
- Element relationships
- Service orchestration
- Email sending (with mocks)
- Cache invalidation

❌ **DON'T use integration tests for**:
- Simple utility functions
- Pure algorithm testing

---

## Best Practices

1. **Keep unit tests pure** - No database, no external dependencies
2. **Use factories** - Create test data with helpers, not manual instantiation
3. **Test behavior, not implementation** - Focus on what, not how
4. **One assertion per test** - Or at least one logical assertion
5. **Clear test names** - `testBookingFailsWhenEmployeeUnavailable` not `testBooking1`
6. **Arrange-Act-Assert** - Structure tests clearly
7. **Clean up** - Integration tests auto-rollback, but be mindful of side effects

---

## Troubleshooting

### Tests are slow
- Use unit tests instead of integration tests where possible
- Run specific test suites: `codecept run unit`

### Database errors
- Check that `tests/_craft/storage/` has write permissions
- Ensure SQLite extension is installed in PHP

### Class not found errors
- Run `ddev exec "./vendor/bin/codecept build -c plugins/booked/codeception.yml"`
- Check that autoloader is configured correctly

### Mock not working
- Check `tests/_support/PluginTestHelper.php` for mock setup
- Ensure plugin instance is initialized in bootstrap

---

## Configuration Files

- **`codeception.yml`** - Main test configuration with suite definitions
- **`tests/_bootstrap_unit.php`** - Unit test initialization
- **`tests/_bootstrap_integration.php`** - Integration test initialization with Craft
- **`tests/_craft/config/test.php`** - Craft CMS test configuration
- **`tests/_craft/config/db.php`** - Database configuration (SQLite)

---

## Next Steps

1. **Categorize existing tests** - Move tests to appropriate suites
2. **Add more unit tests** - Extract pure logic into testable services
3. **Create test factories** - Build reusable test data builders
4. **Add fixtures** - Create seed data for integration tests
5. **Improve coverage** - Target critical business logic

---

**Happy Testing! 🎯**
