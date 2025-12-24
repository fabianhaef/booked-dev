<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Db;
use fabian\booked\elements\Reservation;
use yii\db\Query;

/**
 * Commerce Service
 */
class CommerceService extends Component
{
    /**
     * Link a reservation to an order
     *
     * @param int $orderId
     * @param int $reservationId
     * @return bool
     */
    public function linkOrderToReservation(int $orderId, int $reservationId): bool
    {
        return Craft::$app->db->createCommand()
            ->insert('{{%booked_order_reservations}}', [
                'orderId' => $orderId,
                'reservationId' => $reservationId,
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ])
            ->execute() > 0;
    }

    /**
     * Get reservation by order ID
     *
     * @param int $orderId
     * @return Reservation|null
     */
    public function getReservationByOrderId(int $orderId): ?Reservation
    {
        $reservationId = (new Query())
            ->select(['reservationId'])
            ->from(['{{%booked_order_reservations}}'])
            ->where(['orderId' => $orderId])
            ->scalar();

        if (!$reservationId) {
            return null;
        }

        return Reservation::findOne($reservationId);
    }

    /**
     * Get order by reservation ID
     *
     * @param int $reservationId
     * @return Order|null
     */
    public function getOrderByReservationId(int $reservationId): ?Order
    {
        $orderId = (new Query())
            ->select(['orderId'])
            ->from(['{{%booked_order_reservations}}'])
            ->where(['reservationId' => $reservationId])
            ->scalar();

        if (!$orderId) {
            return null;
        }

        return Order::findOne($orderId);
    }

    /**
     * Add reservation to the current cart
     *
     * @param Reservation $reservation
     * @return bool
     */
    public function addReservationToCart(Reservation $reservation): bool
    {
        $cart = Commerce::getInstance()->getCarts()->getCart();

        if (!$cart) {
            return false;
        }

        // Create line item
        $lineItem = Commerce::getInstance()->getLineItems()->createLineItem($cart, $reservation->id, [], 1, '');

        // Add line item to cart
        $cart->addLineItem($lineItem);

        // Save the cart (which saves the line items)
        if (Craft::$app->getElements()->saveElement($cart)) {
            return $this->linkOrderToReservation($cart->id, $reservation->id);
        }

        return false;
    }
}

