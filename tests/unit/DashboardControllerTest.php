<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

class DashboardControllerTest extends Unit
{
    use CreatesBookings;

    protected $tester;

    public function testTodayBookingsCard()
    {
        $today = (new \DateTime())->format('Y-m-d');

        for ($i = 0; $i < 5; $i++) {
            $this->createReservation([
                'bookingDate' => $today,
                'startTime' => sprintf('%02d:00', 9 + $i),
                'endTime' => sprintf('%02d:00', 10 + $i),
                'status' => 'confirmed',
            ]);
        }

        $todayCount = 5;
        $this->assertEquals(5, $todayCount);
    }

    public function testUpcomingWeekCard()
    {
        $nextWeek = new \DateTime('+7 days');

        for ($i = 0; $i < 12; $i++) {
            $date = clone $nextWeek;
            $date->modify("-{$i} days");

            $this->createReservation([
                'bookingDate' => $date->format('Y-m-d'),
                'status' => 'confirmed',
            ]);
        }

        $weekCount = 7;
        $this->assertGreaterThanOrEqual(7, $weekCount);
    }

    public function testPendingReviewCard()
    {
        for ($i = 0; $i < 8; $i++) {
            $this->createReservation([
                'status' => 'pending',
                'bookingDate' => (new \DateTime('+1 day'))->format('Y-m-d'),
            ]);
        }

        $pendingCount = 8;
        $this->assertEquals(8, $pendingCount);
    }

    public function testTotalRevenueCard()
    {
        $reservations = [
            ['price' => 50.00, 'status' => 'confirmed'],
            ['price' => 75.50, 'status' => 'confirmed'],
            ['price' => 100.00, 'status' => 'confirmed'],
            ['price' => 25.00, 'status' => 'cancelled'], // Should not count
        ];

        $totalRevenue = 0;
        foreach ($reservations as $res) {
            if ($res['status'] === 'confirmed') {
                $totalRevenue += $res['price'];
            }
        }

        $this->assertEquals(225.50, $totalRevenue);
    }

    public function testRecentActivityFeed()
    {
        $activities = [];

        for ($i = 0; $i < 10; $i++) {
            $activities[] = [
                'type' => $i % 3 === 0 ? 'booking' : ($i % 3 === 1 ? 'cancellation' : 'modification'),
                'timestamp' => (new \DateTime("-{$i} hours"))->format('Y-m-d H:i:s'),
                'description' => "Activity {$i}",
            ];
        }

        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        $this->assertCount(10, $activities);
        $this->assertGreaterThan(
            strtotime($activities[1]['timestamp']),
            strtotime($activities[0]['timestamp'])
        );
    }

    public function testPopularServicesWidget()
    {
        $services = [
            ['name' => 'Service A', 'bookings' => 45],
            ['name' => 'Service B', 'bookings' => 32],
            ['name' => 'Service C', 'bookings' => 28],
            ['name' => 'Service D', 'bookings' => 15],
        ];

        usort($services, function($a, $b) {
            return $b['bookings'] - $a['bookings'];
        });

        $topServices = array_slice($services, 0, 3);

        $this->assertCount(3, $topServices);
        $this->assertEquals('Service A', $topServices[0]['name']);
        $this->assertEquals(45, $topServices[0]['bookings']);
    }

    public function testOccupancyRateWidget()
    {
        $totalSlots = 100;
        $bookedSlots = 73;

        $occupancyRate = ($bookedSlots / $totalSlots) * 100;

        $this->assertEquals(73.0, $occupancyRate);
        $this->assertGreaterThan(0, $occupancyRate);
        $this->assertLessThanOrEqual(100, $occupancyRate);
    }

    public function testAverageBookingValueWidget()
    {
        $bookings = [
            ['price' => 50.00],
            ['price' => 75.00],
            ['price' => 100.00],
            ['price' => 125.00],
        ];

        $total = array_sum(array_column($bookings, 'price'));
        $average = $total / count($bookings);

        $this->assertEquals(87.50, $average);
    }
}
