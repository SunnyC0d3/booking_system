<?php

namespace App\Services\V1\Calendar;

use App\Models\CalendarIntegration;
use App\Models\Booking;
use App\Models\ConsultationBooking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class GoogleCalendarService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $scopes;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect_uri');
        $this->scopes = [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ];
    }

    /**
     * Generate Google OAuth authorization URL
     */
    public function getAuthUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code): array
    {
        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if ($response->failed()) {
                throw new Exception('Google token exchange failed: ' . $response->body());
            }

            $data = $response->json();

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_in' => $data['expires_in'] ?? 3600,
                'token_type' => $data['token_type'] ?? 'Bearer',
            ];

        } catch (Exception $e) {
            Log::error('Google token exchange failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshTokens(string $refreshToken): array
    {
        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed()) {
                throw new Exception('Google token refresh failed: ' . $response->body());
            }

            $data = $response->json();

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken, // Google may not return new refresh token
                'expires_in' => $data['expires_in'] ?? 3600,
                'token_type' => $data['token_type'] ?? 'Bearer',
            ];

        } catch (Exception $e) {
            Log::error('Google token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get calendar information
     */
    public function getCalendarInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/calendar/v3/calendars/primary');

            if ($response->failed()) {
                throw new Exception('Failed to get Google calendar info: ' . $response->body());
            }

            $calendar = $response->json();

            return [
                'id' => $calendar['id'],
                'name' => $calendar['summary'] ?? 'Primary Calendar',
                'description' => $calendar['description'] ?? null,
                'timezone' => $calendar['timeZone'] ?? 'UTC',
                'color' => $calendar['backgroundColor'] ?? '#4285F4',
            ];

        } catch (Exception $e) {
            Log::error('Failed to get Google calendar info', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create event in Google Calendar
     */
    public function createEvent(CalendarIntegration $integration, Booking $booking): ?string
    {
        // Verify user owns the booking or integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && $currentUser->id !== $booking->user_id) {
            Log::warning('Unauthorized calendar event creation attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
                'booking_user_id' => $booking->user_id,
            ]);
            return null;
        }

        try {
            $accessToken = $this->getValidAccessToken($integration);
            $eventData = $this->buildEventData($integration, $booking);

            $response = Http::withToken($accessToken)
                ->post("https://www.googleapis.com/calendar/v3/calendars/{$integration->calendar_id}/events", $eventData);

            if ($response->failed()) {
                throw new Exception('Failed to create Google Calendar event: ' . $response->body());
            }

            $event = $response->json();

            Log::info('Google Calendar event created', [
                'event_id' => $event['id'],
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
            ]);

            return $event['id'];

        } catch (Exception $e) {
            Log::error('Failed to create Google Calendar event', [
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update event in Google Calendar
     */
    public function updateEvent(CalendarIntegration $integration, Booking $booking, string $eventId): bool
    {
        // Verify user owns the booking or integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && $currentUser->id !== $booking->user_id) {
            Log::warning('Unauthorized calendar event update attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
                'booking_user_id' => $booking->user_id,
            ]);
            return false;
        }

        try {
            $accessToken = $this->getValidAccessToken($integration);
            $eventData = $this->buildEventData($integration, $booking);

            $response = Http::withToken($accessToken)
                ->put("https://www.googleapis.com/calendar/v3/calendars/{$integration->calendar_id}/events/{$eventId}", $eventData);

            if ($response->failed()) {
                throw new Exception('Failed to update Google Calendar event: ' . $response->body());
            }

            Log::info('Google Calendar event updated', [
                'event_id' => $eventId,
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to update Google Calendar event', [
                'event_id' => $eventId,
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete event from Google Calendar
     */
    public function deleteEvent(CalendarIntegration $integration, string $eventId): bool
    {
        // Verify user owns the integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
            Log::warning('Unauthorized calendar event deletion attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
            ]);
            return false;
        }

        try {
            $accessToken = $this->getValidAccessToken($integration);

            $response = Http::withToken($accessToken)
                ->delete("https://www.googleapis.com/calendar/v3/calendars/{$integration->calendar_id}/events/{$eventId}");

            if ($response->failed() && $response->status() !== 404) {
                throw new Exception('Failed to delete Google Calendar event: ' . $response->body());
            }

            Log::info('Google Calendar event deleted', [
                'event_id' => $eventId,
                'integration_id' => $integration->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete Google Calendar event', [
                'event_id' => $eventId,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get events from Google Calendar for availability checking
     */
    public function getEvents(CalendarIntegration $integration, Carbon $startTime, Carbon $endTime): array
    {
        // Verify user owns the integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            Log::warning('Unauthorized Google Calendar events access attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
            ]);
            return [];
        }

        try {
            $accessToken = $this->getValidAccessToken($integration);

            $params = [
                'timeMin' => $startTime->toISOString(),
                'timeMax' => $endTime->toISOString(),
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'maxResults' => 250,
            ];

            $response = Http::withToken($accessToken)
                ->get("https://www.googleapis.com/calendar/v3/calendars/{$integration->calendar_id}/events", $params);

            if ($response->failed()) {
                throw new Exception('Failed to get Google Calendar events: ' . $response->body());
            }

            $data = $response->json();
            $events = [];

            foreach ($data['items'] ?? [] as $event) {
                // Skip events that are not busy (free/tentative)
                if (($event['transparency'] ?? 'opaque') === 'transparent') {
                    continue;
                }

                $events[] = [
                    'id' => $event['id'],
                    'title' => $event['summary'] ?? 'Busy',
                    'start' => $this->parseDateTime($event['start']),
                    'end' => $this->parseDateTime($event['end']),
                    'all_day' => isset($event['start']['date']),
                    'busy' => ($event['transparency'] ?? 'opaque') === 'opaque',
                ];
            }

            return $events;

        } catch (Exception $e) {
            Log::error('Failed to get Google Calendar events', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if calendar is available during specified time
     */
    public function isTimeSlotAvailable(CalendarIntegration $integration, Carbon $startTime, Carbon $endTime): bool
    {
        // Verify user owns the integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            Log::warning('Unauthorized Google Calendar availability check attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
            ]);
            return true; // Default to available if unauthorized
        }

        $events = $this->getEvents($integration, $startTime, $endTime);

        foreach ($events as $event) {
            if ($event['busy'] && !$event['all_day']) {
                $eventStart = Carbon::parse($event['start']);
                $eventEnd = Carbon::parse($event['end']);

                // Check for overlap
                if ($startTime->lt($eventEnd) && $endTime->gt($eventStart)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get valid access token, refreshing if necessary
     */
    private function getValidAccessToken(CalendarIntegration $integration): string
    {
        // Check if token is expired or about to expire
        if ($integration->token_expires_at && $integration->token_expires_at->lt(now()->addMinutes(5))) {
            if (!$integration->refresh_token) {
                throw new Exception('Access token expired and no refresh token available');
            }

            $refreshToken = Crypt::decrypt($integration->refresh_token);
            $tokenData = $this->refreshTokens($refreshToken);

            $integration->updateTokens(
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                now()->addSeconds($tokenData['expires_in'])
            );
        }

        return Crypt::decrypt($integration->access_token);
    }

    /**
     * Build event data for Google Calendar API
     */
    private function buildEventData(CalendarIntegration $integration, Booking $booking): array
    {
        $settings = $integration->sync_settings_display;

        $eventData = [
            'summary' => $integration->getCalendarEventTitle($booking),
            'description' => $integration->getCalendarEventDescription($booking),
            'start' => [
                'dateTime' => $booking->scheduled_at->toISOString(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $booking->ends_at->toISOString(),
                'timeZone' => config('app.timezone'),
            ],
            'colorId' => $this->getColorId($settings['calendar_color'] ?? '#4285F4'),
        ];

        // Add location if enabled and available
        if ($settings['include_location'] && $booking->serviceLocation) {
            $location = $booking->serviceLocation;
            $eventData['location'] = implode(', ', array_filter([
                $location->name,
                $location->address_line_1,
                $location->city,
                $location->postcode,
            ]));
        }

        // Add reminders
        if (!empty($settings['reminder_minutes'])) {
            $eventData['reminders'] = [
                'useDefault' => false,
                'overrides' => array_map(function ($minutes) {
                    return [
                        'method' => 'popup',
                        'minutes' => $minutes,
                    ];
                }, $settings['reminder_minutes']),
            ];
        }

        // Add attendees (client email)
        if ($booking->client_email) {
            $eventData['attendees'] = [
                [
                    'email' => $booking->client_email,
                    'displayName' => $booking->client_name,
                    'responseStatus' => 'accepted',
                ],
            ];
        }

        return $eventData;
    }

    /**
     * Parse Google Calendar datetime
     */
    private function parseDateTime(array $datetime): string
    {
        if (isset($datetime['dateTime'])) {
            return $datetime['dateTime'];
        }

        if (isset($datetime['date'])) {
            return $datetime['date'] . 'T00:00:00Z';
        }

        throw new Exception('Invalid datetime format in Google Calendar event');
    }

    /**
     * Get Google Calendar color ID from hex color
     */
    private function getColorId(string $hexColor): string
    {
        // Google Calendar predefined colors
        $colorMap = [
            '#a4bdfc' => '1',  // Lavender
            '#7ae7bf' => '2',  // Sage
            '#dbadff' => '3',  // Grape
            '#ff887c' => '4',  // Flamingo
            '#fbd75b' => '5',  // Banana
            '#ffb878' => '6',  // Tangerine
            '#46d6db' => '7',  // Peacock
            '#e1e1e1' => '8',  // Graphite
            '#5484ed' => '9',  // Blueberry
            '#51b749' => '10', // Basil
            '#dc2127' => '11', // Tomato
        ];

        // Default to blue if color not found
        return $colorMap[$hexColor] ?? '9';
    }
}
