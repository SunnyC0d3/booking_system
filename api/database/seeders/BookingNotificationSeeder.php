<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingNotification;
use App\Constants\NotificationTypes;
use App\Constants\NotificationChannels;
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

        $this->command->info('Booking notifications seeded successfully!');
    }

    private function createNotificationsForBooking(Booking $booking): void
    {
        // Booking created notification (immediate)
        BookingNotification::create([
            'booking_id' => $booking->id,
            'type' => NotificationTypes::BOOKING_CREATED,
            'channel' => NotificationChannels::EMAIL,
            'recipient' => $booking->client_email,
            'status' => 'sent',
            'scheduled_at' => $booking->created_at,
            'sent_at' => $booking->created_at->addMinutes(2),
            'content' => 'Thank you for your booking! We will confirm your appointment shortly.',
        ]);

        // Booking reminder (1 hour before)
        if ($booking->scheduled_at->isFuture()) {
            BookingNotification::create([
                'booking_id' => $booking->id,
                'type' => NotificationTypes::BOOKING_REMINDER,
                'channel' => NotificationChannels::EMAIL,
                'recipient' => $booking->client_email,
                'status' => 'pending',
                'scheduled_at' => $booking->scheduled_at->clone()->subHour(),
                'content' => 'Reminder: Your appointment is scheduled for tomorrow.',
                'metadata' => ['minutes_before' => 60],
            ]);
        }

        // Consultation reminder (if applicable)
        if ($booking->requires_consultation && $booking->scheduled_at->isFuture()) {
            BookingNotification::create([
                'booking_id' => $booking->id,
                'type' => NotificationTypes::CONSULTATION_REMINDER,
                'channel' => NotificationChannels::EMAIL,
                'recipient' => $booking->client_email,
                'status' => 'pending',
                'scheduled_at' => $booking->scheduled_at->clone()->subDay(),
                'content' => 'Don\'t forget about your consultation tomorrow!',
                'metadata' => ['hours_before' => 24],
            ]);
        }

        // Follow-up notification (24 hours after service)
        if ($booking->ends_at->isPast()) {
            BookingNotification::create([
                'booking_id' => $booking->id,
                'type' => NotificationTypes::FOLLOW_UP,
                'channel' => NotificationChannels::EMAIL,
                'recipient' => $booking->client_email,
                'status' => rand(0, 1) ? 'sent' : 'pending',
                'scheduled_at' => $booking->ends_at->clone()->addDay(),
                'sent_at' => rand(0, 1) ? $booking->ends_at->clone()->addDay()->addMinutes(rand(1, 60)) : null,
                'content' => 'How was your experience? We\'d love your feedback!',
                'metadata' => ['hours_after_service' => 24],
            ]);
        }
    }
}
