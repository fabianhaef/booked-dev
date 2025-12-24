<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\elements\Employee;

class ReportsController extends Controller
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
     * Reports index
     */
    public function actionIndex(): mixed
    {
        return $this->renderTemplate('booked/reports/index');
    }

    /**
     * Revenue report by date range
     */
    public function actionRevenue(): mixed
    {
        $request = Craft::$app->request;
        $startDate = $request->getParam('startDate', (new \DateTime('first day of this month'))->format('Y-m-d'));
        $endDate = $request->getParam('endDate', (new \DateTime('last day of this month'))->format('Y-m-d'));

        $reservations = Reservation::find()
            ->status('confirmed')
            ->bookingDate(['and', '>= ' . $startDate, '<= ' . $endDate])
            ->all();

        $totalRevenue = 0;
        foreach ($reservations as $reservation) {
            if (isset($reservation->price)) {
                $totalRevenue += (float) $reservation->price;
            }
        }

        return $this->renderTemplate('booked/reports/revenue', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalRevenue' => $totalRevenue,
            'reservations' => $reservations,
        ]);
    }

    /**
     * Bookings by service report
     */
    public function actionByService(): mixed
    {
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $serviceBookings = [];
        foreach ($reservations as $reservation) {
            if (isset($reservation->serviceId)) {
                if (!isset($serviceBookings[$reservation->serviceId])) {
                    $serviceBookings[$reservation->serviceId] = [
                        'count' => 0,
                        'service' => null,
                    ];
                }
                $serviceBookings[$reservation->serviceId]['count']++;
            }
        }

        $totalBookings = array_sum(array_column($serviceBookings, 'count'));

        foreach ($serviceBookings as $serviceId => &$data) {
            $service = Service::find()->id($serviceId)->one();
            $data['service'] = $service;
            $data['percentage'] = $totalBookings > 0 ? ($data['count'] / $totalBookings) * 100 : 0;
        }

        uasort($serviceBookings, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $this->renderTemplate('booked/reports/by-service', [
            'serviceBookings' => $serviceBookings,
            'totalBookings' => $totalBookings,
        ]);
    }

    /**
     * Bookings by employee report
     */
    public function actionByEmployee(): mixed
    {
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $employeeBookings = [];
        foreach ($reservations as $reservation) {
            if (isset($reservation->employeeId)) {
                if (!isset($employeeBookings[$reservation->employeeId])) {
                    $employeeBookings[$reservation->employeeId] = [
                        'count' => 0,
                        'employee' => null,
                    ];
                }
                $employeeBookings[$reservation->employeeId]['count']++;
            }
        }

        foreach ($employeeBookings as $employeeId => &$data) {
            $employee = Employee::find()->id($employeeId)->one();
            $data['employee'] = $employee;
        }

        uasort($employeeBookings, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $this->renderTemplate('booked/reports/by-employee', [
            'employeeBookings' => $employeeBookings,
        ]);
    }

    /**
     * Cancellation rate report
     */
    public function actionCancellations(): mixed
    {
        $totalBookings = Reservation::find()->count();
        $cancelledBookings = Reservation::find()->status('cancelled')->count();

        $cancellationRate = $totalBookings > 0 ? ($cancelledBookings / $totalBookings) * 100 : 0;

        return $this->renderTemplate('booked/reports/cancellations', [
            'totalBookings' => $totalBookings,
            'cancelledBookings' => $cancelledBookings,
            'cancellationRate' => $cancellationRate,
        ]);
    }

    /**
     * Peak hours report
     */
    public function actionPeakHours(): mixed
    {
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $hourlyBookings = [];
        foreach ($reservations as $reservation) {
            if (isset($reservation->startTime)) {
                $hour = (int) explode(':', $reservation->startTime)[0];
                if (!isset($hourlyBookings[$hour])) {
                    $hourlyBookings[$hour] = 0;
                }
                $hourlyBookings[$hour]++;
            }
        }

        ksort($hourlyBookings);

        return $this->renderTemplate('booked/reports/peak-hours', [
            'hourlyBookings' => $hourlyBookings,
        ]);
    }

    /**
     * No-show rate report
     */
    public function actionNoShows(): mixed
    {
        $scheduledBookings = Reservation::find()
            ->status('confirmed')
            ->count();

        // Assuming we have a 'no-show' status or flag
        $noShows = Reservation::find()
            ->status('no-show')
            ->count();

        $noShowRate = $scheduledBookings > 0 ? ($noShows / $scheduledBookings) * 100 : 0;

        return $this->renderTemplate('booked/reports/no-shows', [
            'scheduledBookings' => $scheduledBookings,
            'noShows' => $noShows,
            'noShowRate' => $noShowRate,
        ]);
    }

    /**
     * Average booking lead time report
     */
    public function actionLeadTime(): mixed
    {
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $totalLeadTime = 0;
        $count = 0;

        foreach ($reservations as $reservation) {
            if (isset($reservation->dateCreated) && isset($reservation->bookingDate)) {
                $created = new \DateTime($reservation->dateCreated->format('Y-m-d'));
                $bookingDate = new \DateTime($reservation->bookingDate);
                $leadTime = $created->diff($bookingDate)->days;
                $totalLeadTime += $leadTime;
                $count++;
            }
        }

        $averageLeadTime = $count > 0 ? $totalLeadTime / $count : 0;

        return $this->renderTemplate('booked/reports/lead-time', [
            'averageLeadTime' => $averageLeadTime,
        ]);
    }

    /**
     * Revenue by day of week report
     */
    public function actionByDayOfWeek(): mixed
    {
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $weekdayRevenue = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];

        foreach ($reservations as $reservation) {
            if (isset($reservation->bookingDate) && isset($reservation->price)) {
                $date = new \DateTime($reservation->bookingDate);
                $dayOfWeek = (int) $date->format('N'); // 1 = Monday, 7 = Sunday
                $weekdayRevenue[$dayOfWeek] += (float) $reservation->price;
            }
        }

        return $this->renderTemplate('booked/reports/by-day-of-week', [
            'weekdayRevenue' => $weekdayRevenue,
        ]);
    }

    /**
     * Customer retention report
     */
    public function actionRetention(): mixed
    {
        // Group reservations by customer email
        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $customerBookings = [];
        foreach ($reservations as $reservation) {
            if (isset($reservation->customerEmail)) {
                $email = $reservation->customerEmail;
                if (!isset($customerBookings[$email])) {
                    $customerBookings[$email] = [
                        'total_bookings' => 0,
                        'first_booking' => null,
                        'last_booking' => null,
                    ];
                }
                $customerBookings[$email]['total_bookings']++;

                if (!$customerBookings[$email]['first_booking'] || $reservation->dateCreated < $customerBookings[$email]['first_booking']) {
                    $customerBookings[$email]['first_booking'] = $reservation->dateCreated;
                }

                if (!$customerBookings[$email]['last_booking'] || $reservation->dateCreated > $customerBookings[$email]['last_booking']) {
                    $customerBookings[$email]['last_booking'] = $reservation->dateCreated;
                }
            }
        }

        $totalCustomers = count($customerBookings);
        $returningCustomers = 0;

        foreach ($customerBookings as $data) {
            if ($data['total_bookings'] > 1) {
                $returningCustomers++;
            }
        }

        $retentionRate = $totalCustomers > 0 ? ($returningCustomers / $totalCustomers) * 100 : 0;

        return $this->renderTemplate('booked/reports/retention', [
            'totalCustomers' => $totalCustomers,
            'returningCustomers' => $returningCustomers,
            'retentionRate' => $retentionRate,
        ]);
    }

    /**
     * Export report to CSV
     */
    public function actionExportCsv(): Response
    {
        $request = Craft::$app->request;
        $reportType = $request->getParam('type', 'revenue');

        $reservations = Reservation::find()
            ->status('confirmed')
            ->all();

        $data = [
            ['Date', 'Service', 'Employee', 'Customer', 'Revenue', 'Status']
        ];

        foreach ($reservations as $reservation) {
            $service = isset($reservation->serviceId) ? Service::find()->id($reservation->serviceId)->one() : null;
            $employee = isset($reservation->employeeId) ? Employee::find()->id($reservation->employeeId)->one() : null;

            $data[] = [
                $reservation->bookingDate ?? '',
                $service ? $service->title : '',
                $employee ? $employee->title : '',
                $reservation->customerEmail ?? '',
                $reservation->price ?? '0.00',
                $reservation->status ?? '',
            ];
        }

        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return $this->response
            ->sendContentAsFile($csv, "booked-report-{$reportType}.csv", [
                'mimeType' => 'text/csv',
            ]);
    }
}
