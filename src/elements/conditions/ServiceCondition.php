<?php

namespace fabian\booked\elements\conditions;

use craft\elements\conditions\ElementCondition;
use craft\elements\conditions\HasDescendantsRule;
use craft\elements\conditions\LevelConditionRule;

/**
 * Service query condition.
 *
 * Extends the base element condition to include structure-specific
 * condition rules like Level and HasDescendants for hierarchical services.
 */
class ServiceCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            HasDescendantsRule::class,
            LevelConditionRule::class,
        ]);
    }
}
