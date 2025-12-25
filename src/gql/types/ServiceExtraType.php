<?php

namespace fabian\booked\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * ServiceExtra GraphQL Type
 *
 * Represents a service extra/add-on in the GraphQL schema
 */
class ServiceExtraType extends ObjectType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['fields'] = [
            'id' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::int()),
                'description' => 'The ID of the service extra',
            ],
            'name' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::string()),
                'description' => 'The name of the service extra',
            ],
            'description' => [
                'type' => \GraphQL\Type\Definition\Type::string(),
                'description' => 'The description of the service extra',
            ],
            'price' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::float()),
                'description' => 'The price of the service extra',
            ],
            'duration' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::int()),
                'description' => 'Additional duration in minutes',
            ],
            'maxQuantity' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::int()),
                'description' => 'Maximum quantity allowed per booking',
            ],
            'isRequired' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::boolean()),
                'description' => 'Whether this extra is required',
            ],
            'sortOrder' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::int()),
                'description' => 'Sort order for display',
            ],
            'enabled' => [
                'type' => \GraphQL\Type\Definition\Type::nonNull(\GraphQL\Type\Definition\Type::boolean()),
                'description' => 'Whether this extra is enabled',
            ],
            'dateCreated' => [
                'type' => \GraphQL\Type\Definition\Type::string(),
                'description' => 'The date the extra was created',
                'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                    return $source->dateCreated?->format('Y-m-d H:i:s');
                },
            ],
            'dateUpdated' => [
                'type' => \GraphQL\Type\Definition\Type::string(),
                'description' => 'The date the extra was last updated',
                'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                    return $source->dateUpdated?->format('Y-m-d H:i:s');
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
                'description' => 'This entity represents a service extra/add-on',
            ]));
    }

    /**
     * Get the type name
     */
    public static function getName(): string
    {
        return 'ServiceExtra';
    }
}
