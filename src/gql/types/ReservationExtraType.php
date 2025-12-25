<?php

namespace fabian\booked\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;

/**
 * ReservationExtra GraphQL Type
 *
 * Represents an extra selected for a specific reservation with quantity
 */
class ReservationExtraType extends ObjectType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['fields'] = [
            'extra' => [
                'type' => ServiceExtraType::getType(),
                'description' => 'The service extra',
            ],
            'quantity' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::int()),
                'description' => 'The quantity selected',
            ],
            'price' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::float()),
                'description' => 'The price at the time of booking',
            ],
            'totalPrice' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::float()),
                'description' => 'The total price (price × quantity)',
                'resolve' => function ($source) {
                    return $source['price'] * $source['quantity'];
                },
            ],
        ];

        parent::__construct($config);
    }

    /**
     * Get the GraphQL type
     */
    public static function getType(): \GraphQL\Type\Definition\Type
    {
        return GqlEntityRegistry::getEntity(self::getName()) ?:
            GqlEntityRegistry::createEntity(self::getName(), new self([
                'name' => self::getName(),
                'description' => 'This entity represents a service extra selected for a reservation',
            ]));
    }

    /**
     * Get the type name
     */
    public static function getName(): string
    {
        return 'ReservationExtra';
    }
}
