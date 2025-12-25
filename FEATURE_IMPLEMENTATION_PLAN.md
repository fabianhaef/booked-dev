# Feature Implementation Plan - Missing Features

**Plugin**: fabian/craft-booked
**Date**: 2025-12-25
**Status**: Implementation Ready

This document provides detailed implementation plans for the four features identified as gaps in the [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) report.

---

## Table of Contents

1. [Sequential Booking](#1-sequential-booking)
2. [Group Booking](#2-group-booking)
3. [Employee Self-Management Portal](#3-employee-self-management-portal)
4. [Dedicated Customer CRM Element](#4-dedicated-customer-crm-element)
5. [Implementation Priority](#implementation-priority)

---

## 1. Sequential Booking

### Overview

**Current State**: Users can book multiple services, but there's no dedicated UI flow for booking them back-to-back.

**Goal**: Allow users to book a sequence of services in one transaction, with automatic time slot calculation ensuring services are scheduled consecutively.

**Use Case**:
- Hair salon: "Cut + Color + Style" booked consecutively
- Medical: "Consultation + Treatment + Follow-up"
- Spa: "Massage + Facial + Manicure"

### Architecture

#### 1.1 Database Schema

**New Table**: `{{%booked_booking_sequences}}`

```sql
CREATE TABLE {{%booked_booking_sequences}} (
    id INT PRIMARY KEY AUTO_INCREMENT,
    userId INT NULL,                    -- FK to users table (if logged in)
    customerEmail VARCHAR(255),         -- For guest bookings
    customerName VARCHAR(255),
    status VARCHAR(20) DEFAULT 'pending',
    totalPrice DECIMAL(10,2),
    dateCreated DATETIME NOT NULL,
    dateUpdated DATETIME NOT NULL,
    uid CHAR(36) NOT NULL DEFAULT ''
);
```

**New Table**: `{{%booked_sequence_items}}`

```sql
CREATE TABLE {{%booked_sequence_items}} (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sequenceId INT NOT NULL,            -- FK to booked_booking_sequences
    reservationId INT NOT NULL,         -- FK to booked_reservations (elements table)
    sequenceOrder INT NOT NULL,         -- Order in sequence (0, 1, 2, etc.)
    dateCreated DATETIME NOT NULL,
    dateUpdated DATETIME NOT NULL,
    uid CHAR(36) NOT NULL DEFAULT ''
);
```

**Update Reservation Table**: Add `sequenceId` column

```sql
ALTER TABLE {{%booked_reservations}}
ADD COLUMN sequenceId INT NULL,
ADD INDEX idx_sequenceId (sequenceId);
```

#### 1.2 New Elements

**BookingSequence Element**

**File**: `src/elements/BookingSequence.php`

```php
<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\BookingSequenceQuery;
use fabian\booked\records\BookingSequenceRecord;

/**
 * BookingSequence Element
 *
 * Represents a collection of reservations booked back-to-back
 *
 * @property int|null $userId
 * @property string $customerEmail
 * @property string $customerName
 * @property string $status (pending, confirmed, cancelled)
 * @property float $totalPrice
 * @property Reservation[] $items
 */
class BookingSequence extends Element
{
    public ?int $userId = null;
    public string $customerEmail = '';
    public string $customerName = '';
    public string $status = 'pending';
    public float $totalPrice = 0.0;

    private ?array $_items = null;

    public static function displayName(): string
    {
        return Craft::t('booked', 'Booking Sequence');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Booking Sequences');
    }

    /**
     * Get all reservations in this sequence
     */
    public function getItems(): array
    {
        if ($this->_items === null) {
            $this->_items = Reservation::find()
                ->sequenceId($this->id)
                ->orderBy(['sequenceOrder' => SORT_ASC])
                ->all();
        }
        return $this->_items;
    }

    /**
     * Calculate total duration of sequence
     */
    public function getTotalDuration(): int
    {
        $duration = 0;
        foreach ($this->getItems() as $item) {
            $service = $item->getService();
            if ($service) {
                $duration += $service->duration + $service->bufferAfter;
            }
        }
        return $duration;
    }

    /**
     * Get first reservation in sequence
     */
    public function getFirstReservation(): ?Reservation
    {
        $items = $this->getItems();
        return $items[0] ?? null;
    }

    /**
     * Cancel entire sequence
     */
    public function cancel(): bool
    {
        foreach ($this->getItems() as $item) {
            $item->status = 'cancelled';
            Craft::$app->elements->saveElement($item);
        }

        $this->status = 'cancelled';
        return Craft::$app->elements->saveElement($this);
    }

    // ... rest of element methods (afterSave, beforeDelete, etc.)
}
```

#### 1.3 Service Layer

**File**: `src/services/SequentialBookingService.php`

```php
<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use DateTime;
use fabian\booked\elements\BookingSequence;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\exceptions\BookingException;

/**
 * Sequential Booking Service
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
     */
    public function getAvailableSequenceSlots(
        array $serviceIds,
        string $date,
        ?int $employeeId = null,
        ?int $locationId = null
    ): array {
        // Load all services
        $services = Service::find()
            ->id($serviceIds)
            ->all();

        if (count($services) !== count($serviceIds)) {
            throw new BookingException('One or more services not found');
        }

        // Calculate total duration needed
        $totalDuration = 0;
        foreach ($services as $service) {
            $totalDuration += $service->duration + ($service->bufferAfter ?? 0);
        }

        // Get availability service
        $availabilityService = Booked::getInstance()->availability;

        // Get all slots for the first service
        $firstService = $services[0];
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

            // Check if each subsequent service can be booked
            foreach ($services as $index => $service) {
                if ($index === 0) continue; // Skip first, already checked

                $slotStartTime = $currentTime->format('H:i');

                // Check if this time is available for this service
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

                // Advance time to next service
                $currentTime->modify('+' . ($service->duration + ($service->bufferAfter ?? 0)) . ' minutes');
            }

            if ($slotValid) {
                $validSequenceSlots[] = [
                    'time' => $startSlot['time'],
                    'duration' => $totalDuration,
                    'endTime' => $currentTime->format('H:i'),
                    'services' => array_map(fn($s) => $s->title, $services)
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
     */
    public function createSequentialBooking(array $data): BookingSequence
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Create the sequence element
            $sequence = new BookingSequence([
                'customerEmail' => $data['customerEmail'],
                'customerName' => $data['customerName'],
                'userId' => $data['userId'] ?? null,
                'status' => 'pending',
            ]);

            if (!Craft::$app->elements->saveElement($sequence)) {
                throw new BookingException('Failed to create booking sequence');
            }

            // Load services
            $services = Service::find()
                ->id($data['serviceIds'])
                ->all();

            // Create reservations for each service
            $currentTime = new DateTime($data['date'] . ' ' . $data['startTime']);
            $totalPrice = 0;

            foreach ($services as $index => $service) {
                $startTime = $currentTime->format('H:i');
                $currentTime->modify('+' . $service->duration . ' minutes');
                $endTime = $currentTime->format('H:i');

                // Add buffer after this service (except last)
                if ($index < count($services) - 1) {
                    $currentTime->modify('+' . ($service->bufferAfter ?? 0) . ' minutes');
                }

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
                    'status' => 'pending',
                ]);

                if (!Craft::$app->elements->saveElement($reservation)) {
                    throw new BookingException('Failed to create reservation for ' . $service->title);
                }

                $totalPrice += $service->price ?? 0;
            }

            // Update sequence with total price
            $sequence->totalPrice = $totalPrice;
            Craft::$app->elements->saveElement($sequence);

            $transaction->commit();

            return $sequence;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Get suggested service sequences (predefined packages)
     */
    public function getSuggestedSequences(): array
    {
        // This could be configured in plugin settings
        // or stored as a new "ServicePackage" element type
        return [
            [
                'name' => 'Full Spa Package',
                'serviceIds' => [1, 2, 3], // Massage, Facial, Manicure
                'discount' => 10, // 10% discount
            ],
            // ... more predefined sequences
        ];
    }
}
```

#### 1.4 Frontend UI

**New Template**: `src/templates/frontend/sequential-wizard.twig`

```twig
{# Extended wizard with sequential booking support #}
{% do view.registerAssetBundle("fabian\\booked\\assetbundles\\BookedAsset") %}

<div class="booked-sequential-wizard"
     x-data="sequentialBookingWizard()"
     :class="{ 'booked-loading': loading }">

    <!-- Step Indicator -->
    <div class="booked-step-indicator">
        <div class="booked-step" :class="{ 'active': step >= 1 }">1. Services</div>
        <div class="booked-step" :class="{ 'active': step >= 2 }">2. Order</div>
        <div class="booked-step" :class="{ 'active': step >= 3 }">3. Employee</div>
        <div class="booked-step" :class="{ 'active': step >= 4 }">4. Date & Time</div>
        <div class="booked-step" :class="{ 'active': step >= 5 }">5. Info</div>
        <div class="booked-step" :class="{ 'active': step >= 6 }">6. Confirm</div>
    </div>

    <!-- Step 1: Multiple Service Selection -->
    <div x-show="step === 1">
        <h3>Select Services (in any order)</h3>

        {# Suggested packages #}
        <div class="suggested-packages mb-6">
            <h4 class="text-sm font-semibold mb-2">Popular Packages</h4>
            <template x-for="package in packages" :key="package.name">
                <div class="package-card" @click="selectPackage(package)">
                    <h5 x-text="package.name"></h5>
                    <p class="text-sm" x-text="package.services.join(' → ')"></p>
                    <span class="badge" x-show="package.discount"
                          x-text="package.discount + '% OFF'"></span>
                </div>
            </template>
        </div>

        {# Individual service selection #}
        <h4 class="text-sm font-semibold mb-2">Or build your own</h4>
        <div class="booked-grid">
            <template x-for="service in services" :key="service.id">
                <div class="booked-card"
                     :class="{ 'selected': selectedServiceIds.includes(service.id) }"
                     @click="toggleService(service)">
                    <div class="flex justify-between items-start">
                        <h4 x-text="service.title"></h4>
                        <span class="badge" x-show="selectedServiceIds.includes(service.id)">
                            <span x-text="getServiceOrder(service.id)"></span>
                        </span>
                    </div>
                    <p x-text="service.description"></p>
                    <div class="light" x-text="service.duration + ' min | CHF ' + service.price"></div>
                </div>
            </template>
        </div>

        <button class="btn btn-primary mt-4"
                @click="nextStep()"
                :disabled="selectedServiceIds.length === 0">
            Continue with <span x-text="selectedServiceIds.length"></span> service(s)
        </button>
    </div>

    <!-- Step 2: Arrange Service Order -->
    <div x-show="step === 2">
        <h3>Arrange Service Order</h3>
        <p class="light mb-4">Drag to reorder your services</p>

        <div class="sequence-builder">
            <template x-for="(serviceId, index) in selectedServiceIds" :key="serviceId">
                <div class="sequence-item"
                     draggable="true"
                     @dragstart="dragStart(index)"
                     @dragover.prevent
                     @drop="drop(index)">
                    <span class="sequence-number" x-text="index + 1"></span>
                    <div class="sequence-details">
                        <h4 x-text="getService(serviceId).title"></h4>
                        <p class="light" x-text="getService(serviceId).duration + ' min'"></p>
                    </div>
                    <button class="btn-icon" @click="removeService(index)">
                        <svg><!-- X icon --></svg>
                    </button>
                </div>
            </template>
        </div>

        <div class="summary-box mt-4">
            <p><strong>Total Duration:</strong> <span x-text="totalDuration"></span> min</p>
            <p><strong>Total Price:</strong> CHF <span x-text="totalPrice"></span></p>
        </div>

        <div class="flex gap-4 mt-6">
            <button class="btn" @click="prevStep()">Back</button>
            <button class="btn btn-primary" @click="nextStep()">Continue</button>
        </div>
    </div>

    <!-- Step 4: Sequential Time Slots -->
    <div x-show="step === 4">
        <h3>Select Start Time</h3>
        <p class="light mb-4">
            Your sequence requires <span x-text="totalDuration"></span> minutes.
            Only times with full availability are shown.
        </p>

        <div class="flex gap-8">
            <div class="w-1/2">
                <input type="date"
                       x-model="date"
                       @change="fetchSequentialSlots()"
                       class="text-input">
            </div>
            <div class="w-1/2">
                <div class="booked-slots">
                    <template x-for="slot in availableSlots" :key="slot.time">
                        <div class="booked-slot sequential-slot"
                             @click="selectSlot(slot)">
                            <div class="slot-time" x-text="slot.time"></div>
                            <div class="slot-end light text-xs"
                                 x-text="'Ends at ' + slot.endTime"></div>
                        </div>
                    </template>
                    <template x-if="availableSlots.length === 0 && date && !loading">
                        <p class="light">No consecutive availability for this sequence.</p>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex gap-4 mt-6">
            <button class="btn" @click="prevStep()">Back</button>
        </div>
    </div>

    <!-- Steps 5 & 6: Same as regular wizard -->
    {# ... customer info and confirmation steps #}
</div>
```

**New JavaScript**: `src/web/js/sequential-booking-wizard.js`

```javascript
function sequentialBookingWizard() {
    return {
        step: 1,
        loading: false,

        // Service selection
        services: [],
        selectedServiceIds: [],
        packages: [],

        // Booking details
        date: '',
        selectedSlot: null,
        employeeId: null,
        locationId: null,
        availableSlots: [],

        // Customer info
        customerName: '',
        customerEmail: '',
        customerPhone: '',

        // Drag and drop
        draggedIndex: null,

        async init() {
            await this.fetchServices();
            await this.fetchPackages();
        },

        async fetchServices() {
            this.loading = true;
            const response = await fetch('/actions/booked/api/services');
            this.services = await response.json();
            this.loading = false;
        },

        async fetchPackages() {
            const response = await fetch('/actions/booked/api/sequential-packages');
            this.packages = await response.json();
        },

        toggleService(service) {
            const index = this.selectedServiceIds.indexOf(service.id);
            if (index === -1) {
                this.selectedServiceIds.push(service.id);
            } else {
                this.selectedServiceIds.splice(index, 1);
            }
        },

        selectPackage(package) {
            this.selectedServiceIds = [...package.serviceIds];
            this.nextStep();
        },

        getService(serviceId) {
            return this.services.find(s => s.id === serviceId);
        },

        getServiceOrder(serviceId) {
            return this.selectedServiceIds.indexOf(serviceId) + 1;
        },

        removeService(index) {
            this.selectedServiceIds.splice(index, 1);
        },

        // Drag and drop
        dragStart(index) {
            this.draggedIndex = index;
        },

        drop(targetIndex) {
            if (this.draggedIndex === null) return;

            const draggedId = this.selectedServiceIds[this.draggedIndex];
            this.selectedServiceIds.splice(this.draggedIndex, 1);
            this.selectedServiceIds.splice(targetIndex, 0, draggedId);
            this.draggedIndex = null;
        },

        async fetchSequentialSlots() {
            if (!this.date || this.selectedServiceIds.length === 0) return;

            this.loading = true;
            const response = await fetch('/actions/booked/api/sequential-slots', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    serviceIds: this.selectedServiceIds,
                    date: this.date,
                    employeeId: this.employeeId,
                    locationId: this.locationId
                })
            });

            this.availableSlots = await response.json();
            this.loading = false;
        },

        get totalDuration() {
            return this.selectedServiceIds.reduce((sum, id) => {
                const service = this.getService(id);
                return sum + (service?.duration || 0);
            }, 0);
        },

        get totalPrice() {
            return this.selectedServiceIds.reduce((sum, id) => {
                const service = this.getService(id);
                return sum + (service?.price || 0);
            }, 0).toFixed(2);
        },

        async submitSequentialBooking() {
            this.loading = true;

            const response = await fetch('/actions/booked/booking/create-sequential', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    serviceIds: this.selectedServiceIds,
                    date: this.date,
                    startTime: this.selectedSlot.time,
                    employeeId: this.employeeId,
                    locationId: this.locationId,
                    customerName: this.customerName,
                    customerEmail: this.customerEmail,
                    customerPhone: this.customerPhone
                })
            });

            const result = await response.json();

            if (result.success) {
                this.step = 6; // Confirmation
            } else {
                alert(result.error);
            }

            this.loading = false;
        }
    };
}
```

#### 1.5 Controller Actions

**File**: `src/controllers/BookingController.php` (add methods)

```php
/**
 * Create sequential booking
 */
public function actionCreateSequential(): Response
{
    $this->requirePostRequest();

    $request = Craft::$app->getRequest();
    $data = $request->getBodyParams();

    try {
        $sequence = Booked::getInstance()->sequentialBooking->createSequentialBooking($data);

        return $this->asJson([
            'success' => true,
            'sequenceId' => $sequence->id,
            'message' => 'Sequential booking created successfully'
        ]);

    } catch (\Throwable $e) {
        return $this->asJson([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
```

**File**: `src/controllers/ApiController.php` (add methods)

```php
/**
 * Get available sequential slots
 */
public function actionSequentialSlots(): Response
{
    $this->requirePostRequest();

    $request = Craft::$app->getRequest();
    $serviceIds = $request->getBodyParam('serviceIds', []);
    $date = $request->getBodyParam('date');
    $employeeId = $request->getBodyParam('employeeId');
    $locationId = $request->getBodyParam('locationId');

    $slots = Booked::getInstance()->sequentialBooking->getAvailableSequenceSlots(
        $serviceIds,
        $date,
        $employeeId,
        $locationId
    );

    return $this->asJson($slots);
}

/**
 * Get suggested sequential packages
 */
public function actionSequentialPackages(): Response
{
    $packages = Booked::getInstance()->sequentialBooking->getSuggestedSequences();
    return $this->asJson($packages);
}
```

#### 1.6 Migration

**File**: `src/migrations/m241225_000001_add_sequential_booking.php`

```php
<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

class m241225_000001_add_sequential_booking extends Migration
{
    public function safeUp(): bool
    {
        // Create booking sequences table
        $this->createTable('{{%booked_booking_sequences}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->null(),
            'customerEmail' => $this->string()->notNull(),
            'customerName' => $this->string()->notNull(),
            'status' => $this->string(20)->defaultValue('pending'),
            'totalPrice' => $this->decimal(10, 2)->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign keys
        $this->addForeignKey(null, '{{%booked_booking_sequences}}', 'userId',
                            '{{%users}}', 'id', 'SET NULL');

        // Add sequenceId to reservations
        $this->addColumn('{{%booked_reservations}}', 'sequenceId',
                        $this->integer()->null()->after('id'));
        $this->addColumn('{{%booked_reservations}}', 'sequenceOrder',
                        $this->integer()->defaultValue(0)->after('sequenceId'));

        $this->addForeignKey(null, '{{%booked_reservations}}', 'sequenceId',
                            '{{%booked_booking_sequences}}', 'id', 'CASCADE');

        $this->createIndex(null, '{{%booked_reservations}}', 'sequenceId');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey(
            $this->db->getForeignKeyName('{{%booked_reservations}}', 'sequenceId'),
            '{{%booked_reservations}}'
        );

        $this->dropColumn('{{%booked_reservations}}', 'sequenceId');
        $this->dropColumn('{{%booked_reservations}}', 'sequenceOrder');

        $this->dropTable('{{%booked_booking_sequences}}');

        return true;
    }
}
```

### Testing Strategy

1. **Unit Tests**: `tests/unit/SequentialBookingServiceTest.php`
   - Test `getAvailableSequenceSlots()` with various service combinations
   - Test edge cases (no availability, partial availability)
   - Test duration calculations

2. **Integration Tests**: `tests/integration/SequentialBookingFlowTest.php`
   - Test complete sequential booking creation
   - Test cancellation of sequences
   - Test database integrity

---

## 2. Group Booking

### Overview

**Current State**: Capacity field exists on services, but no UI for booking multiple people in one transaction.

**Goal**: Allow users to book appointments for multiple attendees in a single transaction, with capacity management and group pricing.

**Use Case**:
- Fitness class: Book for family/friends
- Corporate training: Book team of 5
- Group tour: Book 10 people

### Architecture

#### 2.1 Database Schema Updates

**Update Service Table**: Add group booking fields

```sql
ALTER TABLE {{%booked_services}}
ADD COLUMN allowGroupBooking BOOLEAN DEFAULT false,
ADD COLUMN minGroupSize INT DEFAULT 1,
ADD COLUMN maxGroupSize INT DEFAULT 1,
ADD COLUMN groupPricing VARCHAR(20) DEFAULT 'per_person', -- 'per_person' or 'per_group'
ADD COLUMN groupPriceDiscount DECIMAL(5,2) DEFAULT 0; -- Percentage discount for groups
```

**Update Reservation Table**: Add attendee information

```sql
ALTER TABLE {{%booked_reservations}}
ADD COLUMN attendeeCount INT DEFAULT 1,
ADD COLUMN attendees JSON NULL, -- Store array of attendee details
ADD COLUMN isGroupBooking BOOLEAN DEFAULT false;
```

#### 2.2 Update Service Element

**File**: `src/elements/Service.php` (add properties)

```php
public bool $allowGroupBooking = false;
public int $minGroupSize = 1;
public int $maxGroupSize = 1;
public string $groupPricing = 'per_person'; // 'per_person' or 'per_group'
public float $groupPriceDiscount = 0;

/**
 * Calculate price for group booking
 */
public function getGroupPrice(int $attendeeCount): float
{
    if ($this->groupPricing === 'per_group') {
        // Flat rate for entire group
        return $this->price * (1 - $this->groupPriceDiscount / 100);
    }

    // Per person with group discount
    $basePrice = $this->price * $attendeeCount;
    return $basePrice * (1 - $this->groupPriceDiscount / 100);
}

/**
 * Check if group size is valid
 */
public function isValidGroupSize(int $size): bool
{
    return $size >= $this->minGroupSize && $size <= $this->maxGroupSize;
}
```

#### 2.3 Update Reservation Element

**File**: `src/elements/Reservation.php` (add properties and methods)

```php
public int $attendeeCount = 1;
public ?array $attendees = null;
public bool $isGroupBooking = false;

/**
 * Get attendee details
 */
public function getAttendees(): array
{
    if ($this->attendees === null) {
        return [];
    }

    return is_string($this->attendees)
        ? json_decode($this->attendees, true)
        : $this->attendees;
}

/**
 * Set attendee details
 */
public function setAttendees(array $attendees): void
{
    $this->attendees = $attendees;
    $this->attendeeCount = count($attendees);
    $this->isGroupBooking = count($attendees) > 1;
}

/**
 * Get primary attendee (person who made the booking)
 */
public function getPrimaryAttendee(): ?array
{
    $attendees = $this->getAttendees();
    return $attendees[0] ?? null;
}
```

#### 2.4 Service Layer

**File**: `src/services/GroupBookingService.php`

```php
<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\exceptions\BookingException;

/**
 * Group Booking Service
 */
class GroupBookingService extends Component
{
    /**
     * Check if service allows group booking
     */
    public function serviceSupportsGroupBooking(int $serviceId): bool
    {
        $service = Service::find()->id($serviceId)->one();
        return $service && $service->allowGroupBooking;
    }

    /**
     * Check availability for group booking
     *
     * @param string $date
     * @param string $time
     * @param int $serviceId
     * @param int $attendeeCount
     * @param int|null $employeeId
     * @param int|null $locationId
     * @return bool
     */
    public function checkGroupAvailability(
        string $date,
        string $time,
        int $serviceId,
        int $attendeeCount,
        ?int $employeeId = null,
        ?int $locationId = null
    ): bool {
        $service = Service::find()->id($serviceId)->one();

        if (!$service) {
            return false;
        }

        // Check if group size is valid
        if (!$service->isValidGroupSize($attendeeCount)) {
            return false;
        }

        // Calculate end time
        $startDateTime = new \DateTime($date . ' ' . $time);
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . $service->duration . ' minutes');
        $endTime = $endDateTime->format('H:i');

        // Check existing bookings
        $existingReservations = Reservation::find()
            ->bookingDate($date)
            ->andWhere(['or',
                ['and',
                    ['>=', '[[booked_reservations.startTime]]', $time],
                    ['<', '[[booked_reservations.startTime]]', $endTime]
                ],
                ['and',
                    ['>', '[[booked_reservations.endTime]]', $time],
                    ['<=', '[[booked_reservations.endTime]]', $endTime]
                ]
            ])
            ->serviceId($serviceId)
            ->status(['confirmed', 'pending'])
            ->all();

        // Calculate total attendees including this booking
        $totalAttendees = $attendeeCount;
        foreach ($existingReservations as $reservation) {
            $totalAttendees += $reservation->attendeeCount;
        }

        // Check against service capacity
        $capacity = $service->capacity ?? PHP_INT_MAX;
        return $totalAttendees <= $capacity;
    }

    /**
     * Create group booking
     *
     * @param array $data Booking data including attendees array
     * @return Reservation
     */
    public function createGroupBooking(array $data): Reservation
    {
        $service = Service::find()->id($data['serviceId'])->one();

        if (!$service) {
            throw new BookingException('Service not found');
        }

        if (!$service->allowGroupBooking) {
            throw new BookingException('This service does not allow group bookings');
        }

        $attendees = $data['attendees'] ?? [];
        $attendeeCount = count($attendees);

        if (!$service->isValidGroupSize($attendeeCount)) {
            throw new BookingException(
                "Group size must be between {$service->minGroupSize} and {$service->maxGroupSize}"
            );
        }

        // Check availability
        if (!$this->checkGroupAvailability(
            $data['date'],
            $data['startTime'],
            $service->id,
            $attendeeCount,
            $data['employeeId'] ?? null,
            $data['locationId'] ?? null
        )) {
            throw new BookingException('Not enough capacity for this group booking');
        }

        // Calculate group price
        $totalPrice = $service->getGroupPrice($attendeeCount);

        // Create reservation
        $reservation = new Reservation([
            'serviceId' => $service->id,
            'employeeId' => $data['employeeId'] ?? null,
            'locationId' => $data['locationId'] ?? null,
            'bookingDate' => $data['date'],
            'startTime' => $data['startTime'],
            'endTime' => $data['endTime'],
            'userName' => $data['userName'],
            'userEmail' => $data['userEmail'],
            'userPhone' => $data['userPhone'] ?? null,
            'isGroupBooking' => true,
            'attendeeCount' => $attendeeCount,
            'attendees' => $attendees,
            'status' => 'pending',
        ]);

        if (!Craft::$app->elements->saveElement($reservation)) {
            throw new BookingException('Failed to create group booking');
        }

        return $reservation;
    }

    /**
     * Add attendee to existing group booking
     */
    public function addAttendee(int $reservationId, array $attendeeData): bool
    {
        $reservation = Reservation::find()->id($reservationId)->one();

        if (!$reservation) {
            throw new BookingException('Reservation not found');
        }

        $service = $reservation->getService();
        $currentAttendees = $reservation->getAttendees();

        if (count($currentAttendees) >= $service->maxGroupSize) {
            throw new BookingException('Maximum group size reached');
        }

        // Check capacity
        if (!$this->checkGroupAvailability(
            $reservation->bookingDate,
            $reservation->startTime,
            $service->id,
            count($currentAttendees) + 1,
            $reservation->employeeId,
            $reservation->locationId
        )) {
            throw new BookingException('No capacity for additional attendee');
        }

        $currentAttendees[] = $attendeeData;
        $reservation->setAttendees($currentAttendees);

        return Craft::$app->elements->saveElement($reservation);
    }

    /**
     * Remove attendee from group booking
     */
    public function removeAttendee(int $reservationId, int $attendeeIndex): bool
    {
        $reservation = Reservation::find()->id($reservationId)->one();

        if (!$reservation) {
            throw new BookingException('Reservation not found');
        }

        $attendees = $reservation->getAttendees();

        if (!isset($attendees[$attendeeIndex])) {
            throw new BookingException('Attendee not found');
        }

        array_splice($attendees, $attendeeIndex, 1);

        // If no attendees left, cancel the booking
        if (empty($attendees)) {
            $reservation->status = 'cancelled';
        } else {
            $reservation->setAttendees($attendees);
        }

        return Craft::$app->elements->saveElement($reservation);
    }
}
```

#### 2.5 Frontend UI

**New Template**: `src/templates/frontend/group-wizard.twig`

```twig
{% do view.registerAssetBundle("fabian\\booked\\assetbundles\\BookedAsset") %}

<div class="booked-group-wizard"
     x-data="groupBookingWizard()"
     :class="{ 'booked-loading': loading }">

    <!-- Existing wizard steps 1-4 for service, location, employee, date selection -->

    <!-- NEW Step: Group Size Selection (after time selection) -->
    <div x-show="step === 5">
        <h3>How many people?</h3>

        <div class="group-size-selector">
            <p class="light mb-4">
                This service allows
                <span x-text="selectedService.minGroupSize"></span> -
                <span x-text="selectedService.maxGroupSize"></span> people
            </p>

            <div class="flex items-center gap-4">
                <button class="btn-icon" @click="decrementAttendees()"
                        :disabled="attendeeCount <= selectedService.minGroupSize">
                    <svg><!-- Minus icon --></svg>
                </button>

                <div class="attendee-count">
                    <span class="text-4xl font-bold" x-text="attendeeCount"></span>
                    <span class="light">people</span>
                </div>

                <button class="btn-icon" @click="incrementAttendees()"
                        :disabled="attendeeCount >= selectedService.maxGroupSize ||
                                   attendeeCount >= remainingCapacity">
                    <svg><!-- Plus icon --></svg>
                </button>
            </div>

            <div class="capacity-warning mt-4"
                 x-show="remainingCapacity < selectedService.maxGroupSize">
                <p class="text-orange-600">
                    ⚠️ Only <span x-text="remainingCapacity"></span> spots available at this time
                </p>
            </div>

            <div class="price-breakdown mt-6 p-4 bg-gray-50 rounded">
                <p class="flex justify-between">
                    <span>Base Price (<span x-text="attendeeCount"></span> ×
                          CHF <span x-text="selectedService.price"></span>):</span>
                    <span>CHF <span x-text="basePrice"></span></span>
                </p>
                <p class="flex justify-between text-green-600"
                   x-show="groupDiscount > 0">
                    <span>Group Discount (<span x-text="selectedService.groupPriceDiscount"></span>%):</span>
                    <span>- CHF <span x-text="groupDiscount"></span></span>
                </p>
                <p class="flex justify-between font-bold text-lg mt-2 pt-2 border-t">
                    <span>Total:</span>
                    <span>CHF <span x-text="totalPrice"></span></span>
                </p>
            </div>
        </div>

        <div class="flex gap-4 mt-6">
            <button class="btn" @click="prevStep()">Back</button>
            <button class="btn btn-primary" @click="nextStep()">Continue</button>
        </div>
    </div>

    <!-- NEW Step: Attendee Details -->
    <div x-show="step === 6">
        <h3>Attendee Information</h3>
        <p class="light mb-4">Provide details for each person attending</p>

        <template x-for="(attendee, index) in attendees" :key="index">
            <div class="attendee-form mb-6 p-4 border rounded">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-semibold">
                        <span x-show="index === 0">Primary Contact</span>
                        <span x-show="index > 0">Attendee <span x-text="index + 1"></span></span>
                    </h4>
                    <button class="btn-sm"
                            x-show="index > 0"
                            @click="removeAttendee(index)">
                        Remove
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text"
                               x-model="attendees[index].firstName"
                               class="text-input"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text"
                               x-model="attendees[index].lastName"
                               class="text-input"
                               required>
                    </div>
                    <div class="form-group" x-show="index === 0">
                        <label>Email *</label>
                        <input type="email"
                               x-model="attendees[index].email"
                               class="text-input"
                               required>
                    </div>
                    <div class="form-group" x-show="index === 0">
                        <label>Phone</label>
                        <input type="tel"
                               x-model="attendees[index].phone"
                               class="text-input">
                    </div>
                </div>
            </div>
        </template>

        <div class="flex gap-4 mt-6">
            <button class="btn" @click="prevStep()">Back</button>
            <button class="btn btn-primary" @click="submitGroupBooking()">
                Book for <span x-text="attendeeCount"></span> people
            </button>
        </div>
    </div>
</div>
```

**JavaScript**: `src/web/js/group-booking-wizard.js`

```javascript
function groupBookingWizard() {
    return {
        // ... existing wizard data ...

        // Group booking specific
        attendeeCount: 1,
        attendees: [{ firstName: '', lastName: '', email: '', phone: '' }],
        remainingCapacity: 0,

        incrementAttendees() {
            if (this.attendeeCount < this.selectedService.maxGroupSize &&
                this.attendeeCount < this.remainingCapacity) {
                this.attendeeCount++;
                this.attendees.push({ firstName: '', lastName: '', email: '', phone: '' });
            }
        },

        decrementAttendees() {
            if (this.attendeeCount > this.selectedService.minGroupSize) {
                this.attendeeCount--;
                this.attendees.pop();
            }
        },

        removeAttendee(index) {
            if (index > 0) { // Can't remove primary contact
                this.attendees.splice(index, 1);
                this.attendeeCount--;
            }
        },

        async selectSlot(slot) {
            this.selectedSlot = slot;

            // Fetch remaining capacity for this slot
            const response = await fetch('/actions/booked/api/slot-capacity', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: this.date,
                    time: slot.time,
                    serviceId: this.selectedService.id
                })
            });

            const data = await response.json();
            this.remainingCapacity = data.remainingCapacity;

            // Adjust attendee count if exceeds capacity
            if (this.attendeeCount > this.remainingCapacity) {
                this.attendeeCount = this.remainingCapacity;
                this.attendees = this.attendees.slice(0, this.remainingCapacity);
            }

            this.nextStep();
        },

        get basePrice() {
            return (this.selectedService.price * this.attendeeCount).toFixed(2);
        },

        get groupDiscount() {
            const discount = this.basePrice * (this.selectedService.groupPriceDiscount / 100);
            return discount.toFixed(2);
        },

        get totalPrice() {
            return (this.basePrice - this.groupDiscount).toFixed(2);
        },

        async submitGroupBooking() {
            this.loading = true;

            const response = await fetch('/actions/booked/booking/create-group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    serviceId: this.selectedService.id,
                    employeeId: this.employeeId,
                    locationId: this.locationId,
                    date: this.date,
                    startTime: this.selectedSlot.time,
                    endTime: this.selectedSlot.endTime,
                    userName: this.attendees[0].firstName + ' ' + this.attendees[0].lastName,
                    userEmail: this.attendees[0].email,
                    userPhone: this.attendees[0].phone,
                    attendees: this.attendees
                })
            });

            const result = await response.json();

            if (result.success) {
                this.step = 7; // Confirmation
            } else {
                alert(result.error);
            }

            this.loading = false;
        }
    };
}
```

#### 2.6 Controller Actions

**File**: `src/controllers/BookingController.php` (add methods)

```php
/**
 * Create group booking
 */
public function actionCreateGroup(): Response
{
    $this->requirePostRequest();

    $request = Craft::$app->getRequest();
    $data = $request->getBodyParams();

    try {
        $reservation = Booked::getInstance()->groupBooking->createGroupBooking($data);

        return $this->asJson([
            'success' => true,
            'reservationId' => $reservation->id,
            'message' => 'Group booking created successfully'
        ]);

    } catch (\Throwable $e) {
        return $this->asJson([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
```

**File**: `src/controllers/ApiController.php` (add method)

```php
/**
 * Get remaining capacity for a time slot
 */
public function actionSlotCapacity(): Response
{
    $this->requirePostRequest();

    $request = Craft::$app->getRequest();
    $date = $request->getBodyParam('date');
    $time = $request->getBodyParam('time');
    $serviceId = $request->getBodyParam('serviceId');

    $service = Service::find()->id($serviceId)->one();
    $capacity = $service->capacity ?? PHP_INT_MAX;

    // Calculate currently booked attendees
    $existingReservations = Reservation::find()
        ->bookingDate($date)
        ->startTime($time)
        ->serviceId($serviceId)
        ->status(['confirmed', 'pending'])
        ->all();

    $bookedAttendees = 0;
    foreach ($existingReservations as $reservation) {
        $bookedAttendees += $reservation->attendeeCount;
    }

    $remainingCapacity = max(0, $capacity - $bookedAttendees);

    return $this->asJson([
        'capacity' => $capacity,
        'booked' => $bookedAttendees,
        'remainingCapacity' => $remainingCapacity
    ]);
}
```

#### 2.7 CP Interface

**File**: `src/templates/cp/services/_edit.twig` (add group booking settings)

```twig
{# Existing service fields... #}

<hr>

<h2>Group Booking Settings</h2>

{{ forms.lightswitchField({
    label: 'Allow Group Booking',
    name: 'allowGroupBooking',
    on: service.allowGroupBooking,
    instructions: 'Allow customers to book multiple people in one transaction'
}) }}

<div id="group-booking-settings" style="display: {{ service.allowGroupBooking ? 'block' : 'none' }}">
    {{ forms.textField({
        label: 'Minimum Group Size',
        name: 'minGroupSize',
        type: 'number',
        value: service.minGroupSize,
        min: 1,
        required: true
    }) }}

    {{ forms.textField({
        label: 'Maximum Group Size',
        name: 'maxGroupSize',
        type: 'number',
        value: service.maxGroupSize,
        min: 1,
        required: true
    }) }}

    {{ forms.selectField({
        label: 'Group Pricing',
        name: 'groupPricing',
        options: [
            { value: 'per_person', label: 'Per Person' },
            { value: 'per_group', label: 'Per Group (Flat Rate)' }
        ],
        value: service.groupPricing
    }) }}

    {{ forms.textField({
        label: 'Group Discount (%)',
        name: 'groupPriceDiscount',
        type: 'number',
        value: service.groupPriceDiscount,
        min: 0,
        max: 100,
        step: 0.01,
        instructions: 'Percentage discount for group bookings'
    }) }}
</div>

<script>
document.querySelector('input[name="allowGroupBooking"]').addEventListener('change', function(e) {
    document.getElementById('group-booking-settings').style.display = e.target.checked ? 'block' : 'none';
});
</script>
```

#### 2.8 Migration

**File**: `src/migrations/m241225_000002_add_group_booking.php`

```php
<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

class m241225_000002_add_group_booking extends Migration
{
    public function safeUp(): bool
    {
        // Add group booking fields to services
        $this->addColumn('{{%booked_services}}', 'allowGroupBooking',
                        $this->boolean()->defaultValue(false)->after('price'));
        $this->addColumn('{{%booked_services}}', 'minGroupSize',
                        $this->integer()->defaultValue(1)->after('allowGroupBooking'));
        $this->addColumn('{{%booked_services}}', 'maxGroupSize',
                        $this->integer()->defaultValue(1)->after('minGroupSize'));
        $this->addColumn('{{%booked_services}}', 'groupPricing',
                        $this->string(20)->defaultValue('per_person')->after('maxGroupSize'));
        $this->addColumn('{{%booked_services}}', 'groupPriceDiscount',
                        $this->decimal(5, 2)->defaultValue(0)->after('groupPricing'));

        // Add group booking fields to reservations
        $this->addColumn('{{%booked_reservations}}', 'attendeeCount',
                        $this->integer()->defaultValue(1)->after('quantity'));
        $this->addColumn('{{%booked_reservations}}', 'attendees',
                        $this->json()->null()->after('attendeeCount'));
        $this->addColumn('{{%booked_reservations}}', 'isGroupBooking',
                        $this->boolean()->defaultValue(false)->after('attendees'));

        return true;
    }

    public function safeDown(): bool
    {
        // Remove group booking fields from services
        $this->dropColumn('{{%booked_services}}', 'allowGroupBooking');
        $this->dropColumn('{{%booked_services}}', 'minGroupSize');
        $this->dropColumn('{{%booked_services}}', 'maxGroupSize');
        $this->dropColumn('{{%booked_services}}', 'groupPricing');
        $this->dropColumn('{{%booked_services}}', 'groupPriceDiscount');

        // Remove group booking fields from reservations
        $this->dropColumn('{{%booked_reservations}}', 'attendeeCount');
        $this->dropColumn('{{%booked_reservations}}', 'attendees');
        $this->dropColumn('{{%booked_reservations}}', 'isGroupBooking');

        return true;
    }
}
```

### Testing Strategy

1. **Unit Tests**: `tests/unit/GroupBookingServiceTest.php`
   - Test capacity calculations
   - Test group pricing calculations
   - Test validation logic

2. **Integration Tests**: `tests/integration/GroupBookingFlowTest.php`
   - Test complete group booking flow
   - Test attendee management
   - Test capacity limits

---

## 3. Employee Self-Management Portal

### Overview

**Current State**: Employees can be linked to Craft users and can access the Control Panel with appropriate permissions.

**Goal**: Provide a dedicated frontend portal where employees can manage their own schedules, view appointments, update availability, and block out dates without needing CP access.

**Use Case**:
- Employees set their own availability
- Employees block out vacation days
- Employees view their upcoming appointments
- Employees receive appointment notifications

---

*[Continue with detailed implementation for Employee Portal and Customer CRM in next section due to length...]*

**Character Limit Note**: The complete implementation plan is extensive. Should I continue with the remaining two features (Employee Self-Management Portal and Dedicated Customer CRM Element)?
