<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\elements\Employee;

/**
 * Calendar View Controller - Handles calendar views (month/week/day)
 * Separate from CalendarController which handles OAuth
 */
class CalendarViewController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('booked-manageBookings');

        return true;
    }

    /**
     * Month view - Shows all reservations for a given month
     */
    public function actionMonth(): mixed
    {
        $request = Craft::$app->request;
        $year = $request->getParam('year', date('Y'));
        $month = $request->getParam('month', date('m'));

        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        $reservations = Reservation::find()
            ->bookingDate(['and', '>= ' . $startDate->format('Y-m-d'), '<= ' . $endDate->format('Y-m-d')])
            ->withRelations() // Eager load employee, service, location to avoid N+1
            ->orderBy(['bookingDate' => SORT_ASC, 'startTime' => SORT_ASC])
            ->all();

        // Group reservations by date
        $reservationsByDate = [];
        foreach ($reservations as $reservation) {
            $date = $reservation->bookingDate;
            if (!isset($reservationsByDate[$date])) {
                $reservationsByDate[$date] = [];
            }
            $reservationsByDate[$date][] = $reservation;
        }

        return $this->renderTemplate('booked/calendar/month', [
            'year' => $year,
            'month' => $month,
            'reservations' => $reservationsByDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Week view - Shows reservations grouped by day for a given week
     */
    public function actionWeek(): mixed
    {
        $request = Craft::$app->request;
        $date = $request->getParam('date', date('Y-m-d'));

        $currentDate = new \DateTime($date);

        // Get Monday of the week (ISO 8601)
        $dayOfWeek = $currentDate->format('N'); // 1 = Monday, 7 = Sunday
        $startDate = (clone $currentDate)->modify('-' . ($dayOfWeek - 1) . ' days');
        $endDate = (clone $startDate)->modify('+6 days');

        $reservations = Reservation::find()
            ->bookingDate(['and', '>= ' . $startDate->format('Y-m-d'), '<= ' . $endDate->format('Y-m-d')])
            ->withRelations() // Eager load employee, service, location to avoid N+1
            ->orderBy(['bookingDate' => SORT_ASC, 'startTime' => SORT_ASC])
            ->all();

        // Group by day
        $reservationsByDay = [];
        for ($i = 0; $i < 7; $i++) {
            $day = (clone $startDate)->modify("+{$i} days");
            $dayKey = $day->format('Y-m-d');
            $reservationsByDay[$dayKey] = [
                'date' => $day,
                'reservations' => [],
            ];
        }

        foreach ($reservations as $reservation) {
            $date = $reservation->bookingDate;
            if (isset($reservationsByDay[$date])) {
                $reservationsByDay[$date]['reservations'][] = $reservation;
            }
        }

        return $this->renderTemplate('booked/calendar/week', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reservationsByDay' => $reservationsByDay,
        ]);
    }

    /**
     * Day view - Shows hourly slots for a given day
     */
    public function actionDay(): mixed
    {
        $request = Craft::$app->request;
        $date = $request->getParam('date', date('Y-m-d'));

        $selectedDate = new \DateTime($date);

        $reservations = Reservation::find()
            ->bookingDate($selectedDate->format('Y-m-d'))
            ->withRelations() // Eager load to avoid N+1
            ->orderBy(['startTime' => SORT_ASC])
            ->all();

        // Create hourly slots from 8 AM to 8 PM
        $hourlySlots = [];
        for ($hour = 8; $hour <= 20; $hour++) {
            $hourKey = sprintf('%02d:00', $hour);
            $hourlySlots[$hourKey] = [];
        }

        // Assign reservations to slots
        foreach ($reservations as $reservation) {
            if (isset($reservation->startTime)) {
                $hour = (int) explode(':', $reservation->startTime)[0];
                $hourKey = sprintf('%02d:00', $hour);
                if (isset($hourlySlots[$hourKey])) {
                    $hourlySlots[$hourKey][] = $reservation;
                }
            }
        }

        return $this->renderTemplate('booked/calendar/day', [
            'date' => $selectedDate,
            'hourlySlots' => $hourlySlots,
        ]);
    }

    /**
     * Get color coding for reservation status
     */
    public function actionGetColorForStatus(): Response
    {
        $request = Craft::$app->request;
        $status = $request->getParam('status', 'pending');

        $colors = [
            'confirmed' => '#10b981', // green
            'pending' => '#f59e0b',   // amber
            'cancelled' => '#ef4444', // red
            'completed' => '#3b82f6', // blue
            'no-show' => '#6b7280',   // gray
        ];

        $color = $colors[$status] ?? '#9ca3af';

        return $this->asJson([
            'status' => $status,
            'color' => $color,
        ]);
    }

    /**
     * Handle drag-drop rescheduling
     */
    public function actionReschedule(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->request;
        $reservationId = $request->getBodyParam('reservationId');
        $newDate = $request->getBodyParam('newDate');
        $newStartTime = $request->getBodyParam('newStartTime');

        $reservation = Reservation::find()->id($reservationId)->one();

        if (!$reservation) {
            return $this->asJson([
                'success' => false,
                'error' => 'Reservation not found',
            ]);
        }

        $reservation->bookingDate = $newDate;
        if ($newStartTime) {
            $reservation->startTime = $newStartTime;

            // Calculate new end time based on duration
            if (isset($reservation->endTime)) {
                $oldStart = new \DateTime($reservation->startTime);
                $oldEnd = new \DateTime($reservation->endTime);
                $duration = $oldStart->diff($oldEnd);

                $newStart = new \DateTime($newStartTime);
                $newEnd = (clone $newStart)->add($duration);
                $reservation->endTime = $newEnd->format('H:i');
            }
        }

        if (!Craft::$app->elements->saveElement($reservation)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to save reservation',
                'errors' => $reservation->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'reservation' => [
                'id' => $reservation->id,
                'bookingDate' => $reservation->bookingDate,
                'startTime' => $reservation->startTime,
                'endTime' => $reservation->endTime,
            ],
        ]);
    }
}
