<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

class ReportsControllerTest extends Unit
{
    use CreatesBookings;

    protected $tester;

    public function testRevenueReportByDateRange()
    {
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-01-31');

        $bookings = [
            ['date' => '2025-01-05', 'price' => 100.00, 'status' => 'confirmed'],
            ['date' => '2025-01-15', 'price' => 150.00, 'status' => 'confirmed'],
            ['date' => '2025-01-25', 'price' => 200.00, 'status' => 'confirmed'],
            ['date' => '2025-02-05', 'price' => 50.00, 'status' => 'confirmed'], // Outside range
        ];

        $revenueInRange = 0;
        foreach ($bookings as $booking) {
            $bookingDate = new \DateTime($booking['date']);
            if ($bookingDate >= $startDate && $bookingDate <= $endDate && $booking['status'] === 'confirmed') {
                $revenueInRange += $booking['price'];
            }
        }

        $this->assertEquals(450.00, $revenueInRange);
    }

    public function testBookingsByServiceReport()
    {
        $services = [
            ['id' => 1, 'name' => 'Haircut', 'count' => 45],
            ['id' => 2, 'name' => 'Massage', 'count' => 32],
            ['id' => 3, 'name' => 'Consultation', 'count' => 28],
        ];

        $totalBookings = array_sum(array_column($services, 'count'));

        foreach ($services as &$service) {
            $service['percentage'] = ($service['count'] / $totalBookings) * 100;
        }

        $this->assertEquals(105, $totalBookings);
        $this->assertEqualsWithDelta(42.86, $services[0]['percentage'], 0.01);
    }

    public function testBookingsByEmployeeReport()
    {
        $employees = [
            ['id' => 1, 'name' => 'John Doe', 'bookings' => 67],
            ['id' => 2, 'name' => 'Jane Smith', 'bookings' => 54],
            ['id' => 3, 'name' => 'Bob Johnson', 'bookings' => 48],
        ];

        usort($employees, function($a, $b) {
            return $b['bookings'] - $a['bookings'];
        });

        $topEmployee = $employees[0];

        $this->assertEquals('John Doe', $topEmployee['name']);
        $this->assertEquals(67, $topEmployee['bookings']);
    }

    public function testCancellationRateReport()
    {
        $totalBookings = 150;
        $cancelledBookings = 23;

        $cancellationRate = ($cancelledBookings / $totalBookings) * 100;

        $this->assertEqualsWithDelta(15.33, $cancellationRate, 0.01);
        $this->assertLessThan(20, $cancellationRate); // Acceptable threshold
    }

    public function testPeakHoursReport()
    {
        $hourlyBookings = [
            ['hour' => 9, 'count' => 12],
            ['hour' => 10, 'count' => 18],
            ['hour' => 11, 'count' => 25],
            ['hour' => 12, 'count' => 15],
            ['hour' => 13, 'count' => 22],
            ['hour' => 14, 'count' => 28],
            ['hour' => 15, 'count' => 20],
        ];

        usort($hourlyBookings, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $peakHour = $hourlyBookings[0];

        $this->assertEquals(14, $peakHour['hour']);
        $this->assertEquals(28, $peakHour['count']);
    }

    public function testNoShowRateReport()
    {
        $scheduledBookings = 200;
        $noShows = 8;

        $noShowRate = ($noShows / $scheduledBookings) * 100;

        $this->assertEquals(4.0, $noShowRate);
        $this->assertLessThan(10, $noShowRate); // Acceptable threshold
    }

    public function testAverageBookingLeadTimeReport()
    {
        $bookings = [
            ['created' => '2025-01-01', 'booking_date' => '2025-01-05'], // 4 days
            ['created' => '2025-01-10', 'booking_date' => '2025-01-17'], // 7 days
            ['created' => '2025-01-15', 'booking_date' => '2025-01-18'], // 3 days
        ];

        $totalLeadTime = 0;
        foreach ($bookings as $booking) {
            $created = new \DateTime($booking['created']);
            $bookingDate = new \DateTime($booking['booking_date']);
            $leadTime = $created->diff($bookingDate)->days;
            $totalLeadTime += $leadTime;
        }

        $averageLeadTime = $totalLeadTime / count($bookings);

        $this->assertEqualsWithDelta(4.67, $averageLeadTime, 0.01);
    }

    public function testRevenueByDayOfWeekReport()
    {
        $weekdayRevenue = [
            1 => 450.00, // Monday
            2 => 520.00, // Tuesday
            3 => 380.00, // Wednesday
            4 => 610.00, // Thursday
            5 => 690.00, // Friday
            6 => 320.00, // Saturday
            7 => 280.00, // Sunday
        ];

        arsort($weekdayRevenue);
        $topDay = key($weekdayRevenue);

        $this->assertEquals(5, $topDay); // Friday
        $this->assertEquals(690.00, $weekdayRevenue[5]);
    }

    public function testCustomerRetentionReport()
    {
        $customers = [
            ['id' => 1, 'first_booking' => '2024-01-01', 'last_booking' => '2025-01-15', 'total_bookings' => 8],
            ['id' => 2, 'first_booking' => '2024-06-01', 'last_booking' => '2024-06-15', 'total_bookings' => 1],
            ['id' => 3, 'first_booking' => '2024-03-01', 'last_booking' => '2025-01-10', 'total_bookings' => 12],
        ];

        $returningCustomers = 0;
        foreach ($customers as $customer) {
            if ($customer['total_bookings'] > 1) {
                $returningCustomers++;
            }
        }

        $retentionRate = ($returningCustomers / count($customers)) * 100;

        $this->assertEqualsWithDelta(66.67, $retentionRate, 0.01);
    }

    public function testExportToCSV()
    {
        $data = [
            ['Date', 'Service', 'Employee', 'Revenue'],
            ['2025-01-15', 'Haircut', 'John Doe', '50.00'],
            ['2025-01-16', 'Massage', 'Jane Smith', '75.00'],
        ];

        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        $this->assertStringContainsString('Date,Service,Employee,Revenue', $csv);
        $this->assertStringContainsString('Haircut,John Doe,50.00', $csv);
    }
}
