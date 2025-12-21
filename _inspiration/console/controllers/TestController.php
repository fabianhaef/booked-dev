<?php

namespace modules\booking\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use modules\booking\elements\BookingVariation;
use modules\booking\elements\Availability as AvailabilityElement;
use modules\booking\services\BookingService;
use modules\booking\services\AvailabilityService;
use yii\console\ExitCode;

/**
 * Booking Module Test Controller
 *
 * Quick testing commands for the booking module without using the UI
 *
 * Usage:
 *   php craft booking/test/create-booking          # Create a test booking
 *   php craft booking/test/create-booking --quantity=3  # Create booking with multiple places
 *   php craft booking/test/list-variations         # List all variations
 *   php craft booking/test/check-availability      # Check availability for tomorrow
 *   php craft booking/test/send-test-email         # Test email sending
 */
class TestController extends Controller
{
    /**
     * @var int Number of places to book (for testing quantity)
     */
    public $quantity = 1;

    /**
     * @var string Email address for test bookings
     */
    public $email = 'test@example.com';

    /**
     * @var string Name for test bookings
     */
    public $name = 'Test User';

    /**
     * @var int Number of days in the future for the booking
     */
    public $daysAhead = 7;

    /**
     * @var int|null Specific variation ID to use
     */
    public $variationId = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        switch ($actionID) {
            case 'create-booking':
                return array_merge($options, ['quantity', 'email', 'name', 'daysAhead', 'variationId']);
            case 'send-test-email':
                return array_merge($options, ['quantity', 'email', 'name']);
            default:
                return $options;
        }
    }

    /**
     * Create a test booking with optional quantity
     *
     * @return int
     */
    public function actionCreateBooking(): int
    {
        $this->stdout("Creating test booking...\n", Console::FG_CYAN);

        // Get booking service
        $bookingService = new BookingService();
        $availabilityService = new AvailabilityService();

        // Calculate booking date
        $bookingDate = date('Y-m-d', strtotime("+{$this->daysAhead} days"));
        $dayOfWeek = (int)date('w', strtotime($bookingDate));

        $this->stdout("Date: {$bookingDate} (Day of week: {$dayOfWeek})\n");

        // Get available variations for this day
        $variations = $availabilityService->getAvailableVariations($bookingDate);

        if (empty($variations)) {
            $this->stderr("No variations available for {$bookingDate}\n", Console::FG_RED);
            $this->stdout("\nTip: Create availability and variations first:\n", Console::FG_YELLOW);
            $this->stdout("  php craft booking/test/list-variations\n");
            return ExitCode::DATAERR;
        }

        // Select variation
        $variation = null;
        if ($this->variationId) {
            foreach ($variations as $v) {
                if ($v['id'] == $this->variationId) {
                    $variation = $v;
                    break;
                }
            }
            if (!$variation) {
                $this->stderr("Variation #{$this->variationId} not found\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
        } else {
            $variation = $variations[0];
        }

        $this->stdout("Using variation: {$variation['title']} (ID: {$variation['id']})\n");

        // Check if quantity is allowed
        if ($this->quantity > 1) {
            if (!($variation['allowQuantitySelection'] ?? false)) {
                $this->stderr("This variation does not allow quantity selection\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }

            $maxCapacity = $variation['maxCapacity'] ?? 1;
            if ($this->quantity > $maxCapacity) {
                $this->stderr("Requested quantity ({$this->quantity}) exceeds max capacity ({$maxCapacity})\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
        }

        // Get available time slots (filtered by requested quantity)
        $slots = $availabilityService->getAvailableSlots($bookingDate, null, $variation['id'], $this->quantity);

        if (empty($slots)) {
            $this->stderr("No time slots available for quantity {$this->quantity}\n", Console::FG_RED);
            $this->stdout("\nTry a smaller quantity or different date.\n", Console::FG_YELLOW);
            return ExitCode::DATAERR;
        }

        $this->stdout("Found " . count($slots) . " available slot(s) for quantity {$this->quantity}\n");

        // Use first available slot
        $slot = $slots[0];
        $startTime = $slot['time'];
        $endTime = $slot['endTime'];

        $this->stdout("Time slot: {$startTime} - {$endTime}\n");
        $this->stdout("Quantity: {$this->quantity}\n");

        // Create booking data
        $data = [
            'userName' => $this->name,
            'userEmail' => $this->email,
            'bookingDate' => $bookingDate,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'variationId' => $variation['id'],
            'quantity' => $this->quantity,
            'notes' => 'Test booking created via CLI',
        ];

        try {
            // Create reservation
            $reservation = $bookingService->createReservation($data);

            $this->stdout("\n✓ Booking created successfully!\n", Console::FG_GREEN);
            $this->stdout("  ID: #{$reservation->id}\n");
            $this->stdout("  Name: {$reservation->userName}\n");
            $this->stdout("  Email: {$reservation->userEmail}\n");
            $this->stdout("  Date: {$reservation->bookingDate}\n");
            $this->stdout("  Time: {$reservation->startTime} - {$reservation->endTime}\n");
            $this->stdout("  Quantity: {$reservation->quantity}\n");
            $this->stdout("  Status: {$reservation->getStatusLabel()}\n");

            if ($reservation->confirmationToken) {
                $this->stdout("\n  Management URL: " . $reservation->getManagementUrl() . "\n");
                $this->stdout("  Cancel URL: " . $reservation->getCancelUrl() . "\n");
            }

            $this->stdout("\n  Email queued: " . ($reservation->notificationSent ? 'Yes' : 'Pending') . "\n");

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("\n✗ Failed to create booking: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * List all booking variations
     *
     * @return int
     */
    public function actionListVariations(): int
    {
        $this->stdout("Booking Variations:\n\n", Console::FG_CYAN);

        $variations = BookingVariation::find()
            ->orderBy(['title' => SORT_ASC])
            ->all();

        if (empty($variations)) {
            $this->stdout("No variations found.\n", Console::FG_YELLOW);
            $this->stdout("\nCreate variations in the Control Panel:\n");
            $this->stdout("  /admin/booking/variations\n");
            return ExitCode::OK;
        }

        foreach ($variations as $variation) {
            $status = $variation->isActive ? '✓' : '✗';
            $this->stdout("{$status} ", $variation->isActive ? Console::FG_GREEN : Console::FG_RED);
            $this->stdout("#{$variation->id} - {$variation->title}\n", Console::BOLD);
            $this->stdout("    Duration: {$variation->slotDurationMinutes} min");
            if ($variation->bufferMinutes) {
                $this->stdout(" (Buffer: {$variation->bufferMinutes} min)");
            }
            $this->stdout("\n");
            $this->stdout("    Capacity: {$variation->maxCapacity}");
            if ($variation->allowQuantitySelection) {
                $this->stdout(" (Quantity selection enabled)", Console::FG_GREEN);
            }
            $this->stdout("\n");

            if ($variation->description) {
                $this->stdout("    Description: {$variation->description}\n");
            }
            $this->stdout("\n");
        }

        return ExitCode::OK;
    }

    /**
     * Check availability for a specific date
     *
     * @return int
     */
    public function actionCheckAvailability(): int
    {
        $this->stdout("Checking availability...\n\n", Console::FG_CYAN);

        $availabilityService = new AvailabilityService();
        $bookingDate = date('Y-m-d', strtotime('+1 day'));

        $this->stdout("Date: {$bookingDate}\n\n");

        // Get variations
        $variations = $availabilityService->getAvailableVariations($bookingDate);

        if (empty($variations)) {
            $this->stdout("No variations available for this date.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        foreach ($variations as $variation) {
            $this->stdout("Variation: {$variation['title']} (ID: {$variation['id']})\n", Console::BOLD);

            // Get time slots
            $slots = $availabilityService->getAvailableSlots($bookingDate, null, $variation['id']);

            if (empty($slots)) {
                $this->stdout("  No time slots available\n", Console::FG_YELLOW);
            } else {
                $this->stdout("  Available slots:\n", Console::FG_GREEN);
                foreach ($slots as $slot) {
                    $remainingCapacity = $slot['remainingCapacity'] ?? 'N/A';
                    $this->stdout("    • {$slot['time']} - {$slot['endTime']}");
                    if (is_numeric($remainingCapacity)) {
                        $this->stdout(" (Remaining: {$remainingCapacity})");
                    }
                    $this->stdout("\n");
                }
            }
            $this->stdout("\n");
        }

        return ExitCode::OK;
    }

    /**
     * Send a test email to verify email configuration
     *
     * @return int
     */
    public function actionSendTestEmail(): int
    {
        $this->stdout("Sending test email...\n", Console::FG_CYAN);

        // Get booking service
        $bookingService = new BookingService();
        $availabilityService = new AvailabilityService();

        // Calculate booking date (tomorrow by default)
        $bookingDate = date('Y-m-d', strtotime('+1 day'));

        $this->stdout("Date: {$bookingDate}\n");

        // Get available variations for this day
        $variations = $availabilityService->getAvailableVariations($bookingDate);

        if (empty($variations)) {
            $this->stderr("No variations available for {$bookingDate}\n", Console::FG_RED);
            $this->stdout("\nTip: Create availability and variations first:\n", Console::FG_YELLOW);
            $this->stdout("  php craft booking/test/list-variations\n");
            $this->stdout("\nOr use create-booking instead:\n");
            $this->stdout("  php craft booking/test/create-booking --email={$this->email}\n");
            return ExitCode::DATAERR;
        }

        // Use first variation
        $variation = $variations[0];
        $this->stdout("Using variation: {$variation['title']} (ID: {$variation['id']})\n");

        // Check if quantity is allowed
        if ($this->quantity > 1) {
            if (!($variation['allowQuantitySelection'] ?? false)) {
                $this->stderr("This variation does not allow quantity selection\n", Console::FG_RED);
                $this->stdout("Using quantity=1 instead\n", Console::FG_YELLOW);
                $this->quantity = 1;
            } else {
                $maxCapacity = $variation['maxCapacity'] ?? 1;
                if ($this->quantity > $maxCapacity) {
                    $this->stderr("Requested quantity ({$this->quantity}) exceeds max capacity ({$maxCapacity})\n", Console::FG_RED);
                    $this->stdout("Using quantity={$maxCapacity} instead\n", Console::FG_YELLOW);
                    $this->quantity = $maxCapacity;
                }
            }
        }

        // Get available time slots (filtered by requested quantity)
        $slots = $availabilityService->getAvailableSlots($bookingDate, null, $variation['id'], $this->quantity);

        if (empty($slots)) {
            $this->stderr("No time slots available for quantity {$this->quantity}\n", Console::FG_RED);
            $this->stdout("\nTry quantity=1 or a different date\n", Console::FG_YELLOW);
            return ExitCode::DATAERR;
        }

        // Use first available slot
        $slot = $slots[0];
        $startTime = $slot['time'];
        $endTime = $slot['endTime'];

        $this->stdout("Time slot: {$startTime} - {$endTime}\n");
        $this->stdout("Quantity: {$this->quantity}\n");

        // Create booking data
        $data = [
            'userName' => $this->name,
            'userEmail' => $this->email,
            'bookingDate' => $bookingDate,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'variationId' => $variation['id'],
            'quantity' => $this->quantity,
            'notes' => 'This is a test booking for email verification',
        ];

        try {
            $reservation = $bookingService->createReservation($data);

            $this->stdout("\n✓ Test booking created (ID: #{$reservation->id})\n", Console::FG_GREEN);
            $this->stdout("✓ Confirmation email queued\n", Console::FG_GREEN);
            $this->stdout("\nTo: {$this->email}\n");
            $this->stdout("Quantity: {$this->quantity}\n");

            $this->stdout("\nCheck your email or look at the queue:\n", Console::FG_YELLOW);
            $this->stdout("  php craft queue/run\n");

            // Run the queue immediately
            $this->stdout("\nRunning queue now...\n", Console::FG_CYAN);
            Craft::$app->runAction('queue/run');

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("\n✗ Failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Clean up test bookings (bookings with test email addresses)
     *
     * @return int
     */
    public function actionCleanup(): int
    {
        $this->stdout("Cleaning up test bookings...\n", Console::FG_CYAN);

        // Find test reservations
        $reservations = \modules\booking\elements\Reservation::find()
            ->where(['like', 'userEmail', 'test@%', false])
            ->orWhere(['like', 'notes', 'Test booking created via CLI', false])
            ->all();

        if (empty($reservations)) {
            $this->stdout("No test bookings found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $count = count($reservations);
        $this->stdout("Found {$count} test booking(s)\n");

        if (!$this->confirm("Delete these test bookings?")) {
            $this->stdout("Cancelled.\n");
            return ExitCode::OK;
        }

        $deleted = 0;
        foreach ($reservations as $reservation) {
            if (Craft::$app->elements->deleteElement($reservation)) {
                $deleted++;
            }
        }

        $this->stdout("✓ Deleted {$deleted} test booking(s)\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Show booking statistics
     *
     * @return int
     */
    public function actionStats(): int
    {
        $this->stdout("Booking Statistics:\n\n", Console::FG_CYAN);

        // Count variations
        $variationsCount = BookingVariation::find()->count();
        $activeVariations = BookingVariation::find()->where(['isActive' => true])->count();

        $this->stdout("Variations: {$variationsCount} total, {$activeVariations} active\n");

        // Count availabilities
        $availabilitiesCount = AvailabilityElement::find()->count();
        $this->stdout("Availabilities: {$availabilitiesCount}\n");

        // Count reservations
        $totalReservations = \modules\booking\elements\Reservation::find()->count();
        $confirmedReservations = \modules\booking\elements\Reservation::find()
            ->where(['status' => 'confirmed'])
            ->count();
        $pendingReservations = \modules\booking\elements\Reservation::find()
            ->where(['status' => 'pending'])
            ->count();

        $this->stdout("Reservations: {$totalReservations} total\n");
        $this->stdout("  - Confirmed: {$confirmedReservations}\n", Console::FG_GREEN);
        $this->stdout("  - Pending: {$pendingReservations}\n", Console::FG_YELLOW);

        // Count by quantity
        $multipleBookings = \modules\booking\elements\Reservation::find()
            ->where(['>', 'quantity', 1])
            ->count();

        if ($multipleBookings > 0) {
            $this->stdout("  - Multiple places: {$multipleBookings}\n", Console::FG_CYAN);
        }

        // Recent bookings
        $recentBookings = \modules\booking\elements\Reservation::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(5)
            ->all();

        if (!empty($recentBookings)) {
            $this->stdout("\nRecent Bookings:\n", Console::FG_CYAN);
            foreach ($recentBookings as $booking) {
                $qty = $booking->quantity > 1 ? " ({$booking->quantity}x)" : "";
                $this->stdout("  • #{$booking->id} - {$booking->userName}{$qty} - {$booking->bookingDate} {$booking->startTime}\n");
            }
        }

        return ExitCode::OK;
    }
}
