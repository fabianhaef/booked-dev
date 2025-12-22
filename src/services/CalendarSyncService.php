<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Reservation;
use fabian\booked\records\CalendarTokenRecord;
use fabian\booked\Booked;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

/**
 * CalendarSyncService
 */
class CalendarSyncService extends Component
{
    /**
     * Get OAuth authorization URL for a provider
     */
    public function getAuthUrl(Employee $employee, string $provider): string
    {
        if ($provider === 'google') {
            $client = $this->getGoogleClient();
            $client->setState(base64_encode(json_encode([
                'employeeId' => $employee->id,
                'provider' => 'google',
            ])));
            return $client->createAuthUrl();
        }
        if ($provider === 'outlook') {
            $client = $this->getOutlookClient();
            return $client->getAuthorizationUrl([
                'state' => base64_encode(json_encode([
                    'employeeId' => $employee->id,
                    'provider' => 'outlook',
                ])),
                'scope' => ['openid', 'offline_access', 'https://graph.microsoft.com/Calendars.ReadWrite'],
            ]);
        }
        return '';
    }

    /**
     * Handle OAuth callback
     */
    public function handleCallback(Employee $employee, string $provider, string $code): bool
    {
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

    public function syncToExternal(Reservation $reservation): bool
    {
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
        $client = $this->getGoogleClient();
        $client->setAccessToken($token);
        $service = new GoogleCalendar($client);
        
        $event = new \Google\Service\Calendar\Event([
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
        ]);

        try {
            $service->events->insert('primary', $event);
            return true;
        } catch (\Exception $e) {
            Craft::error('Failed to sync booking to Google Calendar: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Sync to Outlook Calendar
     */
    protected function syncToOutlook(Reservation $reservation, string $token): bool
    {
        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($token);

        $event = [
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

        try {
            $graph->createRequest('POST', '/me/events')
                ->attachBody($event)
                ->execute();
            return true;
        } catch (\Exception $e) {
            Craft::error('Failed to sync booking to Outlook Calendar: ' . $e->getMessage(), __METHOD__);
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
    protected function getGoogleClient(): GoogleClient
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

