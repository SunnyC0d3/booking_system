<?php

namespace App\Services\V1\Calendar;

use App\Models\CalendarIntegration;
use App\Models\Booking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ICalService
{
    /**
     * Generate iCal authorization URL (for CalDAV or iCal feeds)
     */
    public function getAuthUrl(string $state): string
    {
        // For iCal, we'll redirect to a form where users can enter their calendar URL
        return route('calendar.ical.setup', ['state' => $state]);
    }

    /**
     * Exchange "code" for calendar access (iCal doesn't use OAuth, so we validate URL)
     */
    public function exchangeCodeForTokens(string $calendarUrl): array
    {
        try {
            // Validate the calendar URL by attempting to fetch it
            $this->validateCalendarUrl($calendarUrl);

            return [
                'access_token' => base64_encode($calendarUrl), // Store URL as "token"
                'refresh_token' => null, // iCal doesn't need refresh tokens
                'expires_in' => null, // iCal URLs don't expire
                'token_type' => 'ical',
            ];

        } catch (Exception $e) {
            Log::error('iCal URL validation failed', [
                'url' => $calendarUrl,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh tokens (not needed for iCal)
     */
    public function refreshTokens(string $refreshToken): array
    {
        throw new Exception('iCal calendars do not require token refresh');
    }

    /**
     * Get calendar information from iCal URL
     */
    public function getCalendarInfo(string $accessToken): array
    {
        try {
            $calendarUrl = base64_decode($accessToken);
            $calendarData = $this->fetchCalendarData($calendarUrl);

            // Parse calendar name from iCal data
            $calendarName = $this->extractCalendarName($calendarData);

            return [
                'id' => md5($calendarUrl), // Use URL hash as ID
                'name' => $calendarName ?: 'iCal Calendar',
                'description' => 'iCal/CalDAV Calendar',
                'timezone' => $this->extractTimezone($calendarData) ?: 'UTC',
                'color' => '#34A853', // Default green for iCal
            ];

        } catch (Exception $e) {
            Log::error('Failed to get iCal calendar info', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create event in iCal (generate iCal file for download)
     */
    public function createEvent(CalendarIntegration $integration, Booking $booking): ?string
    {
        try {
            // Verify user owns the booking or integration
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $integration->user_id && $currentUser->id !== $booking->user_id) {
                Log::warning('Unauthorized iCal event creation attempt', [
                    'user_id' => $currentUser->id,
                    'integration_user_id' => $integration->user_id,
                    'booking_user_id' => $booking->user_id,
                ]);
                return null;
            }

            $icalContent = $this->generateICalEvent($integration, $booking);
            $filename = 'booking-' . $booking->booking_reference . '.ics';

            // Store the iCal file
            Storage::disk('public')->put("ical/{$filename}", $icalContent);

            Log::info('iCal event file created', [
                'filename' => $filename,
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
            ]);

            return $filename;

        } catch (Exception $e) {
            Log::error('Failed to create iCal event', [
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update event (not supported for iCal)
     */
    public function updateEvent(CalendarIntegration $integration, Booking $booking, string $eventId): bool
    {
        // Verify user owns the booking or integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && $currentUser->id !== $booking->user_id) {
            Log::warning('Unauthorized iCal event update attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
                'booking_user_id' => $booking->user_id,
            ]);
            return false;
        }

        // iCal files are static, so we regenerate the file
        $newEventId = $this->createEvent($integration, $booking);

        if ($newEventId && $eventId !== $newEventId) {
            // Delete old file if it exists
            Storage::disk('public')->delete("ical/{$eventId}");
        }

        return $newEventId !== null;
    }

    /**
     * Delete event (remove iCal file)
     */
    public function deleteEvent(CalendarIntegration $integration, string $eventId): bool
    {
        try {
            // Verify user owns the integration
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                Log::warning('Unauthorized iCal event deletion attempt', [
                    'user_id' => $currentUser->id,
                    'integration_user_id' => $integration->user_id,
                ]);
                return false;
            }

            $deleted = Storage::disk('public')->delete("ical/{$eventId}");

            Log::info('iCal event file deleted', [
                'filename' => $eventId,
                'integration_id' => $integration->id,
                'success' => $deleted,
            ]);

            return $deleted;

        } catch (Exception $e) {
            Log::error('Failed to delete iCal event', [
                'event_id' => $eventId,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get events from iCal feed for availability checking
     */
    public function getEvents(CalendarIntegration $integration, Carbon $startTime, Carbon $endTime): array
    {
        // Verify user owns the integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            Log::warning('Unauthorized iCal events access attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
            ]);
            return [];
        }

        try {
            $calendarUrl = base64_decode($integration->access_token);
            $calendarData = $this->fetchCalendarData($calendarUrl);
            $events = $this->parseICalEvents($calendarData, $startTime, $endTime);

            return $events;

        } catch (Exception $e) {
            Log::error('Failed to get iCal events', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if time slot is available
     */
    public function isTimeSlotAvailable(CalendarIntegration $integration, Carbon $startTime, Carbon $endTime): bool
    {
        // Verify user owns the integration
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            Log::warning('Unauthorized iCal availability check attempt', [
                'user_id' => $currentUser->id,
                'integration_user_id' => $integration->user_id,
            ]);
            return true; // Default to available if unauthorized
        }

        $events = $this->getEvents($integration, $startTime, $endTime);

        foreach ($events as $event) {
            if (!$event['all_day']) {
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
     * Validate calendar URL
     */
    private function validateCalendarUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid calendar URL format');
        }

        // Try to fetch the calendar to validate it
        try {
            $response = Http::timeout(10)->get($url);

            if ($response->failed()) {
                throw new Exception('Unable to access calendar URL: ' . $response->status());
            }

            $content = $response->body();

            // Basic validation that it's an iCal file
            if (!str_contains($content, 'BEGIN:VCALENDAR')) {
                throw new Exception('URL does not contain valid iCal data');
            }

        } catch (Exception $e) {
            throw new Exception('Calendar URL validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch calendar data from URL
     */
    private function fetchCalendarData(string $url): string
    {
        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get($url);

        if ($response->failed()) {
            throw new Exception('Failed to fetch calendar data: ' . $response->status());
        }

        return $response->body();
    }

    /**
     * Extract calendar name from iCal data
     */
    private function extractCalendarName(string $icalData): ?string
    {
        if (preg_match('/X-WR-CALNAME:(.+)/i', $icalData, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/PRODID:(.+)/i', $icalData, $matches)) {
            return trim(str_replace(['-//', '//'], '', $matches[1]));
        }

        return null;
    }

    /**
     * Extract timezone from iCal data
     */
    private function extractTimezone(string $icalData): ?string
    {
        if (preg_match('/X-WR-TIMEZONE:(.+)/i', $icalData, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/TZID:(.+)/i', $icalData, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Generate iCal event content
     */
    private function generateICalEvent(CalendarIntegration $integration, Booking $booking): string
    {
        $settings = $integration->sync_settings_display;
        $uid = 'booking-' . $booking->id . '@' . config('app.url');
        $timestamp = now()->format('Ymd\THis\Z');

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Your Company//Booking System//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";

        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:{$uid}\r\n";
        $ical .= "DTSTAMP:{$timestamp}\r\n";
        $ical .= "DTSTART:" . $booking->scheduled_at->utc()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "DTEND:" . $booking->ends_at->utc()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "SUMMARY:" . $this->escapeICalText($integration->getCalendarEventTitle($booking)) . "\r\n";
        $ical .= "DESCRIPTION:" . $this->escapeICalText($integration->getCalendarEventDescription($booking)) . "\r\n";

        // Add location if available
        if ($settings['include_location'] && $booking->serviceLocation) {
            $location = $booking->serviceLocation;
            $locationText = implode(', ', array_filter([
                $location->name,
                $location->address_line_1,
                $location->city,
                $location->postcode,
            ]));
            $ical .= "LOCATION:" . $this->escapeICalText($locationText) . "\r\n";
        }

        // Add organizer
        $ical .= "ORGANIZER:mailto:" . config('mail.from.address') . "\r\n";

        // Add attendee if client email exists
        if ($booking->client_email) {
            $ical .= "ATTENDEE;CN=" . $this->escapeICalText($booking->client_name) . ":mailto:" . $booking->client_email . "\r\n";
        }

        // Add reminders as alarms
        if (!empty($settings['reminder_minutes'])) {
            foreach ($settings['reminder_minutes'] as $minutes) {
                $ical .= "BEGIN:VALARM\r\n";
                $ical .= "TRIGGER:-PT{$minutes}M\r\n";
                $ical .= "ACTION:DISPLAY\r\n";
                $ical .= "DESCRIPTION:Reminder: " . $this->escapeICalText($integration->getCalendarEventTitle($booking)) . "\r\n";
                $ical .= "END:VALARM\r\n";
            }
        }

        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "SEQUENCE:0\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    /**
     * Parse iCal events from calendar data
     */
    private function parseICalEvents(string $icalData, Carbon $startTime, Carbon $endTime): array
    {
        $events = [];

        // Split into lines and process
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $icalData));
        $currentEvent = null;
        $inEvent = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $currentEvent = [];
                continue;
            }

            if ($line === 'END:VEVENT' && $inEvent) {
                $inEvent = false;

                if ($currentEvent && isset($currentEvent['DTSTART']) && isset($currentEvent['DTEND'])) {
                    $eventStart = $this->parseICalDateTime($currentEvent['DTSTART']);
                    $eventEnd = $this->parseICalDateTime($currentEvent['DTEND']);

                    // Only include events within our time range
                    if ($eventStart->lt($endTime) && $eventEnd->gt($startTime)) {
                        $events[] = [
                            'id' => $currentEvent['UID'] ?? 'event-' . count($events),
                            'title' => $currentEvent['SUMMARY'] ?? 'Busy',
                            'start' => $eventStart->toISOString(),
                            'end' => $eventEnd->toISOString(),
                            'all_day' => $this->isAllDayEvent($currentEvent['DTSTART']),
                            'busy' => true,
                        ];
                    }
                }
                continue;
            }

            if ($inEvent && strpos($line, ':') !== false) {
                [$property, $value] = explode(':', $line, 2);
                $currentEvent[$property] = $value;
            }
        }

        return $events;
    }

    /**
     * Parse iCal datetime string
     */
    private function parseICalDateTime(string $datetime): Carbon
    {
        // Handle all-day events (date only)
        if (strlen($datetime) === 8) {
            return Carbon::createFromFormat('Ymd', $datetime)->startOfDay();
        }

        // Handle datetime with timezone
        if (str_ends_with($datetime, 'Z')) {
            return Carbon::createFromFormat('Ymd\THis\Z', $datetime);
        }

        // Handle local datetime
        if (strlen($datetime) === 15) {
            return Carbon::createFromFormat('Ymd\THis', $datetime);
        }

        // Fallback parsing
        return Carbon::parse($datetime);
    }

    /**
     * Check if event is all-day
     */
    private function isAllDayEvent(string $datetime): bool
    {
        return strlen($datetime) === 8; // Date only format
    }

    /**
     * Escape text for iCal format
     */
    private function escapeICalText(string $text): string
    {
        $text = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $text);
        return $text;
    }
}
