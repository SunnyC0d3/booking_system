<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingNotification;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class BookingNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $bookings = Booking::with(['service', 'user'])->limit(10)->get();

        foreach ($bookings as $booking) {
            $this->createNotificationsForBooking($booking);
        }
    }

    private function createNotificationsForBooking(Booking $booking): void
    {
        // Booking created notification (immediate)
        BookingNotification::create([
            'booking_id' => $booking->id,
            'notification_type' => 'confirmation', // Use notification_type instead of type
            'delivery_method' => 'email', // Use delivery_method instead of channel
            'scheduled_for' => $booking->created_at, // Use scheduled_for instead of scheduled_at
            'status' => 'sent',
            'sent_at' => $booking->created_at->addMinutes(2),
            'message' => 'Thank you for your booking! We will confirm your appointment shortly.', // Use message instead of content
            'subject' => 'Booking Confirmation - ' . $booking->service->name,
            'template_data' => json_encode([ // Ensure it's JSON encoded
                'booking_reference' => $booking->booking_reference ?? 'B' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                'service_name' => $booking->service->name,
                'client_name' => $booking->client_name,
                'scheduled_at' => $booking->scheduled_at->format('M j, Y g:i A'),
            ]),
        ]);

        // Booking confirmation (if status is confirmed)
        if ($booking->status === 'confirmed') {
            BookingNotification::create([
                'booking_id' => $booking->id,
                'notification_type' => 'confirmation',
                'delivery_method' => 'email',
                'scheduled_for' => $booking->updated_at ?? $booking->created_at->addHours(2),
                'status' => 'sent',
                'sent_at' => $booking->updated_at ?? $booking->created_at->addHours(2)->addMinutes(5),
                'message' => 'Great news! Your booking has been confirmed.',
                'subject' => 'Booking Confirmed - ' . $booking->service->name,
                'template_data' => json_encode([
                    'booking_reference' => $booking->booking_reference ?? 'B' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                    'service_name' => $booking->service->name,
                    'client_name' => $booking->client_name,
                    'scheduled_at' => $booking->scheduled_at->format('M j, Y g:i A'),
                ]),
            ]);
        }

        // Booking reminder (1 hour before) - only for future bookings
        if ($booking->scheduled_at->isFuture()) {
            $reminderTime = $booking->scheduled_at->clone()->subHour();

            BookingNotification::create([
                'booking_id' => $booking->id,
                'notification_type' => 'reminder_2h', // Use available enum value
                'delivery_method' => 'email',
                'scheduled_for' => $reminderTime,
                'status' => $reminderTime->isPast() ? 'sent' : 'pending',
                'sent_at' => $reminderTime->isPast() ? $reminderTime->addMinutes(rand(1, 5)) : null,
                'message' => 'Reminder: Your appointment is scheduled for tomorrow.',
                'subject' => 'Appointment Reminder - Tomorrow',
                'template_data' => json_encode([
                    'booking_reference' => $booking->booking_reference ?? 'B' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                    'service_name' => $booking->service->name,
                    'client_name' => $booking->client_name,
                    'scheduled_at' => $booking->scheduled_at->format('M j, Y g:i A'),
                    'minutes_before' => 60,
                ]),
            ]);
        }

        // Follow-up notification (24 hours after service) - only for past bookings
        if ($booking->ends_at && $booking->ends_at->isPast()) {
            $followUpTime = $booking->ends_at->clone()->addDay();
            $isOverdue = $followUpTime->isPast();

            BookingNotification::create([
                'booking_id' => $booking->id,
                'notification_type' => 'follow_up',
                'delivery_method' => 'email',
                'scheduled_for' => $followUpTime,
                'status' => $isOverdue ? (rand(0, 1) ? 'sent' : 'failed') : 'pending',
                'sent_at' => $isOverdue && rand(0, 1) ? $followUpTime->addMinutes(rand(1, 60)) : null,
                'message' => 'How was your experience? We\'d love your feedback!',
                'subject' => 'How was your experience with ' . $booking->service->name . '?',
                'template_data' => json_encode([
                    'booking_reference' => $booking->booking_reference ?? 'B' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                    'service_name' => $booking->service->name,
                    'client_name' => $booking->client_name,
                    'service_date' => $booking->scheduled_at->format('M j, Y'),
                    'hours_after_service' => 24,
                    'review_url' => url('/reviews/create/' . $booking->id),
                ]),
            ]);
        }

        // Handle cancelled bookings
        if ($booking->status === 'cancelled') {
            $this->createCancellationNotification($booking);
        }

        // Handle rescheduled bookings
        if ($booking->status === 'rescheduled' || (isset($booking->metadata['rescheduled']) && $booking->metadata['rescheduled'])) {
            $this->createRescheduleNotification($booking);
        }
    }

    private function createCancellationNotification(Booking $booking): void
    {
        BookingNotification::create([
            'booking_id' => $booking->id,
            'notification_type' => 'cancelled',
            'delivery_method' => 'email',
            'scheduled_for' => $booking->updated_at ?? now(),
            'status' => 'sent',
            'sent_at' => ($booking->updated_at ?? now())->addMinutes(2),
            'message' => 'Your booking has been cancelled. We hope to serve you again in the future.',
            'subject' => 'Booking Cancelled - ' . $booking->service->name,
            'template_data' => json_encode([
                'booking_reference' => $booking->booking_reference ?? 'B' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                'service_name' => $booking->service->name,
                'client_name' => $booking->client_name,
                'original_date' => $booking->scheduled_at->format('M j, Y g:i A'),
                'cancellation_reason' => $booking->metadata['cancellation_reason'] ?? 'Not specified',
            ]),
        ]);
    }

    private function createRescheduleNotification(Booking $booking): void
    {
        BookingNotification::create([
            'booking_id' => $booking->id,
            'notification_type' => 'rescheduled',
            'delivery_method' => 'email',
            'scheduled_for' => $booking->updated_at ?? now(),
            'status' => 'sent',
            'sent_at' => ($booking->updated_at ?? now())->addMinutes(3),
            'message' => 'Your booking has been rescheduled. Please check the new date and time.',
            'subject' => 'Booking Rescheduled - ' . $booking->service->name,
            'template_data' => json_encode([
                'booking_reference' => $booking->booking_reference ?? 'B' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                'service_name' => $booking->service->name,
                'client_name' => $booking->client_name,
                'new_date' => $booking->scheduled_at->format('M j, Y g:i A'),
                'original_date' => $booking->metadata['original_date'] ?? 'Previous date',
            ]),
        ]);
    }
}
