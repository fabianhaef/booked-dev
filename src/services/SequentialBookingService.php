<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use DateTime;
use fabian\booked\Booked;
use fabian\booked\elements\BookingSequence;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\exceptions\BookingException;
use fabian\booked\records\BookingSequenceRecord;
use fabian\booked\records\ReservationRecord;

/**
 * Sequential Booking Service
 *
 * Handles booking multiple services back-to-back in a single transaction
 */
class SequentialBookingService extends Component
{
    /**
     * Calculate sequential time slots for multiple services
     *
     * @param array $serviceIds Array of service IDs to book sequentially
     * @param string $date Starting date (YYYY-MM-DD)
     * @param int|null $employeeId Optional employee ID
     * @param int|null $locationId Optional location ID
     * @return array Available start times for the sequence
     * @throws BookingException
     */
    public function getAvailableSequenceSlots(
        array $serviceIds,
        string $date,
        ?int $employeeId = null,
        ?int $locationId = null
    ): array {
        // Validate input
        if (empty($serviceIds)) {
            throw new BookingException('No services provided');
        }

        // Load all services
        $services = Service::find()
            ->id($serviceIds)
            ->status('enabled')
            ->all();

        if (count($services) !== count($serviceIds)) {
            throw new BookingException('One or more services not found');
        }

        // Reorder services according to input order
        $orderedServices = [];
        foreach ($serviceIds as $serviceId) {
            foreach ($services as $service) {
                if ($service->id === $serviceId) {
                    $orderedServices[] = $service;
                    break;
                }
            }
        }

        // Calculate total duration needed (including buffers)
        $totalDuration = 0;
        foreach ($orderedServices as $index => $service) {
            $totalDuration += $service->duration;
            // Add buffer after each service except the last one
            if ($index < count($orderedServices) - 1 && $service->bufferAfter) {
                $totalDuration += $service->bufferAfter;
            }
        }

        // Get availability service
        $availabilityService = Booked::getInstance()->availability;

        // Get all slots for the first service
        $firstService = $orderedServices[0];
        $firstServiceSlots = $availabilityService->getAvailableSlots(
            $date,
            $firstService->id,
            $employeeId,
            $locationId
        );

        $validSequenceSlots = [];

        // For each potential start time, check if entire sequence fits
        foreach ($firstServiceSlots as $startSlot) {
            $currentTime = new DateTime($date . ' ' . $startSlot['time']);
            $slotValid = true;

            // Track the end time to return it
            $endTime = clone $currentTime;
            $endTime->modify('+' . $totalDuration . ' minutes');

            // Check if each service can be booked at its calculated time
            foreach ($orderedServices as $index => $service) {
                $slotStartTime = $currentTime->format('H:i');

                // For services after the first, check availability
                if ($index > 0) {
                    $isAvailable = $availabilityService->checkSlotAvailability(
                        $date,
                        $slotStartTime,
                        $service->id,
                        $employeeId,
                        $locationId
                    );

                    if (!$isAvailable) {
                        $slotValid = false;
                        break;
                    }
                }

                // Advance time to next service (service duration + buffer)
                $currentTime->modify('+' . $service->duration . ' minutes');
                if ($index < count($orderedServices) - 1 && $service->bufferAfter) {
                    $currentTime->modify('+' . $service->bufferAfter . ' minutes');
                }
            }

            if ($slotValid) {
                $validSequenceSlots[] = [
                    'time' => $startSlot['time'],
                    'duration' => $totalDuration,
                    'endTime' => $endTime->format('H:i'),
                    'services' => array_map(fn($s) => $s->title, $orderedServices),
                    'serviceCount' => count($orderedServices)
                ];
            }
        }

        return $validSequenceSlots;
    }

    /**
     * Create a sequential booking
     *
     * @param array $data Booking data including serviceIds, date, startTime, customer info
     * @return BookingSequence
     * @throws BookingException
     * @throws \Throwable
     */
    public function createSequentialBooking(array $data): BookingSequence
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Validate required fields
            $this->validateBookingData($data);

            // Create the sequence element
            $sequence = new BookingSequence([
                'customerEmail' => $data['customerEmail'],
                'customerName' => $data['customerName'],
                'userId' => $data['userId'] ?? Craft::$app->getUser()->id ?? null,
                'status' => BookingSequenceRecord::STATUS_PENDING,
            ]);

            if (!Craft::$app->elements->saveElement($sequence)) {
                throw new BookingException('Failed to create booking sequence: ' . implode(', ', $sequence->getErrorSummary(true)));
            }

            // Load services in the correct order
            $services = [];
            foreach ($data['serviceIds'] as $serviceId) {
                $service = Service::find()->id($serviceId)->one();
                if (!$service) {
                    throw new BookingException("Service with ID {$serviceId} not found");
                }
                $services[] = $service;
            }

            // Create reservations for each service
            $currentTime = new DateTime($data['date'] . ' ' . $data['startTime']);
            $totalPrice = 0;

