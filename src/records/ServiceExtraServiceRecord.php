<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * ServiceExtraService Record
 *
 * Junction table linking service extras to services (many-to-many).
 * An extra can be offered for multiple services, and a service can have multiple extras.
 *
 * @property int $id
 * @property int $extraId
 * @property int $serviceId
 * @property int $sortOrder Display order for this service
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ServiceExtraServiceRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_service_extras_services}}';
    }

    /**
     * Get the service extra
     */
    public function getExtra(): ActiveQueryInterface
    {
        return $this->hasOne(ServiceExtraRecord::class, ['id' => 'extraId']);
    }

    /**
     * Get the service
     */
    public function getService(): ActiveQueryInterface
    {
        return $this->hasOne(\craft\records\Element::class, ['id' => 'serviceId']);
    }
}
