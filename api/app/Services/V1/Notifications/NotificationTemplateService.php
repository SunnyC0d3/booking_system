<?php

namespace App\Services\V1\Notifications;

use App\Models\Booking;
use App\Models\ConsultationBooking;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class NotificationTemplateService
{
    /**
     * Get template configuration for notification type
     */
    public function getTemplateConfig(string $notificationType): array
    {
        $templates = config('notifications.templates', []);

        return $templates[$notificationType] ?? [
            'mail' => null,
            'subject' => 'Notification',
            'priority' => 'normal',
        ];
    }

    /**
     * Format notification subject with variables
     */
    public function formatSubject(string $template, array $variables = []): string
    {
        $subject = $template;

        foreach ($variables as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
        }

        return $subject;
    }

    /**
     * Get template variables for booking notification
     */
    public function getBookingVariables(Booking $booking): array
    {
        $booking->load(['user', 'service', 'serviceLocation', 'bookingAddOns.serviceAddOn']);

        $variables = [
            // Booking details
            'reference' => $booking->booking_reference,
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'status_display' => ucfirst(str_replace('_', ' ', $booking->status)),

            // Client information
            'client_name' => $booking->client_name ?: $booking->user->name,
            'client_email' => $booking->client_email ?: $booking->user->email,
            'client_phone' => $booking->client_phone ?: $booking->user->phone,

            // Service details
            'service_name' => $booking->service->name,
            'service_description' => $booking->service->description,
            'service_category' => $booking->service->category,

            // Timing
            'scheduled_date' => $booking->scheduled_at->format('l, F j, Y'),
            'scheduled_time' => $booking->scheduled_at->format('g:i A'),
            'scheduled_datetime' => $booking->scheduled_at->format('l, F j, Y \a\t g:i A'),
            'ends_at' => $booking->ends_at->format('g:i A'),
            'duration_minutes' => $booking->duration_minutes,
            'duration_display' => $this->formatDuration($booking->duration_minutes),
            'timezone' => $booking->scheduled_at->timezone->getName(),

            // Location
            'location_name' => $booking->serviceLocation?->name ?? 'To be confirmed',
            'location_address' => $this->formatLocationAddress($booking->serviceLocation),
            'location_type' => $booking->serviceLocation?->type ?? 'standard',

            // Pricing
            'base_price' => $this->formatPrice($booking->base_price),
            'addons_total' => $this->formatPrice($booking->addons_total),
            'total_amount' => $this->formatPrice($booking->total_amount),
            'deposit_amount' => $this->formatPrice($booking->deposit_amount),
            'remaining_amount' => $this->formatPrice($booking->remaining_amount),

            // Payment status
            'payment_status' => $booking->payment_status,
            'payment_status_display' => ucfirst(str_replace('_', ' ', $booking->payment_status)),

            // Special requirements
            'special_requirements' => $booking->special_requirements ?: 'None specified',
            'notes' => $booking->notes ?: 'No additional notes',

            // Add-ons
            'addons_list' => $this->formatAddOnsList($booking->bookingAddOns),
            'addons_count' => $booking->bookingAddOns->count(),

            // Consultation
            'requires_consultation' => $booking->requires_consultation,
            'consultation_completed' => !is_null($booking->consultation_completed_at),
            'consultation_date' => $booking->consultation_completed_at?->format('F j, Y'),

            // Timing helpers
            'is_today' => $booking->scheduled_at->isToday(),
            'is_tomorrow' => $booking->scheduled_at->isTomorrow(),
            'days_until' => max(0, $booking->scheduled_at->diffInDays(now(), false)),
            'hours_until' => max(0, $booking->scheduled_at->diffInHours(now(), false)),
            'time_until_display' => $this->formatTimeUntil($booking->scheduled_at),
        ];

        // Add contact information
        $variables = array_merge($variables, $this->getContactVariables());

        // Add company information
        $variables = array_merge($variables, $this->getCompanyVariables());

        return $variables;
    }

    /**
     * Get template variables for consultation notification
     */
    public function getConsultationVariables(ConsultationBooking $consultation): array
    {
        $consultation->load(['user', 'service', 'mainBooking']);

        $variables = [
            // Consultation details
            'reference' => $consultation->consultation_reference,
            'consultation_id' => $consultation->id,
            'status' => $consultation->status,
            'status_display' => ucfirst(str_replace('_', ' ', $consultation->status)),
            'type' => $consultation->type,
            'format' => $consultation->format,

            // Client information
            'client_name' => $consultation->client_name ?: $consultation->user->name,
            'client_email' => $consultation->client_email ?: $consultation->user->email,
            'client_phone' => $consultation->client_phone ?: $consultation->user->phone,

            // Service details
            'service_name' => $consultation->service->name,
            'service_description' => $consultation->service->description,

            // Timing
            'scheduled_date' => $consultation->scheduled_at->format('l, F j, Y'),
            'scheduled_time' => $consultation->scheduled_at->format('g:i A'),
            'scheduled_datetime' => $consultation->scheduled_at->format('l, F j, Y \a\t g:i A'),
            'ends_at' => $consultation->ends_at->format('g:i A'),
            'duration_minutes' => $consultation->duration_minutes,
            'duration_display' => $this->formatDuration($consultation->duration_minutes),
            'timezone' => $consultation->scheduled_at->timezone->getName(),

            // Meeting details
            'meeting_link' => $consultation->meeting_link,
            'meeting_location' => $consultation->meeting_location,
            'meeting_instructions' => $consultation->meeting_instructions,

            // Consultation content
            'consultation_notes' => $consultation->consultation_notes ?: 'No specific notes',
            'preparation_instructions' => $consultation->preparation_instructions ?: 'No special preparation needed',
            'consultation_questions' => $consultation->consultation_questions,

            // Related booking
            'main_booking_reference' => $consultation->mainBooking?->booking_reference,
            'main_booking_date' => $consultation->mainBooking?->scheduled_at->format('F j, Y'),

            // Payment (if applicable)
            'consultation_fee' => $this->formatPrice($consultation->consultation_fee),
            'fee_waived_if_booking' => $consultation->fee_waived_if_booking,
            'payment_status' => $consultation->payment_status,

            // Timing helpers
            'is_today' => $consultation->scheduled_at->isToday(),
            'is_tomorrow' => $consultation->scheduled_at->isTomorrow(),
            'days_until' => max(0, $consultation->scheduled_at->diffInDays(now(), false)),
            'hours_until' => max(0, $consultation->scheduled_at->diffInHours(now(), false)),
            'minutes_until' => max(0, $consultation->scheduled_at->diffInMinutes(now(), false)),
            'time_until_display' => $this->formatTimeUntil($consultation->scheduled_at),
        ];

        // Add contact and company information
        $variables = array_merge($variables, $this->getContactVariables());
        $variables = array_merge($variables, $this->getCompanyVariables());

        return $variables;
    }

    /**
     * Get template variables for payment notifications
     */
    public function getPaymentVariables(Booking $booking, array $paymentData = []): array
    {
        $variables = $this->getBookingVariables($booking);

        // Add payment-specific variables
        $paymentVariables = [
            'amount_due' => $this->formatPrice($paymentData['amount_due'] ?? $booking->remaining_amount),
            'due_date' => isset($paymentData['due_date']) ?
                Carbon::parse($paymentData['due_date'])->format('F j, Y') :
                $booking->scheduled_at->subDays(1)->format('F j, Y'),
            'payment_method' => $paymentData['payment_method'] ?? 'Online payment',
            'payment_url' => $paymentData['payment_url'] ?? url('/payments'),
            'is_overdue' => $paymentData['is_overdue'] ?? false,
            'days_overdue' => $paymentData['days_overdue'] ?? 0,
            'late_fee' => $this->formatPrice($paymentData['late_fee'] ?? 0),
            'is_final_notice' => $paymentData['is_final_notice'] ?? false,
        ];

        return array_merge($variables, $paymentVariables);
    }

    /**
     * Get company contact variables
     */
    private function getContactVariables(): array
    {
        return [
            'support_email' => config('mail.from.address'),
            'support_phone' => config('app.support_phone', '+44 20 3890 2370'),
            'website_url' => config('app.url'),
            'contact_hours' => 'Monday to Friday, 9am to 6pm',
        ];
    }

    /**
     * Get company information variables
     */
    private function getCompanyVariables(): array
    {
        return [
            'company_name' => config('app.name'),
            'company_address' => config('app.company_address', 'London, UK'),
            'company_email' => config('mail.from.address'),
            'company_phone' => config('app.company_phone', '+44 20 3890 2370'),
            'app_name' => config('app.name'),
        ];
    }

    /**
     * Format duration in minutes to human readable
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        $formatted = $hours . ' hour' . ($hours !== 1 ? 's' : '');

        if ($remainingMinutes > 0) {
            $formatted .= ' and ' . $remainingMinutes . ' minute' . ($remainingMinutes !== 1 ? 's' : '');
        }

        return $formatted;
    }

    /**
     * Format price in pence to pounds
     */
    private function formatPrice(?int $priceInPence): string
    {
        if (is_null($priceInPence) || $priceInPence === 0) {
            return '£0.00';
        }

        return '£' . number_format($priceInPence / 100, 2);
    }

    /**
     * Format location address
     */
    private function formatLocationAddress($location): string
    {
        if (!$location) {
            return 'Address to be confirmed';
        }

        $parts = array_filter([
            $location->address_line_1,
            $location->address_line_2,
            $location->city,
            $location->postcode,
        ]);

        return implode(', ', $parts) ?: 'Address to be confirmed';
    }

    /**
     * Format add-ons list
     */
    private function formatAddOnsList($bookingAddOns): string
    {
        if ($bookingAddOns->isEmpty()) {
            return 'No add-ons selected';
        }

        return $bookingAddOns->map(function ($bookingAddOn) {
            $addon = $bookingAddOn->serviceAddOn;
            $price = $this->formatPrice($bookingAddOn->price);
            return "• {$addon->name} - {$price}";
        })->implode("\n");
    }

    /**
     * Format time until an event
     */
    private function formatTimeUntil(Carbon $eventTime): string
    {
        $now = now();

        if ($eventTime->isPast()) {
            return 'Event has passed';
        }

        if ($eventTime->isToday()) {
            $hours = $eventTime->diffInHours($now);
            $minutes = $eventTime->diffInMinutes($now) % 60;

            if ($hours === 0) {
                return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
            }

            if ($minutes === 0) {
                return $hours . ' hour' . ($hours !== 1 ? 's' : '');
            }

            return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' and ' .
                $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }

        if ($eventTime->isTomorrow()) {
            return 'Tomorrow at ' . $eventTime->format('g:i A');
        }

        $days = $eventTime->diffInDays($now);
        return $days . ' day' . ($days !== 1 ? 's' : '');
    }

    /**
     * Validate template variables
     */
    public function validateTemplateVariables(array $variables, array $requiredVariables = []): bool
    {
        foreach ($requiredVariables as $required) {
            if (!isset($variables[$required])) {
                Log::warning('Missing required template variable', [
                    'required_variable' => $required,
                    'available_variables' => array_keys($variables),
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get SMS template content
     */
    public function getSmsTemplate(string $notificationType, array $variables = []): ?string
    {
        $config = $this->getTemplateConfig($notificationType);

        if (!isset($config['sms'])) {
            return null;
        }

        $template = $config['sms'];

        // Replace variables in SMS template
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get push notification content
     */
    public function getPushTemplate(string $notificationType, array $variables = []): array
    {
        $config = $this->getTemplateConfig($notificationType);

        $title = $config['push_title'] ?? $this->formatSubject($config['subject'] ?? 'Notification', $variables);
        $body = $config['push_body'] ?? 'You have a new notification';

        // Replace variables
        foreach ($variables as $key => $value) {
            $title = str_replace("{{$key}}", $value, $title);
            $body = str_replace("{{$key}}", $value, $body);
        }

        return [
            'title' => $title,
            'body' => $body,
            'icon' => $config['push_icon'] ?? '/images/notification-icon.png',
            'click_action' => $config['push_action'] ?? url('/notifications'),
        ];
    }

    /**
     * Log template usage for analytics
     */
    public function logTemplateUsage(string $notificationType, string $channel, ?string $userId = null): void
    {
        try {
            Log::info('Notification template used', [
                'notification_type' => $notificationType,
                'channel' => $channel,
                'user_id' => $userId,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
    }
}