            foreach ($services as $index => $service) {
                $startTime = $currentTime->format('H:i');
                $currentTime->modify('+' . $service->duration . ' minutes');
                $endTime = $currentTime->format('H:i');

                // Create reservation
                $reservation = new Reservation([
                    'sequenceId' => $sequence->id,
                    'sequenceOrder' => $index,
                    'serviceId' => $service->id,
                    'employeeId' => $data['employeeId'] ?? null,
                    'locationId' => $data['locationId'] ?? null,
                    'bookingDate' => $data['date'],
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'userName' => $data['customerName'],
                    'userEmail' => $data['customerEmail'],
                    'userPhone' => $data['customerPhone'] ?? null,
                    'status' => ReservationRecord::STATUS_CONFIRMED,
                ]);

                if (!Craft::$app->elements->saveElement($reservation)) {
                    throw new BookingException('Failed to create reservation for ' . $service->title . ': ' . implode(', ', $reservation->getErrorSummary(true)));
                }

                $totalPrice += $service->price ?? 0;

                // Add buffer after this service (except last)
                if ($index < count($services) - 1 && $service->bufferAfter) {
                    $currentTime->modify('+' . $service->bufferAfter . ' minutes');
                }
            }

            // Update sequence with total price
            $sequence->totalPrice = $totalPrice;
            if (!Craft::$app->elements->saveElement($sequence)) {
                throw new BookingException('Failed to update sequence total price');
            }

            $transaction->commit();

            // Send confirmation email (if notification service available)
            try {
                $notificationService = Booked::getInstance()->notification;
                if ($notificationService) {
                    // Send email for first reservation (will include full sequence details)
                    $firstReservation = $sequence->getFirstReservation();
                    if ($firstReservation) {
                        $notificationService->sendBookingConfirmation($firstReservation);
                    }
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the booking
                Craft::error('Failed to send sequential booking confirmation email: ' . $e->getMessage(), __METHOD__);
            }

            return $sequence;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Validate booking data
     *
     * @param array $data
     * @throws BookingException
     */
    private function validateBookingData(array $data): void
    {
        $required = ['serviceIds', 'date', 'startTime', 'customerName', 'customerEmail'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new BookingException("Missing required field: {$field}");
            }
        }

        if (!is_array($data['serviceIds']) || empty($data['serviceIds'])) {
            throw new BookingException('serviceIds must be a non-empty array');
        }

        if (!filter_var($data['customerEmail'], FILTER_VALIDATE_EMAIL)) {
            throw new BookingException('Invalid email address');
        }

        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $data['date']);
        if (!$date || $date->format('Y-m-d') !== $data['date']) {
            throw new BookingException('Invalid date format. Expected Y-m-d');
        }

        // Validate time format
        $time = DateTime::createFromFormat('H:i', $data['startTime']);
        if (!$time || $time->format('H:i') !== $data['startTime']) {
            throw new BookingException('Invalid time format. Expected H:i');
        }
    }

    /**
     * Get suggested service sequences (predefined packages)
     *
     * This could be expanded to read from plugin settings or a database table
     *
     * @return array
     */
    public function getSuggestedSequences(): array
    {
        // For now, return empty array
        // This can be implemented later to fetch from settings or database
        return [];
    }

    /**
     * Get a booking sequence by ID
     *
     * @param int $id
     * @return BookingSequence|null
     */
    public function getSequenceById(int $id): ?BookingSequence
    {
        return BookingSequence::find()->id($id)->one();
    }

    /**
     * Get sequences for a customer
     *
     * @param string $email
     * @return BookingSequence[]
     */
    public function getSequencesByCustomerEmail(string $email): array
    {
        return BookingSequence::find()
            ->customerEmail($email)
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * Cancel a booking sequence
     *
     * @param int $sequenceId
     * @return bool
     * @throws BookingException
     */
    public function cancelSequence(int $sequenceId): bool
    {
        $sequence = $this->getSequenceById($sequenceId);

        if (!$sequence) {
            throw new BookingException('Booking sequence not found');
        }

        return $sequence->cancel();
    }

    /**
     * Confirm a booking sequence (change status from pending to confirmed)
     *
     * @param int $sequenceId
     * @return bool
     * @throws BookingException
     */
    public function confirmSequence(int $sequenceId): bool
    {
        $sequence = $this->getSequenceById($sequenceId);

        if (!$sequence) {
            throw new BookingException('Booking sequence not found');
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Confirm all reservations
            foreach ($sequence->getItems() as $item) {
                $item->status = ReservationRecord::STATUS_CONFIRMED;
                if (!Craft::$app->elements->saveElement($item)) {
                    throw new BookingException('Failed to confirm reservation: ' . implode(', ', $item->getErrorSummary(true)));
                }
            }

            // Update sequence status
            $sequence->status = BookingSequenceRecord::STATUS_CONFIRMED;
            if (!Craft::$app->elements->saveElement($sequence)) {
                throw new BookingException('Failed to confirm sequence: ' . implode(', ', $sequence->getErrorSummary(true)));
            }

            $transaction->commit();
            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
