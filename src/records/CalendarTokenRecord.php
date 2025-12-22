<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * CalendarTokenRecord
 *
 * @property int $id
 * @property int $employeeId
 * @property string $provider
 * @property string $accessToken
 * @property string|null $refreshToken
 * @property string $expiresAt
 */
class CalendarTokenRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_calendar_tokens}}';
    }
}

