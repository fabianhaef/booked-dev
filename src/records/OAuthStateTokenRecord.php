<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * OAuth State Token Record
 *
 * Stores secure state tokens for OAuth flows to prevent CSRF attacks
 * and protect employeeId from exposure in base64-encoded state.
 *
 * @property int $id
 * @property string $token UUID-based secure token
 * @property int $employeeId Employee ID associated with this OAuth flow
 * @property string $provider OAuth provider (google, outlook)
 * @property string $createdAt Token creation time
 * @property string $expiresAt Token expiration time (default: 1 hour)
 */
class OAuthStateTokenRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_oauth_state_tokens}}';
    }

    /**
     * Create a new state token
     *
     * @param int $employeeId
     * @param string $provider
     * @return OAuthStateTokenRecord
     */
    public static function createToken(int $employeeId, string $provider): OAuthStateTokenRecord
    {
        $record = new self();
        $record->token = \craft\helpers\StringHelper::UUID();
        $record->employeeId = $employeeId;
        $record->provider = $provider;
        $record->createdAt = (new \DateTime())->format('Y-m-d H:i:s');
        $record->expiresAt = (new \DateTime('+1 hour'))->format('Y-m-d H:i:s');
        $record->save();

        return $record;
    }

    /**
     * Verify and consume a state token
     *
     * @param string $token
     * @return array|null ['employeeId' => int, 'provider' => string] or null if invalid/expired
     */
    public static function verifyAndConsume(string $token): ?array
    {
        $record = self::find()
            ->where(['token' => $token])
            ->andWhere(['>', 'expiresAt', (new \DateTime())->format('Y-m-d H:i:s')])
            ->one();

        if (!$record) {
            return null;
        }

        $data = [
            'employeeId' => $record->employeeId,
            'provider' => $record->provider,
        ];

        // Delete token after use (one-time use)
        $record->delete();

        return $data;
    }

    /**
     * Clean up expired tokens
     *
     * @return int Number of tokens deleted
     */
    public static function cleanupExpired(): int
    {
        return self::deleteAll(['<', 'expiresAt', (new \DateTime())->format('Y-m-d H:i:s')]);
    }
}
