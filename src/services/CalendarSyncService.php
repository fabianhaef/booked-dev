<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Reservation;
use fabian\booked\events\AfterCalendarSyncEvent;
use fabian\booked\events\BeforeCalendarSyncEvent;
use fabian\booked\records\CalendarTokenRecord;
use fabian\booked\records\OAuthStateTokenRecord;
use fabian\booked\Booked;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use yii\base\InvalidConfigException;

/**
 * CalendarSyncService
 *
 * This service requires the Pro edition.
 */
class CalendarSyncService extends Component
{
    /**
     * Event constants
     */
    const EVENT_BEFORE_CALENDAR_SYNC = 'beforeCalendarSync';
    const EVENT_AFTER_CALENDAR_SYNC = 'afterCalendarSync';

    /**
     * Ensure Pro edition is active before using calendar sync features
     *
     * @throws InvalidConfigException
     */
    private function requirePro(): void
    {
        Booked::requireEdition(Booked::EDITION_PRO);
    }

    /**
     * Get OAuth authorization URL for a provider
     * Uses secure UUID-based state tokens instead of base64-encoded employeeId
     *
     * @throws InvalidConfigException If Pro edition is not active
     */
    public function getAuthUrl(Employee $employee, string $provider): string
    {
        $this->requirePro();

        // Create secure state token and store in database
        $stateRecord = OAuthStateTokenRecord::createToken($employee->id, $provider);

        if ($provider === 'google') {
            $client = $this->getGoogleClient();
            $client->setState($stateRecord->token);
            return $client->createAuthUrl();
        }
        if ($provider === 'outlook') {
            $client = $this->getOutlookClient();
            return $client->getAuthorizationUrl([
                'state' => $stateRecord->token,
                'scope' => ['openid', 'offline_access', 'https://graph.microsoft.com/Calendars.ReadWrite'],
            ]);
        }
        return '';
    }

    /**
     * Handle OAuth callback
     * Verifies state token and retrieves employeeId securely from database
     *
     * @param string $stateToken Secure UUID token from OAuth state parameter
     * @param string $code Authorization code from OAuth provider
     * @return bool Success status
     */
    public function handleCallback(string $stateToken, string $code): bool
    {
        // Verify and consume state token (one-time use, prevents CSRF)
        $stateData = OAuthStateTokenRecord::verifyAndConsume($stateToken);

        if (!$stateData) {
            Craft::error('Invalid or expired OAuth state token', __METHOD__);
            return false;
        }

        $employeeId = $stateData['employeeId'];
        $provider = $stateData['provider'];

        if ($provider === 'google') {
            $client = $this->getGoogleClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                Craft::error('Google OAuth error: ' . $token['error_description'], __METHOD__);
                return false;
            }

            return $this->saveToken($employeeId, 'google', [
                'accessToken' => $token['access_token'],
                'refreshToken' => $token['refresh_token'] ?? null,
                'expiresAt' => (new \DateTime())->modify('+' . $token['expires_in'] . ' seconds')->format('Y-m-d H:i:s'),
            ]);
        }

