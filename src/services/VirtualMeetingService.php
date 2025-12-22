<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;

/**
 * VirtualMeetingService
 */
class VirtualMeetingService extends Component
{
    /**
     * Create a virtual meeting for a reservation
     */
    public function createMeeting(Reservation $reservation, string $provider): ?string
    {
        if ($provider === 'zoom') {
            $result = $this->createZoomMeeting($reservation);
            if ($result) {
                $reservation->virtualMeetingUrl = $result['url'];
                $reservation->virtualMeetingId = $result['id'];
                $reservation->virtualMeetingProvider = 'zoom';
                return $result['url'];
            }
        }
        if ($provider === 'google') {
            $result = $this->createGoogleMeetLink($reservation);
            if ($result) {
                $reservation->virtualMeetingUrl = $result['url'];
                $reservation->virtualMeetingId = $result['id'];
                $reservation->virtualMeetingProvider = 'google';
                return $result['url'];
            }
        }
        return null;
    }

    /**
     * Create a Zoom meeting
     */
    protected function createZoomMeeting(Reservation $reservation): ?array
    {
        $settings = Booked::getInstance()->getSettings();
        if (!$settings->zoomEnabled) {
            return null;
        }

        // Zoom Server-to-Server OAuth logic
        $token = $this->getZoomAccessToken();
        if (!$token) {
            return null;
        }

        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->post('https://api.zoom.us/v2/users/me/meetings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'topic' => $reservation->getService()->title ?? 'Buchung',
                    'type' => 2, // Scheduled meeting
                    'start_time' => $reservation->bookingDate . 'T' . $reservation->startTime . ':00Z',
                    'duration' => $reservation->getDurationMinutes(),
                    'timezone' => 'Europe/Zurich',
                    'settings' => [
                        'join_before_host' => true,
                        'mute_upon_entry' => true,
                        'waiting_room' => false,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return [
                'url' => $data['join_url'],
                'id' => (string)$data['id'],
            ];
        } catch (\Exception $e) {
            Craft::error('Zoom meeting creation failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Create a Google Meet link
     */
    protected function createGoogleMeetLink(Reservation $reservation): ?array
    {
        $employee = $reservation->getEmployee();
        if (!$employee) {
            return null;
        }

        $syncService = Booked::getInstance()->getCalendarSync();
        $token = $syncService->getAccessToken($employee, 'google');
        if (!$token) {
            return null;
        }

        $client = $syncService->getGoogleClient();
        $client->setAccessToken($token);
        $service = new GoogleCalendar($client);

        $event = new GoogleEvent([
            'summary' => $reservation->getService()->title ?? 'Buchung',
            'description' => 'Google Meet Meeting',
            'start' => [
                'dateTime' => $reservation->bookingDate . 'T' . $reservation->startTime,
                'timeZone' => 'Europe/Zurich',
            ],
            'end' => [
                'dateTime' => $reservation->bookingDate . 'T' . $reservation->endTime,
                'timeZone' => 'Europe/Zurich',
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'booked-' . $reservation->id . '-' . time(),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet'
                    ],
                ],
            ],
        ]);

        try {
            $createdEvent = $service->events->insert('primary', $event, ['conferenceDataVersion' => 1]);
            
            return [
                'url' => $createdEvent->getHangoutLink(),
                'id' => $createdEvent->getId(),
            ];
        } catch (\Exception $e) {
            Craft::error('Google Meet creation failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Get Zoom Access Token (Server-to-Server OAuth)
     */
    protected function getZoomAccessToken(): ?string
    {
        $settings = Booked::getInstance()->getSettings();
        $cacheKey = 'booked_zoom_access_token';
        
        $token = Craft::$app->cache->get($cacheKey);
        if ($token) {
            return $token;
        }

        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->post('https://zoom.us/oauth/token', [
                'form_params' => [
                    'grant_type' => 'account_credentials',
                    'account_id' => $settings->zoomAccountId,
                ],
                'auth' => [
                    $settings->zoomClientId,
                    $settings->zoomClientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $token = $data['access_token'];
            
            // Cache for slightly less than expires_in
            Craft::$app->cache->set($cacheKey, $token, $data['expires_in'] - 60);
            
            return $token;
        } catch (\Exception $e) {
            Craft::error('Zoom auth failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}

