<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use Craft;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Schedule;
use fabian\booked\records\ScheduleRecord;
use fabian\booked\tests\_support\traits\CreatesBookings;
use IntegrationTester;

/**
 * Tests for Schedule element with multiple days support
 *
 * Tests that schedules can be created and saved with multiple days
 * of the week (e.g., Monday, Tuesday, Friday all in one schedule)
 */
class ScheduleMultipleDaysTest extends Unit
{
    use CreatesBookings;

    /**
     * @var IntegrationTester
     */
    protected $tester;

    /**
     * Test that a schedule can be saved with multiple days of the week
     *
     * This is a critical test to verify the bug fix where only the first
     * selected day was being saved instead of all selected days.
     */
    public function testScheduleSavesMultipleDaysOfWeek()
    {
        // Arrange: Create an employee
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        // Create a schedule with Monday (1), Tuesday (2), and Friday (5)
        $schedule = new Schedule();
        $schedule->title = 'Weekday Schedule';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 2, 5]; // Monday, Tuesday, Friday
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';

        // Act: Save the schedule
        $saved = Craft::$app->elements->saveElement($schedule);

        // Assert: Schedule was saved successfully
        $this->assertTrue($saved, 'Schedule should save successfully');
        $this->assertNotNull($schedule->id, 'Schedule should have an ID after saving');

        // Reload from database to verify persistence
        $reloaded = Schedule::find()->id($schedule->id)->one();

        // Assert: All three days were saved
        $this->assertNotNull($reloaded, 'Schedule should be found in database');
        $this->assertIsArray($reloaded->daysOfWeek, 'daysOfWeek should be an array');
        $this->assertCount(3, $reloaded->daysOfWeek, 'Should have exactly 3 days saved');
        $this->assertContains(1, $reloaded->daysOfWeek, 'Should contain Monday (1)');
        $this->assertContains(2, $reloaded->daysOfWeek, 'Should contain Tuesday (2)');
        $this->assertContains(5, $reloaded->daysOfWeek, 'Should contain Friday (5)');

        // Assert: Days are in the correct format
        $this->assertEquals([1, 2, 5], $reloaded->daysOfWeek, 'Days should match exactly');
    }

    /**
     * Test that a schedule saves a single day correctly (backward compatibility)
     */
    public function testScheduleSavesSingleDayOfWeek()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        $schedule = new Schedule();
        $schedule->title = 'Monday Only';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1]; // Only Monday
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';

        // Act
        $saved = Craft::$app->elements->saveElement($schedule);

        // Assert
        $this->assertTrue($saved);

        $reloaded = Schedule::find()->id($schedule->id)->one();
        $this->assertCount(1, $reloaded->daysOfWeek);
        $this->assertEquals([1], $reloaded->daysOfWeek);
    }

    /**
     * Test that schedule stores days in database as JSON
     */
    public function testScheduleStoresDaysAsJsonInDatabase()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        $schedule = new Schedule();
        $schedule->title = 'Multi-day Test';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 3, 5, 7]; // Mon, Wed, Fri, Sun
        $schedule->startTime = '10:00';
        $schedule->endTime = '18:00';

        // Act
        $saved = Craft::$app->elements->saveElement($schedule);
        $this->assertTrue($saved);

        // Assert: Check the database record directly
        $record = ScheduleRecord::findOne($schedule->id);
        $this->assertNotNull($record);

        // The daysOfWeek field in database should be JSON string
        $this->assertIsString($record->daysOfWeek, 'daysOfWeek in database should be JSON string');

        // Decode and verify
        $decoded = json_decode($record->daysOfWeek, true);
        $this->assertIsArray($decoded);
        $this->assertCount(4, $decoded);
        $this->assertEquals([1, 3, 5, 7], $decoded);
    }

    /**
     * Test that getDaysName() formats multiple days correctly
     */
    public function testGetDaysNameFormatsMultipleDays()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        $schedule = new Schedule();
        $schedule->title = 'Display Test';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 2, 5]; // Mon, Tue, Fri
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';

        // Act
        Craft::$app->elements->saveElement($schedule);
        $displayName = $schedule->getDaysName();

        // Assert
        $this->assertEquals('Mon, Tue, Fri', $displayName);
    }

    /**
     * Test validation: at least one day must be selected
     */
    public function testValidationRequiresAtLeastOneDay()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        $schedule = new Schedule();
        $schedule->title = 'No Days';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = []; // Empty array - should fail validation
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';

        // Act
        $saved = Craft::$app->elements->saveElement($schedule);

        // Assert
        $this->assertFalse($saved, 'Schedule should not save with empty daysOfWeek');
        $this->assertTrue($schedule->hasErrors('daysOfWeek'), 'Should have validation error on daysOfWeek');
    }

    /**
     * Test validation: day values must be in range 1-7
     */
    public function testValidationEnforcesDayRange()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        $schedule = new Schedule();
        $schedule->title = 'Invalid Days';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [0, 8]; // Invalid: 0 and 8 are out of range
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';

        // Act
        $saved = Craft::$app->elements->saveElement($schedule);

        // Assert
        $this->assertFalse($saved, 'Schedule should not save with invalid day values');
        $this->assertTrue($schedule->hasErrors('daysOfWeek'), 'Should have validation error on daysOfWeek');
    }

    /**
     * Test that all 7 days can be selected (24/7 schedule)
     */
    public function testScheduleCanHaveAllSevenDays()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Test Employee']);

        $schedule = new Schedule();
        $schedule->title = '24/7 Schedule';
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 2, 3, 4, 5, 6, 7]; // All days
        $schedule->startTime = '00:00';
        $schedule->endTime = '23:59';

        // Act
        $saved = Craft::$app->elements->saveElement($schedule);

        // Assert
        $this->assertTrue($saved);

        $reloaded = Schedule::find()->id($schedule->id)->one();
        $this->assertCount(7, $reloaded->daysOfWeek);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7], $reloaded->daysOfWeek);
    }
}
