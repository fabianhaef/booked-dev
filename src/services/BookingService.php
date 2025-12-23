<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use craft\mail\Message;
use fabian\booked\Booked;
use fabian\booked\elements\Reservation;
use fabian\booked\exceptions\BookingConflictException;
use fabian\booked\exceptions\BookingException;
use fabian\booked\exceptions\BookingNotFoundException;
use fabian\booked\exceptions\BookingRateLimitException;
use fabian\booked\exceptions\BookingValidationException;
use fabian\booked\models\Settings;
use fabian\booked\queue\jobs\SendBookingEmailJob;
use fabian\booked\queue\jobs\SyncToCalendarJob;
use fabian\booked\records\ReservationRecord;

/**
 * Booking Service
 */
class BookingService extends Component
{
    /**
     * Get all reservations
     */
    public function getAllReservations(): array
    {
        return Reservation::find()
            ->orderBy(['bookingDate' => SORT_DESC, 'startTime' => SORT_DESC])
            ->all();
    }

    /**
     * Get reservations with pagination and filtering
     */
    public function getReservations(array $criteria = []): array
    {
        $query = Reservation::find();

        // Apply filters
        if (!empty($criteria['status'])) {
            $query->status($criteria['status']);
        }

        if (!empty($criteria['dateFrom'])) {
            $query->andWhere(['>=', 'bookings_reservations.bookingDate', $criteria['dateFrom']]);
        }

        if (!empty($criteria['dateTo'])) {
            $query->andWhere(['<=', 'bookings_reservations.bookingDate', $criteria['dateTo']]);
        }

        if (!empty($criteria['userEmail'])) {
            $query->userEmail($criteria['userEmail']);
        }

        // Apply ordering
        $orderBy = $criteria['orderBy'] ?? ['bookingDate' => SORT_DESC, 'startTime' => SORT_DESC];
        $query->orderBy($orderBy);

        // Apply pagination
        if (!empty($criteria['limit'])) {
            $query->limit($criteria['limit']);
        }

        if (!empty($criteria['offset'])) {
            $query->offset($criteria['offset']);
        }

        return $query->all();
    }

    /**
     * Get reservation by ID
     */
    public function getReservationById(int $id): ?Reservation
    {
        return Reservation::find()
            ->id($id)
            ->one();
    }

    /**
     * Get reservation by ID (legacy method name for backward compatibility)
     */
    public function getReservationByIdLegacy(int $id): ?Reservation
    {
        $record = ReservationRecord::findOne($id);
        if (!$record) {
            return null;
        }

        return Reservation::fromRecord($record);
    }

    /**
     * Get reservations for a specific date
     */
    public function getReservationsForDate(string $date): array
    {
        $records = ReservationRecord::find()
            ->where(['bookingDate' => $date])
            ->orderBy(['startTime' => SORT_ASC])
            ->all();

        $reservations = [];
        foreach ($records as $record) {
            $reservations[] = Reservation::fromRecord($record);
        }

        return $reservations;
    }