        if ($provider === 'outlook') {
            $client = $this->getOutlookClient();
            try {
                $token = $client->getAccessToken('authorization_code', [
                    'code' => $code,
                ]);

                return $this->saveToken($employeeId, 'outlook', [
                    'accessToken' => $token->getToken(),
                    'refreshToken' => $token->getRefreshToken(),
                    'expiresAt' => (new \DateTime())->setTimestamp($token->getExpires())->format('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                Craft::error('Outlook OAuth error: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        return false;
    }

    /**
     * Handle OAuth callback (DEPRECATED - for backward compatibility)
     * Use handleCallback($stateToken, $code) instead
     *
     * @deprecated Use handleCallback($stateToken, $code) for secure state token verification
     */
    public function handleCallbackLegacy(Employee $employee, string $provider, string $code): bool
    {
        Craft::warning('handleCallbackLegacy() is deprecated. Use handleCallback($stateToken, $code) for secure state token verification.', __METHOD__);

        if ($provider === 'google') {
            $client = $this->getGoogleClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                Craft::error('Google OAuth error: ' . $token['error_description'], __METHOD__);
                return false;
            }

            return $this->saveToken($employee->id, 'google', [
                'accessToken' => $token['access_token'],
                'refreshToken' => $token['refresh_token'] ?? null,
                'expiresAt' => (new \DateTime())->modify('+' . $token['expires_in'] . ' seconds')->format('Y-m-d H:i:s'),
            ]);
        }

        if ($provider === 'outlook') {
            $client = $this->getOutlookClient();
            try {
                $token = $client->getAccessToken('authorization_code', [
                    'code' => $code,
                ]);

                return $this->saveToken($employee->id, 'outlook', [
                    'accessToken' => $token->getToken(),
                    'refreshToken' => $token->getRefreshToken(),
                    'expiresAt' => (new \DateTime())->setTimestamp($token->getExpires())->format('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                Craft::error('Outlook OAuth error: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        return false;
    }

    /**
     * Get a valid access token for an employee/provider
     */
    public function getAccessToken(Employee $employee, string $provider): ?string
    {
        $tokenData = $this->getToken($employee->id, $provider);
        if (!$tokenData) {
            return null;
        }

        // Check if expired and refresh if necessary
        $now = new \DateTime();
        $expiresAt = new \DateTime($tokenData['expiresAt']);
        
        if ($expiresAt <= $now) {
            return $this->refreshToken($employee, $provider, $tokenData['refreshToken']);
        }

        return $tokenData['accessToken'];
    }

    /**
     * @throws InvalidConfigException If Pro edition is not active
     */
    public function syncToExternal(Reservation $reservation): bool
    {
        $this->requirePro();

        $employee = $reservation->getEmployee();
        if (!$employee) {
            return false;
        }

        // Google Sync
        $googleToken = $this->getAccessToken($employee, 'google');
        if ($googleToken) {
            $this->syncToGoogle($reservation, $googleToken);
        }

        // Outlook Sync
        $outlookToken = $this->getAccessToken($employee, 'outlook');
        if ($outlookToken) {
            $this->syncToOutlook($reservation, $outlookToken);
        }

        return true;
    }

    /**
     * Sync to Google Calendar
     */
    protected function syncToGoogle(Reservation $reservation, string $token): bool
    {
        $startTime = microtime(true);

        $client = $this->getGoogleClient();
        $client->setAccessToken($token);
        $service = new GoogleCalendar($client);

        $eventData = [
            'summary' => $reservation->getService()->title ?? 'Buchung',
            'description' => 'Kunde: ' . $reservation->userName . "\n" .
                             'E-Mail: ' . $reservation->userEmail . "\n" .
                             'Notizen: ' . ($reservation->notes ?? '-'),
            'start' => [
                'dateTime' => $reservation->bookingDate . 'T' . $reservation->startTime,
                'timeZone' => 'Europe/Zurich',
            ],
            'end' => [
                'dateTime' => $reservation->bookingDate . 'T' . $reservation->endTime,
                'timeZone' => 'Europe/Zurich',
            ],
        ];

        // Fire BEFORE_CALENDAR_SYNC event
        $beforeSyncEvent = new BeforeCalendarSyncEvent([
            'reservation' => $reservation,
            'provider' => 'google',
            'action' => 'create',
            'eventData' => $eventData,
            'employeeId' => $reservation->employeeId,
        ]);
        $this->trigger(self::EVENT_BEFORE_CALENDAR_SYNC, $beforeSyncEvent);

        // Check if event was cancelled
        if (!$beforeSyncEvent->isValid) {
            $errorMessage = $beforeSyncEvent->errorMessage ?? 'Calendar sync was cancelled by event handler';
            Craft::warning("Calendar sync cancelled by event handler: {$errorMessage}", __METHOD__);

            // Fire AFTER event with failure
            $afterSyncEvent = new AfterCalendarSyncEvent([
                'reservation' => $reservation,
                'provider' => 'google',
                'action' => 'create',
                'success' => false,
                'errorMessage' => $errorMessage,
                'duration' => microtime(true) - $startTime,
            ]);
            $this->trigger(self::EVENT_AFTER_CALENDAR_SYNC, $afterSyncEvent);

            return false;
        }

        // Use potentially modified event data
        $event = new \Google\Service\Calendar\Event($beforeSyncEvent->eventData);

        try {
            $createdEvent = $service->events->insert('primary', $event);
            $duration = microtime(true) - $startTime;

            // Fire AFTER_CALENDAR_SYNC event
            $afterSyncEvent = new AfterCalendarSyncEvent([
                'reservation' => $reservation,
                'provider' => 'google',
                'action' => 'create',
                'success' => true,
                'externalEventId' => $createdEvent->getId(),
                'response' => [
                    'id' => $createdEvent->getId(),
                    'htmlLink' => $createdEvent->getHtmlLink(),
                ],
                'duration' => $duration,
            ]);
            $this->trigger(self::EVENT_AFTER_CALENDAR_SYNC, $afterSyncEvent);

            return true;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $errorMessage = $e->getMessage();

            Craft::error('Failed to sync booking to Google Calendar: ' . $errorMessage, __METHOD__);

            // Fire AFTER_CALENDAR_SYNC event with failure
            $afterSyncEvent = new AfterCalendarSyncEvent([
                'reservation' => $reservation,
                'provider' => 'google',
                'action' => 'create',
                'success' => false,
                'errorMessage' => $errorMessage,
                'duration' => $duration,
            ]);
            $this->trigger(self::EVENT_AFTER_CALENDAR_SYNC, $afterSyncEvent);

            return false;
        }
    }

    /**
     * Sync to Outlook Calendar
     */
    protected function syncToOutlook(Reservation $reservation, string $token): bool
    {
        $startTime = microtime(true);

        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($token);

        $eventData = [
            'subject' => $reservation->getService()->title ?? 'Buchung',
            'body' => [
                'contentType' => 'HTML',
                'content' => 'Kunde: ' . $reservation->userName . '<br>' .
                             'E-Mail: ' . $reservation->userEmail . '<br>' .
                             'Notizen: ' . ($reservation->notes ?? '-'),
            ],
            'start' => [
                'dateTime' => $reservation->bookingDate . 'T' . $reservation->startTime,
                'timeZone' => 'W. Europe Standard Time',
            ],
            'end' => [
                'dateTime' => $reservation->bookingDate . 'T' . $reservation->endTime,
                'timeZone' => 'W. Europe Standard Time',
            ],
        ];

        // Fire BEFORE_CALENDAR_SYNC event
        $beforeSyncEvent = new BeforeCalendarSyncEvent([
            'reservation' => $reservation,
            'provider' => 'outlook',
            'action' => 'create',
            'eventData' => $eventData,
            'employeeId' => $reservation->employeeId,
        ]);
        $this->trigger(self::EVENT_BEFORE_CALENDAR_SYNC, $beforeSyncEvent);

        // Check if event was cancelled
        if (!$beforeSyncEvent->isValid) {
            $errorMessage = $beforeSyncEvent->errorMessage ?? 'Calendar sync was cancelled by event handler';
            Craft::warning("Calendar sync cancelled by event handler: {$errorMessage}", __METHOD__);

            // Fire AFTER event with failure
            $afterSyncEvent = new AfterCalendarSyncEvent([
                'reservation' => $reservation,
                'provider' => 'outlook',
                'action' => 'create',
                'success' => false,
                'errorMessage' => $errorMessage,
                'duration' => microtime(true) - $startTime,
            ]);
            $this->trigger(self::EVENT_AFTER_CALENDAR_SYNC, $afterSyncEvent);

            return false;
        }

        try {
            $response = $graph->createRequest('POST', '/me/events')
                ->attachBody($beforeSyncEvent->eventData)
                ->execute();
            $duration = microtime(true) - $startTime;

            // Fire AFTER_CALENDAR_SYNC event
            $afterSyncEvent = new AfterCalendarSyncEvent([
                'reservation' => $reservation,
                'provider' => 'outlook',
                'action' => 'create',
                'success' => true,
                'externalEventId' => $response->getId() ?? null,
                'response' => [
                    'id' => $response->getId() ?? null,
                    'webLink' => $response->getWebLink() ?? null,
                ],
                'duration' => $duration,
            ]);
            $this->trigger(self::EVENT_AFTER_CALENDAR_SYNC, $afterSyncEvent);

            return true;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $errorMessage = $e->getMessage();

            Craft::error('Failed to sync booking to Outlook Calendar: ' . $errorMessage, __METHOD__);

            // Fire AFTER_CALENDAR_SYNC event with failure
            $afterSyncEvent = new AfterCalendarSyncEvent([
                'reservation' => $reservation,
                'provider' => 'outlook',
                'action' => 'create',
                'success' => false,
                'errorMessage' => $errorMessage,
                'duration' => $duration,
            ]);
            $this->trigger(self::EVENT_AFTER_CALENDAR_SYNC, $afterSyncEvent);

            return false;
        }
    }

    /**
     * Pull events from external calendar
     */
    public function syncFromExternal(Employee $employee, string $provider): int
    {
        $token = $this->getAccessToken($employee, $provider);
        if (!$token) {
            return 0;
        }

        if ($provider === 'google') {
            return $this->syncFromGoogle($employee, $token);
        }

        if ($provider === 'outlook') {
            return $this->syncFromOutlook($employee, $token);
        }

        return 0;
    }

    /**
     * Sync from Google Calendar
     */
    protected function syncFromGoogle(Employee $employee, string $token): int
    {
        $client = $this->getGoogleClient();
        $client->setAccessToken($token);
        $service = new GoogleCalendar($client);

        $optParams = [
            'timeMin' => (new \DateTime())->format(\DateTime::RFC3339),
            'timeMax' => (new \DateTime())->modify('+30 days')->format(\DateTime::RFC3339),
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ];

        try {
            $results = $service->events->listEvents('primary', $optParams);
            $events = $results->getItems();
            
            return $this->processExternalEvents($employee, 'google', $events, function($event) {
                return [
                    'externalId' => $event->getId(),
                    'summary' => $event->getSummary(),
                    'start' => $event->start->dateTime ?: $event->start->date,
                    'end' => $event->end->dateTime ?: $event->end->date,
                ];
            });
        } catch (\Exception $e) {
            Craft::error('Failed to sync events from Google Calendar: ' . $e->getMessage(), __METHOD__);
            return 0;
        }
    }

    /**
     * Sync from Outlook Calendar
     */
    protected function syncFromOutlook(Employee $employee, string $token): int
    {
        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($token);

        try {
            $start = (new \DateTime())->format(\DateTime::RFC3339);
            $end = (new \DateTime())->modify('+30 days')->format(\DateTime::RFC3339);
            
            $url = '/me/calendarView?startDateTime=' . $start . '&endDateTime=' . $end;
            $events = $graph->createRequest('GET', $url)
                ->setReturnType(\Microsoft\Graph\Model\Event::class)
                ->execute();

            return $this->processExternalEvents($employee, 'outlook', $events, function($event) {
                return [
                    'externalId' => $event->getId(),
                    'summary' => $event->getSubject(),
                    'start' => $event->getStart()->getDateTime(),
                    'end' => $event->getEnd()->getDateTime(),
                ];
            });
        } catch (\Exception $e) {
            Craft::error('Failed to sync events from Outlook Calendar: ' . $e->getMessage(), __METHOD__);
            return 0;
        }
    }

    /**
     * Common processing for external events
     */
    protected function processExternalEvents(Employee $employee, string $provider, array $events, callable $mapper): int
    {
        if (empty($events)) {
            return 0;
        }

        // Delete existing external events for this employee/provider
        \fabian\booked\records\ExternalEventRecord::deleteAll([
            'employeeId' => $employee->id,
            'provider' => $provider,
        ]);

        $count = 0;
        foreach ($events as $event) {
            $data = $mapper($event);
            
            if (!$data['start'] || !$data['end']) continue;

            $startDate = new \DateTime($data['start']);
            $endDate = new \DateTime($data['end']);

            $record = new \fabian\booked\records\ExternalEventRecord();
            $record->employeeId = $employee->id;
            $record->provider = $provider;
            $record->externalId = $data['externalId'];
            $record->summary = $data['summary'];
            $record->startDate = $startDate->format('Y-m-d');
            $record->startTime = $startDate->format('H:i:s');
            $record->endDate = $endDate->format('Y-m-d');
            $record->endTime = $endDate->format('H:i:s');
            
            if ($record->save()) {
                $count++;
            }
        }

        // Clear availability cache for this employee
        Booked::getInstance()->getAvailabilityCache()->invalidateAllForEmployee($employee->id);

        return $count;
    }

    /**
     * Helper to get token data (to be mocked in tests)
     */
    protected function getToken(int $employeeId, string $provider): ?array
    {
        $record = CalendarTokenRecord::findOne([
            'employeeId' => $employeeId,
            'provider' => $provider,
        ]);

        if (!$record) {
            return null;
        }

        return [
            'accessToken' => $record->accessToken,
            'refreshToken' => $record->refreshToken,
            'expiresAt' => $record->expiresAt,
        ];
    }

    /**
     * Helper to save token data (to be mocked in tests)
     */
    protected function saveToken(int $employeeId, string $provider, array $data): bool
    {
        $record = CalendarTokenRecord::findOne([
            'employeeId' => $employeeId,
            'provider' => $provider,
        ]) ?? new CalendarTokenRecord();

        $record->employeeId = $employeeId;
        $record->provider = $provider;
        $record->accessToken = $data['accessToken'];
        $record->refreshToken = $data['refreshToken'] ?? $record->refreshToken;
        $record->expiresAt = $data['expiresAt'];

        return $record->save();
    }

    /**
     * Refresh an expired token
     */
    protected function refreshToken(Employee $employee, string $provider, ?string $refreshToken): ?string
    {
        if (!$refreshToken) {
            return null;
        }

        if ($provider === 'google') {
            $client = $this->getGoogleClient();
            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($token['error'])) {
                Craft::error('Google token refresh error: ' . $token['error_description'], __METHOD__);
                return null;
            }

            $this->saveToken($employee->id, 'google', [
                'accessToken' => $token['access_token'],
                'refreshToken' => $token['refresh_token'] ?? $refreshToken,
                'expiresAt' => (new \DateTime())->modify('+' . $token['expires_in'] . ' seconds')->format('Y-m-d H:i:s'),
            ]);

            return $token['access_token'];
        }

        if ($provider === 'outlook') {
            $client = $this->getOutlookClient();
            try {
                $token = $client->getAccessToken('refresh_token', [
                    'refresh_token' => $refreshToken,
                ]);

                $this->saveToken($employee->id, 'outlook', [
                    'accessToken' => $token->getToken(),
                    'refreshToken' => $token->getRefreshToken(),
                    'expiresAt' => (new \DateTime())->setTimestamp($token->getExpires())->format('Y-m-d H:i:s'),
                ]);

                return $token->getToken();
            } catch (\Exception $e) {
                Craft::error('Outlook token refresh error: ' . $e->getMessage(), __METHOD__);
                return null;
            }
        }

        return null;
    }

    /**
     * Get a configured Outlook API client (OAuth2 Generic Provider)
     */
    protected function getOutlookClient(): \League\OAuth2\Client\Provider\GenericProvider
    {
        $settings = Booked::getInstance()->getSettings();

        return new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => $settings->outlookCalendarClientId,
            'clientSecret' => $settings->outlookCalendarClientSecret,
            'redirectUri' => $this->getRedirectUri(),
            'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes' => 'openid offline_access https://graph.microsoft.com/Calendars.ReadWrite',
        ]);
    }

    /**
     * Get a configured Google API client
     */
    public function getGoogleClient(): GoogleClient
    {
        $settings = Booked::getInstance()->getSettings();
        
        $client = new GoogleClient();
        $client->setClientId($settings->googleCalendarClientId);
        $client->setClientSecret($settings->googleCalendarClientSecret);
        $client->setRedirectUri($this->getRedirectUri());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(GoogleCalendar::CALENDAR);
        
        return $client;
    }

    /**
     * Get the OAuth redirect URI
     */
    protected function getRedirectUri(): string
    {
        return \craft\helpers\UrlHelper::cpUrl('booked/calendar/callback');
    }
}

