<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ConsultationBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class ConsultationConfirmationMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'consultation.confirmation';

    private ConsultationBooking $consultation;
    private ?Booking $booking;

    /**
     * Create a new message instance.
     */
    public function __construct(ConsultationBooking $consultation, ?Booking $booking = null)
    {
        $this->consultation = $consultation;
        $this->booking = $booking;

        // Prepare email data using the consultation
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        return "Consultation Confirmed - {$this->consultation->service->name} (#{$this->consultation->consultation_reference})";
    }

    private function prepareEmailData(): array
    {
        return [
            'consultation' => [
                'id' => $this->consultation->id,
                'reference' => $this->consultation->consultation_reference,
                'status' => ucfirst($this->consultation->status),
                'type' => ucfirst(str_replace('_', ' ', $this->consultation->type)),
                'format' => ucfirst($this->consultation->format),
                'scheduled_at' => $this->consultation->scheduled_at->format('l, F j, Y'),
                'scheduled_time' => $this->consultation->scheduled_at->format('g:i A'),
                'timezone' => $this->consultation->scheduled_at->timezone->getName(),
                'ends_at' => $this->consultation->ends_at->format('g:i A'),
                'duration_minutes' => $this->consultation->duration_minutes,
                'duration_display' => $this->formatDuration($this->consultation->duration_minutes),
                'consultation_notes' => $this->consultation->consultation_notes,
                'preparation_instructions' => $this->consultation->preparation_instructions,
                'consultation_questions' => $this->consultation->consultation_questions,
                'priority' => ucfirst($this->consultation->priority ?? 'normal'),
                'workflow_stage' => ucfirst(str_replace('_', ' ', $this->consultation->workflow_stage ?? 'scheduled')),
            ],
            'service' => [
                'name' => $this->consultation->service->name,
                'description' => $this->consultation->service->description,
                'short_description' => $this->consultation->service->short_description,
                'duration_display' => $this->formatDuration($this->consultation->service->duration_minutes),
                'requires_consultation' => $this->consultation->service->requires_consultation,
            ],
            'client' => [
                'name' => $this->consultation->client_name,
                'email' => $this->consultation->client_email,
                'phone' => $this->consultation->client_phone,
            ],
            'meeting' => $this->getMeetingDetails(),
            'booking' => $this->booking ? [
                'id' => $this->booking->id,
                'reference' => $this->booking->booking_reference,
                'scheduled_at' => $this->booking->scheduled_at->format('l, F j, Y'),
                'scheduled_time' => $this->booking->scheduled_at->format('g:i A'),
                'duration_display' => $this->formatDuration($this->booking->duration_minutes),
                'total_amount' => $this->formatPrice($this->booking->total_amount),
                'status' => ucfirst($this->booking->status),
                'notes' => $this->booking->notes,
                'special_requirements' => $this->booking->special_requirements,
            ] : null,
            'location' => $this->booking && $this->booking->serviceLocation ? [
                'name' => $this->booking->serviceLocation->name,
                'address' => $this->booking->serviceLocation->address,
                'city' => $this->booking->serviceLocation->city,
                'postcode' => $this->booking->serviceLocation->postcode,
                'phone' => $this->booking->serviceLocation->phone,
                'notes' => $this->booking->serviceLocation->notes,
                'full_address' => $this->formatFullAddress($this->booking->serviceLocation),
            ] : null,
            'vendor' => $this->consultation->service->vendor ? [
                'name' => $this->consultation->service->vendor->name,
                'email' => $this->consultation->service->vendor->email,
                'phone' => $this->consultation->service->vendor->phone,
            ] : null,
            'preparation' => [
                'instructions' => $this->consultation->preparation_instructions,
                'questions' => $this->consultation->consultation_questions,
                'items_to_bring' => $this->getItemsToBring(),
                'what_to_expect' => $this->getWhatToExpect(),
            ],
            'company' => [
                'name' => config('app.name'),
                'email' => config('mail.from.address'),
                'phone' => config('app.phone', ''),
                'website' => config('app.url'),
            ],
        ];
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        $hoursText = $hours === 1 ? "1 hour" : "{$hours} hours";
        $minutesText = "{$remainingMinutes} minutes";

        return "{$hoursText} {$minutesText}";
    }

    private function formatFullAddress($location): string
    {
        $parts = array_filter([
            $location->address,
            $location->city,
            $location->postcode,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get meeting details formatted for email
     */
    private function getMeetingDetails(): array
    {
        $details = [
            'format' => ucfirst($this->consultation->format),
            'format_display' => $this->getFormatDisplay($this->consultation->format),
            'date' => $this->consultation->scheduled_at->format('l, F j, Y'),
            'time' => $this->consultation->scheduled_at->format('g:i A'),
            'duration' => $this->consultation->duration_minutes . ' minutes',
            'duration_display' => $this->formatDuration($this->consultation->duration_minutes),
            'timezone' => $this->consultation->scheduled_at->timezone->getName(),
            'is_video' => $this->consultation->format === 'video',
            'is_phone' => $this->consultation->format === 'phone',
            'is_in_person' => in_array($this->consultation->format, ['in_person', 'site_visit']),
        ];

        switch ($this->consultation->format) {
            case 'video':
                $details['meeting_link'] = $this->consultation->meeting_link;
                $details['meeting_id'] = $this->consultation->meeting_id;
                $details['access_code'] = $this->consultation->meeting_access_code;
                $details['platform'] = $this->consultation->meeting_platform ?? 'Video Call';
                $details['host_key'] = $this->consultation->host_key;
                $details['join_instructions'] = 'Join the meeting 5 minutes early to test your audio and video';
                $details['technical_support'] = '+44 20 3890 2370';
                break;

            case 'phone':
                $details['dial_in_number'] = $this->consultation->dial_in_number;
                $details['access_code'] = $this->consultation->meeting_access_code;
                $details['call_instructions'] = 'We will call you at the scheduled time using the phone number provided';
                $details['phone_number'] = $this->consultation->client_phone;
                break;

            case 'in_person':
            case 'site_visit':
                $details['location'] = $this->consultation->meeting_location;
                $details['instructions'] = $this->consultation->meeting_instructions;
                $details['is_site_visit'] = $this->consultation->format === 'site_visit';

                if ($this->consultation->format === 'site_visit') {
                    $details['site_requirements'] = 'Please ensure site access is available';
                    $details['additional_time'] = 'Allow extra time for site assessment';
                }
                break;
        }

        return $details;
    }

    private function getFormatDisplay(string $format): string
    {
        return match($format) {
            'video' => 'Video Call',
            'phone' => 'Phone Call',
            'in_person' => 'In-Person Meeting',
            'site_visit' => 'Site Visit',
            default => ucfirst($format),
        };
    }

    private function getItemsToBring(): array
    {
        $items = [];

        switch ($this->consultation->type) {
            case 'design':
                $items = [
                    'Inspiration photos or ideas',
                    'Color preference examples',
                    'Venue photos (if available)',
                    'Any specific requirements list',
                ];
                break;

            case 'technical':
                $items = [
                    'Site measurements or plans',
                    'Access requirements information',
                    'Equipment or setup constraints list',
                    'Safety or compliance requirements',
                ];
                break;

            case 'planning':
                $items = [
                    'Event timeline or schedule',
                    'Guest count and venue details',
                    'Coordination requirements',
                    'Special needs or preferences',
                ];
                break;

            default: // pre_booking and others
                $items = [
                    'List of requirements and preferences',
                    'Budget range information',
                    'Timeline expectations',
                    'Any questions about our services',
                ];
        }

        // Add format-specific items
        if (in_array($this->consultation->format, ['in_person', 'site_visit'])) {
            $items[] = 'Photo ID';
        }

        return $items;
    }

    private function getWhatToExpect(): array
    {
        $expectations = [
            'Duration: ' . $this->formatDuration($this->consultation->duration_minutes),
            'Review your specific requirements and goals',
            'Discuss timeline and logistics',
            'Get expert advice and recommendations',
            'Receive a detailed proposal (if applicable)',
        ];

        switch ($this->consultation->type) {
            case 'design':
                $expectations[] = 'Explore design options and creative ideas';
                $expectations[] = 'Review color schemes and styling choices';
                break;

            case 'technical':
                $expectations[] = 'Technical feasibility assessment';
                $expectations[] = 'Equipment and setup planning';
                break;

            case 'planning':
                $expectations[] = 'Coordination and timeline planning';
                $expectations[] = 'Logistics and delivery arrangements';
                break;

            case 'pre_booking':
                $expectations[] = 'Service overview and options';
                $expectations[] = 'Pricing and package details';
                break;
        }

        if ($this->booking) {
            $expectations[] = 'Finalize details for your upcoming booking';
        }

        return $expectations;
    }
}
