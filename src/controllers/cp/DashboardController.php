<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;

class DashboardController extends Controller
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
     * Dashboard index - Shows KPI cards and widgets
     */
    public function actionIndex(): mixed
    {
        $today = new \DateTime('now', new \DateTimeZone('Europe/Zurich'));
        $today->setTime(0, 0, 0); // Start of day
        $weekFromNow = (clone $today)->modify('+7 days');

        // Today's bookings (confirmed only)
        $todayBookings = Reservation::find()
            ->bookingDate($today->format('Y-m-d'))
            ->status('confirmed')
            ->count();

        // Upcoming week bookings (next 7 days from today, confirmed only)
        $upcomingBookings = Reservation::find()
            ->bookingDate(['and', '>= ' . $today->format('Y-m-d'), '<= ' . $weekFromNow->format('Y-m-d')])
            ->status('confirmed')
            ->count();

        // Pending review (all pending regardless of date)
        $pendingBookings = Reservation::find()
            ->status('pending')
            ->count();

        // Total revenue (all confirmed bookings ever)
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $totalRevenue = 0;
        foreach ($reservations as $reservation) {
            if (isset($reservation->price)) {
                $totalRevenue += (float) $reservation->price;
            }
        }

        // Recent activity (last 10 bookings)
        $recentActivity = Reservation::find()
            ->withRelations() // Eager load to avoid N+1 in templates
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(10)
            ->all();

        // Popular services (top 3 by booking count)
        $allReservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $serviceBookings = [];
        foreach ($allReservations as $reservation) {
            if (isset($reservation->serviceId)) {
                if (!isset($serviceBookings[$reservation->serviceId])) {
                    $serviceBookings[$reservation->serviceId] = 0;
                }
                $serviceBookings[$reservation->serviceId]++;
            }
        }

        arsort($serviceBookings);
        $topServiceIds = array_slice(array_keys($serviceBookings), 0, 3, true);

        $popularServices = [];
        foreach ($topServiceIds as $serviceId) {
            $service = Service::find()->id($serviceId)->one();
            if ($service) {
                $popularServices[] = [
                    'name' => $service->title,
                    'bookings' => $serviceBookings[$serviceId],
                ];
            }
        }

        // Occupancy rate (simplified calculation - should be based on actual capacity)
        $totalSlots = 100; // This should be calculated based on actual schedules
        $bookedSlots = Reservation::find()
            ->status('confirmed')
            ->count();
        $occupancyRate = $totalSlots > 0 ? ($bookedSlots / $totalSlots) * 100 : 0;

        // Average booking value
        $confirmedCount = count($reservations);
        $averageBookingValue = $confirmedCount > 0 ? $totalRevenue / $confirmedCount : 0;

        // Get currency from Commerce or default to CHF
        $currency = 'CHF';
        if (Craft::$app->plugins->isPluginEnabled('commerce')) {
            try {
                $paymentCurrency = \craft\commerce\Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
                if ($paymentCurrency) {
                    $currency = $paymentCurrency->iso;
                }
            } catch (\Exception $e) {
                // Commerce might not be configured yet
            }
        }

        return $this->renderTemplate('booked/dashboard/index', [
            'todayBookings' => $todayBookings,
            'upcomingBookings' => $upcomingBookings,
            'pendingBookings' => $pendingBookings,
            'totalRevenue' => $totalRevenue,
            'recentActivity' => $recentActivity,
            'popularServices' => $popularServices,
            'occupancyRate' => $occupancyRate,
            'averageBookingValue' => $averageBookingValue,
            'currency' => $currency,
        ]);
    }
}
