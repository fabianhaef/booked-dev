<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\models\ServiceExtra;
use fabian\booked\records\ServiceExtraRecord;
use fabian\booked\records\ServiceExtraServiceRecord;
use fabian\booked\records\ReservationExtraRecord;

/**
 * Service Extra Service
 *
 * Manages service extras and add-ons that can be selected during booking.
 */
class ServiceExtraService extends Component
{
    /**
     * Get all service extras
     *
     * @param bool $enabledOnly Only return enabled extras
     * @return ServiceExtra[]
     */
    public function getAllExtras(bool $enabledOnly = false): array
    {
        $query = ServiceExtraRecord::find();

        if ($enabledOnly) {
            $query->where(['enabled' => true]);
        }

        $query->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC]);

        $records = $query->all();

        return array_map(fn($record) => ServiceExtra::fromRecord($record), $records);
    }

    /**
     * Get extras for a specific service
     *
     * @param int $serviceId
     * @param bool $enabledOnly Only return enabled extras
     * @return ServiceExtra[]
     */
    public function getExtrasForService(int $serviceId, bool $enabledOnly = true): array
    {
        $query = ServiceExtraRecord::find()
            ->innerJoin(
                '{{%booked_service_extras_services}} ses',
                '{{%booked_service_extras}}.[[id]] = ses.[[extraId]]'
            )
            ->where(['ses.serviceId' => $serviceId]);

        if ($enabledOnly) {
            $query->andWhere(['{{%booked_service_extras}}.enabled' => true]);
        }

        $query->orderBy(['ses.sortOrder' => SORT_ASC, '{{%booked_service_extras}}.name' => SORT_ASC]);

        $records = $query->all();

        return array_map(fn($record) => ServiceExtra::fromRecord($record), $records);
    }

    /**
     * Get a single service extra by ID
     */
    public function getExtraById(int $id): ?ServiceExtra
    {
        $record = ServiceExtraRecord::findOne($id);

        return $record ? ServiceExtra::fromRecord($record) : null;
    }

    /**
     * Save a service extra
     */
    public function saveExtra(ServiceExtra $extra): bool
    {
        return $extra->save();
    }

    /**
     * Delete a service extra
     */
    public function deleteExtra(int $id): bool
    {
        $record = ServiceExtraRecord::findOne($id);

        if (!$record) {
            return false;
        }

        return (bool)$record->delete();
    }

    /**
     * Assign an extra to a service
     *
     * @param int $extraId
     * @param int $serviceId
     * @param int $sortOrder
     * @return bool
     */
    public function assignExtraToService(int $extraId, int $serviceId, int $sortOrder = 0): bool
    {
        // Check if already assigned
        $existing = ServiceExtraServiceRecord::findOne([
            'extraId' => $extraId,
            'serviceId' => $serviceId,
        ]);

        if ($existing) {
            // Update sort order
            $existing->sortOrder = $sortOrder;
            return (bool)$existing->save();
        }

        // Create new assignment
        $record = new ServiceExtraServiceRecord();
        $record->extraId = $extraId;
        $record->serviceId = $serviceId;
        $record->sortOrder = $sortOrder;

        return (bool)$record->save();
    }

    /**
     * Remove an extra from a service
     */
    public function removeExtraFromService(int $extraId, int $serviceId): bool
    {
        $record = ServiceExtraServiceRecord::findOne([
            'extraId' => $extraId,
            'serviceId' => $serviceId,
        ]);

        if (!$record) {
            return false;
        }

        return (bool)$record->delete();
    }

    /**
     * Set extras for a service (replaces all existing assignments)
     *
     * @param int $serviceId
     * @param array $extraIds Array of extra IDs
     * @return bool
     */
    public function setExtrasForService(int $serviceId, array $extraIds): bool
    {
        // Delete existing assignments
        ServiceExtraServiceRecord::deleteAll(['serviceId' => $serviceId]);

        // Create new assignments
        foreach ($extraIds as $index => $extraId) {
            $record = new ServiceExtraServiceRecord();
            $record->extraId = $extraId;
            $record->serviceId = $serviceId;
            $record->sortOrder = $index;

            if (!$record->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set services for an extra (inverse of setExtrasForService)
     *
     * @param int $extraId
     * @param array $serviceIds Array of service IDs
     * @return bool
     */
    public function setServicesForExtra(int $extraId, array $serviceIds): bool
    {
        // Delete existing assignments for this extra
        ServiceExtraServiceRecord::deleteAll(['extraId' => $extraId]);

        // Create new assignments
        foreach ($serviceIds as $index => $serviceId) {
            $record = new ServiceExtraServiceRecord();
            $record->extraId = $extraId;
            $record->serviceId = $serviceId;
            $record->sortOrder = $index;

            if (!$record->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get selected extras for a reservation
     *
     * @param int $reservationId
     * @return array Array with extra info, quantity, and price
     */
    public function getExtrasForReservation(int $reservationId): array
    {
        $records = ReservationExtraRecord::find()
            ->where(['reservationId' => $reservationId])
            ->all();

        $results = [];

        foreach ($records as $record) {
            $extra = $this->getExtraById($record->extraId);

            if ($extra) {
                $results[] = [
                    'id' => $record->id,
                    'extra' => $extra,
                    'quantity' => $record->quantity,
                    'price' => $record->price,
                    'totalPrice' => $record->getTotalPrice(),
                ];
            }
        }

        return $results;
    }

    /**
     * Save selected extras for a reservation
     *
     * @param int $reservationId
     * @param array $extras Array of ['extraId' => quantity]
     * @return bool
     */
    public function saveExtrasForReservation(int $reservationId, array $extras): bool
    {
        // Delete existing extras for this reservation
        ReservationExtraRecord::deleteAll(['reservationId' => $reservationId]);

        // Save new extras
        foreach ($extras as $extraId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $extra = $this->getExtraById($extraId);

            if (!$extra || !$extra->enabled) {
                continue;
            }

            // Validate quantity
            if (!$extra->isValidQuantity($quantity)) {
                Craft::warning(
                    "Invalid quantity {$quantity} for extra {$extraId}, max is {$extra->maxQuantity}",
                    __METHOD__
                );
                continue;
            }

            $record = new ReservationExtraRecord();
            $record->reservationId = $reservationId;
            $record->extraId = $extraId;
            $record->quantity = $quantity;
            $record->price = $extra->price; // Store current price for historical accuracy

            if (!$record->save()) {
                Craft::error("Failed to save extra {$extraId} for reservation {$reservationId}", __METHOD__);
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate total price for selected extras
     *
     * @param array $extras Array of ['extraId' => quantity]
     * @return float
     */
    public function calculateExtrasPrice(array $extras): float
    {
        $total = 0.0;

        foreach ($extras as $extraId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $extra = $this->getExtraById($extraId);

            if ($extra && $extra->enabled) {
                $total += $extra->getTotalPrice($quantity);
            }
        }

        return $total;
    }

    /**
     * Calculate total additional duration for selected extras
     *
     * @param array $extras Array of ['extraId' => quantity]
     * @return int Total additional minutes
     */
    public function calculateExtrasDuration(array $extras): int
    {
        $totalMinutes = 0;

        foreach ($extras as $extraId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $extra = $this->getExtraById($extraId);

            if ($extra && $extra->enabled) {
                $totalMinutes += $extra->getTotalDuration($quantity);
            }
        }

        return $totalMinutes;
    }

    /**
     * Validate required extras are selected
     *
     * @param int $serviceId
     * @param array $selectedExtras Array of ['extraId' => quantity]
     * @return array Array of missing required extra names
     */
    public function validateRequiredExtras(int $serviceId, array $selectedExtras): array
    {
        $serviceExtras = $this->getExtrasForService($serviceId);
        $missing = [];

        foreach ($serviceExtras as $extra) {
            if ($extra->isRequired) {
                $quantity = $selectedExtras[$extra->id] ?? 0;

                if ($quantity <= 0) {
                    $missing[] = $extra->name;
                }
            }
        }

        return $missing;
    }

    /**
     * Get extras summary for display
     *
     * @param int $reservationId
     * @return string Formatted summary
     */
    public function getExtrasSummary(int $reservationId): string
    {
        $extras = $this->getExtrasForReservation($reservationId);

        if (empty($extras)) {
            return '';
        }

        $lines = [];

        foreach ($extras as $item) {
            $extra = $item['extra'];
            $quantity = $item['quantity'];
            $price = $item['totalPrice'];

            $line = $extra->name;

            if ($quantity > 1) {
                $line .= " x{$quantity}";
            }

            $line .= ' - ' . Craft::$app->formatter->asCurrency($price);

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Get total extras price for a reservation
     */
    public function getTotalExtrasPrice(int $reservationId): float
    {
        $total = 0.0;

        $records = ReservationExtraRecord::find()
            ->where(['reservationId' => $reservationId])
            ->all();

        foreach ($records as $record) {
            $total += $record->getTotalPrice();
        }

        return $total;
    }
}