    /**
     * Get upcoming reservations
     */
    public function getUpcomingReservations(int $limit = 10): array
    {
        $today = date('Y-m-d');
        $records = ReservationRecord::find()
            ->where(['>=', 'bookingDate', $today])
            ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED])
            ->orderBy(['bookingDate' => SORT_ASC, 'startTime' => SORT_ASC])
            ->limit($limit)
            ->all();

        $reservations = [];
        foreach ($records as $record) {
            $reservations[] = Reservation::fromRecord($record);
        }

        return $reservations;
    }

    /**
     * Create reservation model
     */
    protected function createReservationModel(): Reservation
    {
        return new Reservation();
    }

    /**
     * Get plugin settings
     */
    protected function getSettingsModel(): Settings
    {
        return Settings::loadSettings();
    }

    /**
     * Create a new booking (wrapper for createReservation with simplified array keys)
     */
    public function createBooking(array $data): bool
    {
        $reservationData = [
            'userName' => $data['customerName'] ?? '',
            'userEmail' => $data['customerEmail'] ?? '',
            'bookingDate' => $data['date'] ?? '',
            'startTime' => $data['time'] ?? '',
            'serviceId' => $data['serviceId'] ?? null,
            'employeeId' => $data['employeeId'] ?? null,
            'locationId' => $data['locationId'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'notes' => $data['notes'] ?? null,
            'softLockToken' => $data['softLockToken'] ?? null,
        ];

        try {
            $this->createReservation($reservationData);
            return true;
        } catch (\Exception $e) {
            throw new BookingException($e->getMessage());
        }
    }

    /**
     * Create a new reservation
     *
     * Uses mutex locking and database transaction to prevent race conditions
     *
     * @param array $data Reservation data
     * @return Reservation The created reservation
     * @throws BookingRateLimitException If rate limit is exceeded
     * @throws BookingConflictException If slot is unavailable or already booked
     * @throws BookingValidationException If validation fails
     */
    public function createReservation(array $data): Reservation
    {
        // Rate limiting - check email
        $userEmail = $data['userEmail'] ?? '';
        if ($userEmail && !$this->checkEmailRateLimit($userEmail)) {
            Craft::warning("Booking blocked: Email rate limit exceeded for {$userEmail}", __METHOD__);
            throw new BookingRateLimitException('Sie haben zu viele Buchungen von dieser E-Mail-Adresse vorgenommen. Bitte versuchen Sie es später erneut.');
        }

        // Rate limiting - check IP (skip for console requests)
        $ipAddress = $data['ipAddress'] ?? null;
        if ($ipAddress === null && !$this->getRequestService()->getIsConsoleRequest()) {
            $ipAddress = $this->getRequestService()->getUserIP();
        }
        if ($ipAddress && !$this->checkIPRateLimit($ipAddress)) {
            Craft::warning("Booking blocked: IP rate limit exceeded for {$ipAddress}", __METHOD__);
            throw new BookingRateLimitException('Sie haben zu viele Buchungen von dieser IP-Adresse vorgenommen. Bitte versuchen Sie es später erneut.');
        }

        // CRITICAL: Acquire mutex lock to prevent race conditions with overlapping bookings
        // Lock on the specific slot to allow concurrent bookings for different slots
        $bookingDate = $data['bookingDate'] ?? '';
        $startTime = $data['startTime'] ?? '';
        $employeeId = $data['employeeId'] ?? null;
        $locationId = $data['locationId'] ?? null;
        $serviceId = $data['serviceId'] ?? null;
        $softLockToken = $data['softLockToken'] ?? null;
        
        $lockKey = "booked-booking-{$bookingDate}-{$startTime}-" . ($employeeId ?? 'any') . "-" . ($serviceId ?? 'any');
        $mutex = $this->getMutex();

        // Try to acquire lock with 10 second timeout
        if (!$mutex->acquire($lockKey, 10)) {
            Craft::warning("Could not acquire booking lock for {$bookingDate}", __METHOD__);
            throw new BookingConflictException('Das Buchungssystem ist derzeit ausgelastet. Bitte versuchen Sie es in einem Moment erneut.');
        }

        // Wrap entire booking logic in try-finally to ensure mutex is ALWAYS released
        try {
            // Begin database transaction to prevent race conditions
            $transaction = $this->getDb()->beginTransaction();

            try {
                // Check for soft locks (unless it's the user's own lock)
                if (Booked::getInstance()->getSoftLock()->isLocked($bookingDate, $startTime, $serviceId, $employeeId)) {
                    // Check if it's our lock
                    $ourLock = false;
                    if ($softLockToken) {
                        $lock = Booked::getInstance()->getSoftLock()->getRecordByToken($softLockToken);
                        if ($lock && $lock->date === $bookingDate && $lock->startTime === $startTime) {
                            $ourLock = true;
                        }
                    }

                    if (!$ourLock) {
                        $transaction->rollBack();
                        throw new BookingConflictException('Dieser Zeitslot ist vorübergehend reserviert. Bitte versuchen Sie es in 15 Minuten erneut.');
                    }
                }

                // Calculate end time based on service duration if not provided
                $endTime = $data['endTime'] ?? '';
                if (empty($endTime) && $serviceId) {
                    $service = $this->getServiceById($serviceId);
                    if ($service) {
                        $startDateTime = new \DateTime($bookingDate . ' ' . $startTime);
                        $endDateTime = (clone $startDateTime)->modify("+{$service->duration} minutes");
                        $endTime = $endDateTime->format('H:i');
                    }
                }

                $reservation = $this->createReservationModel();
                $reservation->userName = $data['userName'] ?? '';
                $reservation->userEmail = $userEmail;
                $reservation->userPhone = $data['userPhone'] ?? null;
                $reservation->userTimezone = $data['userTimezone'] ?? $this->detectUserTimezone();
                $reservation->bookingDate = $bookingDate;
                $reservation->startTime = $startTime;
                $reservation->endTime = $endTime;
                $reservation->status = $data['status'] ?? ReservationRecord::STATUS_CONFIRMED;
                $reservation->notes = $data['notes'] ?? null;

                // Store relationships
                $reservation->employeeId = $employeeId;
                $reservation->locationId = $locationId;
                $reservation->serviceId = $serviceId;

                // Store source information from availability
                $reservation->sourceType = $data['sourceType'] ?? null;
                $reservation->sourceId = $data['sourceId'] ?? null;
                $reservation->sourceHandle = $data['sourceHandle'] ?? null;
                $reservation->variationId = $data['variationId'] ?? null;

                // Extract and validate quantity (default to 1 for backward compatibility)
                $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
                $reservation->quantity = max(1, $quantity); // Ensure at least 1

                // Validate availability INSIDE transaction for consistency
                // CRITICAL: Pass quantity to check capacity-based availability
                if (!$this->getAvailabilityService()->isSlotAvailable(
                    $reservation->bookingDate,
                    $reservation->startTime,
                    $reservation->endTime,
                    $reservation->employeeId,
                    $reservation->locationId,
                    $reservation->serviceId,
                    $reservation->quantity
                )) {
                    $transaction->rollBack();
                    Craft::error('Attempted to book unavailable slot: ' .
                        $reservation->bookingDate . ' ' .
                        $reservation->startTime . '-' .
                        $reservation->endTime .
                        ' (variation: ' . ($reservation->variationId ?? 'none') . ', quantity: ' . $reservation->quantity . ')', __METHOD__);
                    throw new BookingConflictException('Der gewählte Zeitslot hat nicht genügend Kapazität für die angeforderte Anzahl.');
                }
                
                // Save reservation - unique constraint will catch any race conditions
                if (!$this->getElementsService()->saveElement($reservation)) {
                    $transaction->rollBack();
                    Craft::error('Failed to save reservation: ' . json_encode($reservation->getErrors()), __METHOD__);
                    throw new BookingValidationException('Die Buchungsvalidierung ist fehlgeschlagen.', $reservation->getErrors());
                }

                // Phase 4.2 - Commerce Integration
                if (Booked::getInstance()->isCommerceEnabled()) {
                    $service = $reservation->getService();
                    if ($service && $service->price > 0) {
                        // For paid services, set status to pending until paid
                        $reservation->status = ReservationRecord::STATUS_PENDING;
                        $this->getElementsService()->saveElement($reservation);
                        
                        // Add to cart and link to order
                        Booked::getInstance()->commerce->addReservationToCart($reservation);
                    }
                }

                // Log successful booking with variation and quantity info
                Craft::info(
                    "Reservation created: ID {$reservation->id} | " .
                    "Date: {$reservation->bookingDate} {$reservation->startTime}-{$reservation->endTime} | " .
                    "Variation: " . ($reservation->variationId ? "ID {$reservation->variationId}" : "none") . " | " .
                    "Quantity: {$reservation->quantity} | " .
                    "Email: {$reservation->userEmail}",
                    __METHOD__
                );

                // Commit transaction first to ensure booking is saved
                $transaction->commit();

                // Generate virtual meeting if needed
                if ($reservation->getService() && $reservation->getService()->virtualMeetingProvider) {
                    $virtualMeetingService = Booked::getInstance()->getVirtualMeeting();
                    $meetingUrl = $virtualMeetingService->createMeeting($reservation, $reservation->getService()->virtualMeetingProvider);
                    
                    if ($meetingUrl) {
                        // Re-save the reservation with meeting details
                        $this->getElementsService()->saveElement($reservation);
                        Craft::info("Created virtual meeting for reservation #{$reservation->id}: {$meetingUrl}", __METHOD__);
                    }
                }

                // Invalidate availability cache for this date
                $this->getAvailabilityCacheService()->invalidateDateCache($reservation->bookingDate);

                // Release soft lock if exists
                if ($softLockToken) {
                    Booked::getInstance()->getSoftLock()->releaseLock($softLockToken);
                }

                // Queue confirmation email to client AFTER commit (async, failure won't affect booking)
                $this->queueBookingEmail($reservation->id, 'confirmation');

                // Queue notification email to owner if enabled
                $this->queueOwnerNotification($reservation->id);

                // Queue calendar sync
                $this->queueCalendarSync($reservation->id);

                return $reservation;

            } catch (\yii\db\IntegrityException $e) {
                // Handle unique constraint violation (race condition caught!)
                $transaction->rollBack();

                if (strpos($e->getMessage(), 'idx_unique_active_booking') !== false) {
                    Craft::warning('Booking conflict: Slot already booked (race condition prevented): ' .
                        $data['bookingDate'] . ' ' .
                        $data['startTime'] . '-' .
                        $data['endTime'], __METHOD__);
                    throw new BookingConflictException('Dieser Zeitslot wurde gerade von einem anderen Benutzer gebucht. Bitte wählen Sie eine andere Zeit.');
                } else {
                    Craft::error('Database integrity error: ' . $e->getMessage(), __METHOD__);
                    throw new BookingException('Ein Datenbankfehler ist beim Erstellen der Buchung aufgetreten. Bitte versuchen Sie es erneut.');
                }

            } catch (BookingRateLimitException | BookingConflictException | BookingValidationException $e) {
                // Re-throw our custom exceptions
                throw $e;

            } catch (\Throwable $e) {
                // Handle any other unexpected errors
                $transaction->rollBack();
                Craft::error('Booking creation failed: ' . $e->getMessage(), __METHOD__);
                throw new BookingException('Ein unerwarteter Fehler ist beim Erstellen der Buchung aufgetreten: ' . $e->getMessage());
            }

        } finally {
            // CRITICAL: Always release the mutex lock, even if an exception was thrown
            $mutex->release($lockKey);
            Craft::info("Released booking lock for {$bookingDate}", __METHOD__);
        }
    }

    /**
     * Update an existing reservation
     *
     * @param int $id Reservation ID
     * @param array $data Update data
     * @return Reservation The updated reservation
     * @throws BookingNotFoundException If reservation not found
     * @throws BookingConflictException If updated slot is unavailable
     * @throws BookingValidationException If validation fails
     */
    public function updateReservation(int $id, array $data): Reservation
    {
        $reservation = $this->getReservationById($id);
        if (!$reservation) {
            Craft::error('Reservation not found with ID: ' . $id, __METHOD__);
            throw new BookingNotFoundException('Reservation not found.');
        }

        $oldStatus = $reservation->status;

        // Update fields
        if (isset($data['userName'])) {
            $reservation->userName = $data['userName'];
        }
        if (isset($data['userEmail'])) {
            $reservation->userEmail = $data['userEmail'];
        }
        if (isset($data['userPhone'])) {
            $reservation->userPhone = $data['userPhone'];
        }
        if (isset($data['bookingDate'])) {
            $reservation->bookingDate = $data['bookingDate'];
        }
        if (isset($data['startTime'])) {
            $reservation->startTime = $data['startTime'];
        }
        if (isset($data['endTime'])) {
            $reservation->endTime = $data['endTime'];
        }
        if (isset($data['status'])) {
            $reservation->status = $data['status'];
        }
        if (isset($data['notes'])) {
            $reservation->notes = $data['notes'];
        }

        // If time/date changed, validate availability
        if (isset($data['bookingDate']) || isset($data['startTime']) || isset($data['endTime'])) {
            if (!$this->getAvailabilityService()->isSlotAvailable(
                $reservation->bookingDate,
                $reservation->startTime,
                $reservation->endTime
            )) {
                Craft::error('Attempted to update reservation to unavailable slot', __METHOD__);
                throw new BookingConflictException('Der gewählte Zeitslot ist nicht verfügbar.');
            }
        }

        if (!Craft::$app->elements->saveElement($reservation)) {
            Craft::error('Failed to update reservation: ' . json_encode($reservation->getErrors()), __METHOD__);
            throw new BookingValidationException('Die Aktualisierung der Buchung ist fehlgeschlagen.', $reservation->getErrors());
        }

        // Queue notification if status changed
        if ($oldStatus !== $reservation->status) {
            $this->queueBookingEmail($reservation->id, 'status_change', $oldStatus);
        }

        return $reservation;
    }

    /**
     * Cancel a reservation
     */
    public function cancelReservation(int $id, string $reason = ''): bool
    {
        $reservation = $this->getReservationById($id);
        if (!$reservation || !$this->canCancelReservation($reservation)) {
            return false;
        }

        $reservation->status = ReservationRecord::STATUS_CANCELLED;
        if ($reason) {
            $reservation->notes = ($reservation->notes ? $reservation->notes . "\n\n" : '') .
                                 "Stornierungsgrund: " . $reason;
        }

        if (Craft::$app->elements->saveElement($reservation)) {
            // Queue cancellation notification
            $this->queueBookingEmail($reservation->id, 'cancellation');
            return true;
        }

        return false;
    }

    /**
     * Check if a reservation can be cancelled
     * 
     * @param Reservation $reservation
     * @return bool
     */
    protected function canCancelReservation(Reservation $reservation): bool
    {
        if ($reservation->status === ReservationRecord::STATUS_CANCELLED) {
            return false;
        }

        $now = new \DateTime();
        $bookingDateTime = new \DateTime($reservation->bookingDate . ' ' . ($reservation->startTime ?? '00:00'));

        if ($bookingDateTime < $now) {
            return false;
        }

        return true;
    }

    /**
     * Delete a reservation
     */
    public function deleteReservation(int $id): bool
    {
        $reservation = $this->getReservationById($id);
        if (!$reservation) {
            return false;
        }

        return $reservation->delete();
    }

    /**
     * Queue booking email for asynchronous sending
     *
     * @param int $reservationId
     * @param string $emailType 'confirmation', 'status_change', 'cancellation', 'owner_notification'
     * @param string|null $oldStatus For status change emails
     * @param int $priority Lower number = higher priority (default: 1024)
     */
    public function queueBookingEmail(
        int $reservationId,
        string $emailType,
        ?string $oldStatus = null,
        int $priority = 1024
    ): void {
        $job = new SendBookingEmailJob([
            'reservationId' => $reservationId,
            'emailType' => $emailType,
            'oldStatus' => $oldStatus,
        ]);

        $this->getQueueService()->priority($priority)->push($job);

        Craft::info(
            "Queued {$emailType} email for reservation #{$reservationId}",
            __METHOD__
        );
    }

    /**
     * Queue calendar sync for a reservation
     */
    public function queueCalendarSync(int $reservationId, int $priority = 1024): void
    {
        $job = new SyncToCalendarJob([
            'reservationId' => $reservationId,
        ]);

        $this->getQueueService()->priority($priority)->push($job);

        Craft::info(
            "Queued calendar sync for reservation #{$reservationId}",
            __METHOD__
        );
    }

    /**
     * Queue owner notification email
     *
     * Sends a notification to the site owner when a new booking is created.
     * Only queues if owner notification is enabled in settings.
     *
     * @param int $reservationId
     * @param int $priority Lower number = higher priority (default: 1024)
     */
    public function queueOwnerNotification(int $reservationId, int $priority = 1024): void
    {
        $settings = $this->getSettingsModel();

        // Check if owner notification is enabled
        if (!$settings->ownerNotificationEnabled) {
            Craft::info("Owner notification disabled - skipping for reservation #{$reservationId}", __METHOD__);
            return;
        }

        // Ensure we have an owner email configured
        $ownerEmail = $settings->getEffectiveEmail();
        if (empty($ownerEmail)) {
            Craft::warning("No owner email configured - skipping notification for reservation #{$reservationId}", __METHOD__);
            return;
        }

        $job = new SendBookingEmailJob([
            'reservationId' => $reservationId,
            'emailType' => 'owner_notification',
        ]);

        $this->getQueueService()->priority($priority)->push($job);

        Craft::info(
            "Queued owner notification email for reservation #{$reservationId}",
            __METHOD__
        );
    }

    /**
     * Send booking confirmation email (synchronous - for manual/immediate sending)
     *
     * @deprecated Use queueBookingEmail() instead for better reliability
     */
    public function sendBookingConfirmation(Reservation $reservation): bool
    {
        try {
            $settings = Settings::loadSettings();

            $subject = $settings->bookingConfirmationSubject ?: 'Booking Confirmation';
            $body = $this->renderConfirmationEmail($reservation, $settings);

            // Get system email settings
            $fromEmail = $settings->ownerEmail ?: Craft::$app->projectConfig->get('email.fromEmail');
            $fromName = $settings->ownerName ?: Craft::$app->projectConfig->get('email.fromName');

            $message = new Message();
            $message->setTo($reservation->userEmail)
                   ->setFrom([$fromEmail => $fromName])
                   ->setSubject($subject)
                   ->setHtmlBody($body);

            $sent = Craft::$app->mailer->send($message);

            if ($sent) {
                // Mark notification as sent
                $reservation->notificationSent = true;
                Craft::$app->elements->saveElement($reservation);
            }

            return $sent;
        } catch (\Exception $e) {
            Craft::error('Failed to send booking confirmation: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Send status change notification (synchronous - for manual/immediate sending)
     *
     * @deprecated Use queueBookingEmail() instead for better reliability
     */
    public function sendStatusChangeNotification(Reservation $reservation, string $oldStatus): bool
    {
        try {
            $settings = Settings::loadSettings();

            $subject = 'Booking Status Update';
            $body = $this->renderStatusChangeEmail($reservation, $oldStatus, $settings);

            // Get system email settings
            $fromEmail = $settings->ownerEmail ?: Craft::$app->projectConfig->get('email.fromEmail');
            $fromName = $settings->ownerName ?: Craft::$app->projectConfig->get('email.fromName');

            $message = new Message();
            $message->setTo($reservation->userEmail)
                   ->setFrom([$fromEmail => $fromName])
                   ->setSubject($subject)
                   ->setHtmlBody($body);

            return Craft::$app->mailer->send($message);
        } catch (\Exception $e) {
            Craft::error('Failed to send status change notification: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Send cancellation notification (synchronous - for manual/immediate sending)
     *
     * @deprecated Use queueBookingEmail() instead for better reliability
     */
    public function sendCancellationNotification(Reservation $reservation): bool
    {
        try {
            $settings = Settings::loadSettings();

            $subject = 'Booking Cancelled';
            $body = $this->renderCancellationEmail($reservation, $settings);

            // Get system email settings
            $fromEmail = $settings->ownerEmail ?: Craft::$app->projectConfig->get('email.fromEmail');
            $fromName = $settings->ownerName ?: Craft::$app->projectConfig->get('email.fromName');

            $message = new Message();
            $message->setTo($reservation->userEmail)
                   ->setFrom([$fromEmail => $fromName])
                   ->setSubject($subject)
                   ->setHtmlBody($body);

            return Craft::$app->mailer->send($message);
        } catch (\Exception $e) {
            Craft::error('Failed to send cancellation notification: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Render confirmation email template
     */
    private function renderConfirmationEmail(Reservation $reservation, Settings $settings): string
    {
        // Get variation information if available
        $variationInfo = '';
        if ($reservation->variationId) {
            $variation = \fabian\booked\elements\BookingVariation::find()
                ->id($reservation->variationId)
                ->one();
            if ($variation) {
                $variationInfo = $variation->title;
            }
        }

        // Format date nicely
        $dateObj = \DateTime::createFromFormat('Y-m-d', $reservation->bookingDate);
        $formattedDate = $dateObj ? $dateObj->format('d.m.Y') : $reservation->bookingDate;

        $variables = [
            'userName' => $reservation->userName,
            'userEmail' => $reservation->userEmail,
            'bookingDate' => $formattedDate,
            'startTime' => $reservation->startTime,
            'endTime' => $reservation->endTime,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'status' => $reservation->getStatusLabel(),
            'notes' => $reservation->notes ?: '',
            'variationName' => $variationInfo,
            'quantity' => $reservation->quantity,
            'quantityDisplay' => $reservation->quantity > 1,
            'ownerName' => $settings->ownerName,
            'managementUrl' => $reservation->getManagementUrl(),
            'cancelUrl' => $reservation->getCancelUrl(),
        ];

        // Use custom template if defined in settings
        if (!empty($settings->bookingConfirmationBody)) {
            return Craft::$app->view->renderString($settings->bookingConfirmationBody, $variables);
        }

        return Craft::$app->view->renderTemplate('booked/emails/confirmation', $variables);
    }

    /**
     * Render status change email template
     */
    private function renderStatusChangeEmail(Reservation $reservation, string $oldStatus, Settings $settings): string
    {
        $variables = [
            'userName' => $reservation->userName,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'oldStatus' => ucfirst($oldStatus),
            'newStatus' => $reservation->getStatusLabel(),
            'ownerName' => $settings->ownerName,
            'managementUrl' => $reservation->getManagementUrl(),
        ];

        return Craft::$app->view->renderTemplate('booked/emails/status-change', $variables);
    }

    /**
     * Render cancellation email template
     */
    private function renderCancellationEmail(Reservation $reservation, Settings $settings): string
    {
        $variables = [
            'userName' => $reservation->userName,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'ownerName' => $settings->ownerName,
        ];

        return Craft::$app->view->renderTemplate('booked/emails/cancellation', $variables);
    }

    /**
     * Get DB service
     */
    protected function getDb()
    {
        return Craft::$app->db;
    }

    /**
     * Get elements service
     */
    protected function getElementsService()
    {
        return Craft::$app->elements;
    }

    /**
     * Get mutex service
     */
    protected function getMutex()
    {
        return Craft::$app->mutex;
    }

    /**
     * Get availability cache service
     */
    protected function getAvailabilityCacheService(): AvailabilityCacheService
    {
        return Booked::getInstance()->getAvailabilityCache();
    }

    /**
     * Get queue service
     */
    protected function getQueueService()
    {
        return Craft::$app->queue;
    }

    /**
     * Get cache service
     */
    protected function getCacheService()
    {
        return Craft::$app->cache;
    }

    /**
     * Get request service
     */
    protected function getRequestService()
    {
        return Craft::$app->request;
    }

    /**
     * Get service by ID
     */
    protected function getServiceById(int $id): ?\fabian\booked\elements\Service
    {
        return \fabian\booked\elements\Service::findOne($id);
    }

    /**
     * Get reservation record query
     */
    protected function getReservationRecordQuery(): \yii\db\ActiveQuery
    {
        return ReservationRecord::find();
    }

    /**
     * Detect user's timezone from browser or default to Europe/Zurich
     * Can be enhanced with IP-based detection or browser timezone detection
     */
    private function detectUserTimezone(): string
    {
        // Default timezone for the application
        $defaultTimezone = 'Europe/Zurich';

        // Skip session in test environment
        if (defined('CRAFT_ENVIRONMENT') && CRAFT_ENVIRONMENT === 'test') {
            return $defaultTimezone;
        }

        // Try to get timezone from session if previously set
        try {
            $session = Craft::$app->session;
            if ($session->has('userTimezone')) {
                return $session->get('userTimezone');
            }
        } catch (\Exception $e) {
            // Session not available, use default
            Craft::warning("Could not access session for timezone: " . $e->getMessage(), __METHOD__);
        }

        // Could add IP-based detection here in the future
        // For now, return default
        return $defaultTimezone;
    }

    /**
     * Check if email has exceeded rate limit
     */
    private function checkEmailRateLimit(string $email): bool
    {
        $settings = $this->getSettingsModel();

        // Skip in test environment to avoid session issues
        if (defined('CRAFT_ENVIRONMENT') && CRAFT_ENVIRONMENT === 'test') {
            return true;
        }

        $today = date('Y-m-d');

        try {
            // Check total bookings per email today
            $bookingsToday = $this->getReservationRecordQuery()
                ->where(['userEmail' => $email])
                ->andWhere(['>=', 'dateCreated', $today . ' 00:00:00'])
                ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED])
                ->count();

            if ($bookingsToday >= $settings->maxBookingsPerEmail) {
                return false;
            }

            // Check time between bookings
            $lastBooking = $this->getReservationRecordQuery()
                ->where(['userEmail' => $email])
                ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED])
                ->orderBy(['dateCreated' => SORT_DESC])
                ->one();

            if ($lastBooking) {
                $lastBookingTime = strtotime($lastBooking->dateCreated);
                $now = time();
                $minutesSinceLastBooking = ($now - $lastBookingTime) / 60;

                if ($minutesSinceLastBooking < $settings->rateLimitMinutes) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            // If there's an error (e.g., session not available), skip rate limiting
            Craft::warning("Could not check email rate limit: " . $e->getMessage(), __METHOD__);
            return true;
        }
    }

    /**
     * Check if IP address has exceeded rate limit
     */
    private function checkIPRateLimit(string $ipAddress): bool
    {
        $settings = $this->getSettingsModel();

        // Skip in test environment
        if (defined('CRAFT_ENVIRONMENT') && CRAFT_ENVIRONMENT === 'test') {
            return true;
        }

        $today = date('Y-m-d');
        $cache = $this->getCacheService();
        $cacheKey = 'booking_ip_limit_' . md5($ipAddress);

        try {
            // Get existing bookings from cache
            $ipBookings = $cache->get($cacheKey) ?: [];

            // Clean old entries (older than today)
            $ipBookings = array_filter($ipBookings, function($timestamp) use ($today) {
                return date('Y-m-d', $timestamp) === $today;
            });

            // Check if exceeded max bookings per IP
            if (count($ipBookings) >= $settings->maxBookingsPerIP) {
                return false;
            }

            // Check time between bookings
            if (!empty($ipBookings)) {
                $lastBookingTime = max($ipBookings);
                $now = time();
                $minutesSinceLastBooking = ($now - $lastBookingTime) / 60;

                if ($minutesSinceLastBooking < $settings->rateLimitMinutes) {
                    return false;
                }
            }

            // Track this booking attempt
            $ipBookings[] = time();
            
            // Save back to cache, expire after 24 hours
            $cache->set($cacheKey, $ipBookings, 86400);

            return true;
        } catch (\Exception $e) {
            Craft::warning("Could not check IP rate limit: " . $e->getMessage(), __METHOD__);
            // Fail safe: allow booking if cache is down, but log it
            return true;
        }
    }

    /**
     * Get booking statistics
     */
    public function getBookingStats(): array
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));

        return [
            'totalBookings' => ReservationRecord::find()->count(),
            'confirmedBookings' => ReservationRecord::find()
                ->where(['status' => ReservationRecord::STATUS_CONFIRMED])
                ->count(),
            'pendingBookings' => ReservationRecord::find()
                ->where(['status' => ReservationRecord::STATUS_PENDING])
                ->count(),
            'todayBookings' => ReservationRecord::find()
                ->where(['bookingDate' => $today])
                ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED])
                ->count(),
            'thisMonthBookings' => ReservationRecord::find()
                ->where(['>=', 'bookingDate', $thisMonth])
                ->where(['<', 'bookingDate', $nextMonth])
                ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED])
                ->count(),
        ];
    }
}
