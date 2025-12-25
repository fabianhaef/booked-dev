<?php

namespace fabian\booked\gql\queries;

use craft\gql\base\Query;
use fabian\booked\Booked;
use fabian\booked\gql\types\ServiceExtraType;
use GraphQL\Type\Definition\Type;

/**
 * ServiceExtras GraphQL Query
 *
 * Provides GraphQL queries for service extras
 */
class ServiceExtrasQuery extends Query
{
    /**
     * Get all extras query
     */
    public static function getQueries(bool $checkToken = true): array
    {
        return [
            'serviceExtras' => [
                'type' => Type::listOf(ServiceExtraType::getType()),
                'description' => 'Query all service extras',
                'args' => [
                    'serviceId' => [
                        'type' => Type::int(),
                        'description' => 'Filter by service ID',
                    ],
                    'enabled' => [
                        'type' => Type::boolean(),
                        'description' => 'Filter by enabled status',
                    ],
                ],
                'resolve' => function ($source, array $arguments) {
                    $serviceId = $arguments['serviceId'] ?? null;
                    $enabledOnly = $arguments['enabled'] ?? true;

                    if ($serviceId) {
                        return Booked::getInstance()->serviceExtra->getExtrasForService($serviceId, $enabledOnly);
                    }

                    return Booked::getInstance()->serviceExtra->getAllExtras($enabledOnly);
                },
            ],
            'serviceExtra' => [
                'type' => ServiceExtraType::getType(),
                'description' => 'Query a single service extra by ID',
                'args' => [
                    'id' => [
                        'type' => Type::nonNull(Type::int()),
                        'description' => 'The ID of the service extra',
                    ],
                ],
                'resolve' => function ($source, array $arguments) {
                    return Booked::getInstance()->serviceExtra->getExtraById($arguments['id']);
                },
            ],
        ];
    }
}
