<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class ConsultationReminderMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'consultation.reminder';

    private ConsultationBooking $consultation;
    private ?Booking $mainBooking;
    private string $reminderType;
    private int $hoursUntil;

    public function __construct(
        ConsultationBooking $consultation,
        string $reminderType = '24h',
        ?Booking $mainBooking = null
    ) {
        $this->consultation = $consultation;
        $this->mainBooking = $mainBooking ?? $consultation->mainBooking;
        $this->reminderType = $reminderType;
        $this->hoursUntil = $this->calculateHoursUntil();

        // Prepare email data using the consultation
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        $timeframe = $this->getTimeframeText();
        $consultationType = $this->consultation->type === 'pre_booking' ? 'Pre-Booking Consultation' :
            ucfirst(str_replace('_', ' ', $this->consultation->type));

        return "Consultation Reminder - {$consultationType} {$timeframe} (#{$this->consultation->consultation_reference})";
    }

    private function prepareEmailData(): array
    {
        return [
            'consultation' => [
                'id' => $this->consultation->id,
                'reference' => $this->consultation->consultation_reference,
                'type' => $this->consultation->type,
                'type_display' => $this->getConsultationTypeDisplay(),
                'format' => $this->consultation->format,
                'format_display' => $this->getFormatDisplay(),
                'status' => ucfirst($this->consultation->status),
                'scheduled_at' => $this->consultation->scheduled_at->format('l, F j, Y'),
                'scheduled_time' => $this->consultation->scheduled_at->format('g:i A'),
                'ends_at' => $this->consultation->ends_at->format('g:i A'),
                'duration_minutes' => $this->consultation->duration_minutes,
                'duration_display' => $this->formatDuration($this->consultation->duration_minutes),
                'timezone' => $this->consultation->scheduled_at->timezoneName ?? 'UTC',
            ],

            'reminder_details' => [
                'type' => $this->reminderType,
                'hours_until' => $this->hoursUntil,
                'timeframe_text' => $this->getTimeframeText(),
                'is_same_day' => $this->consultation->scheduled_at->isToday(),
                'is_tomorrow' => $this->consultation->scheduled_at->isTomorrow(),
                'time_until' => $this->consultation->scheduled_at->diffForHumans(),
                'countdown' => $this->getCountdownText(),
                'urgency_level' => $this->getUrgencyLevel(),
            ],

            'service' => [
                'name' => $this->consultation->service->name,
                'description' => $this->consultation->service->description,
                'category' => $this->consultation->service->category,
                'consultation_duration_minutes' => $this->consultation->service->consultation_duration_minutes,
                'preparation_notes' => $this->consultation->service->preparation_notes,
            ],

            'main_booking' => $this->mainBooking ? [
                'reference' => $this->mainBooking->booking_reference,
                'service_name' => $this->mainBooking->service->name,
                'scheduled_at' => $this->mainBooking->scheduled_at->format('l, F j, Y \a\t g:i A'),
                'total_amount' => $this->formatPrice($this->mainBooking->total_amount),
                'status' => ucfirst($this->mainBooking->status),
                'special_requirements' => $this->mainBooking->special_requirements,
            ] : null,

            'client' => [
                'name' => $this->consultation->client_name,
                'email' => $this->consultation->client_email,
                'phone' => $this->consultation->client_phone,
            ],

            'meeting_details' => $this->getMeetingDetails(),

            'preparation' => [
                'instructions' => $this->consultation->preparation_instructions,
                'questions' => $this->consultation->consultation_questions,
                'checklist' => $this->getPreparationChecklist(),
                'documents_needed' => $this->getDocumentsNeeded(),
                'information_to_gather' => $this->getInformationToGather(),
                'technical_requirements' => $this->getTechnicalRequirements(),
            ],

            'agenda' => $this->getConsultationAgenda(),
            'expectations' => $this->getConsultationExpectations(),
            'outcomes' => $this->getExpectedOutcomes(),

            'contact_info' => [
                'consultant_name' => $this->consultation->consultant_name,
                'consultant_email' => $this->consultation->consultant_email,
                'consultant_phone' => $this->consultation->consultant_phone,
                'support_email' => config('mail.from.address'),
                'emergency_contact' => config('app.emergency_contact'),
            ],

            'rescheduling' => [
                'can_reschedule' => $this->consultation->canBeRescheduled(),
                'reschedule_deadline' => $this->getRescheduleDeadline(),
                'reschedule_instructions' => $this->getRescheduleInstructions(),
                'cancellation_policy' => $this->consultation->service->cancellation_policy,
            ],

            'follow_up' => [
                'what_happens_next' => $this->getWhatHappensNext(),
                'timeline_after_consultation' => $this->getTimelineAfterConsultation(),
                'booking_process' => $this->getBookingProcessNext(),
            ],

            'helpful_tips' => $this->getHelpfulTips(),
            'frequently_asked_questions' => $this->getFrequentlyAskedQuestions(),
        ];
    }

    /**
     * Calculate hours until consultation
     */
    private function calculateHoursUntil(): int
    {
        return (int) now()->diffInHours($this->consultation->scheduled_at);
    }

    /**
     * Get timeframe text for subject line
     */
    private function getTimeframeText(): string
    {
        return match($this->reminderType) {
            '24h' => 'Tomorrow',
            '2h' => 'in 2 Hours',
            '30m' => 'in 30 Minutes',
            '1h' => 'in 1 Hour',
            default => 'Soon'
        };
    }

    /**
     * Get consultation type display name
     */
    private function getConsultationTypeDisplay(): string
    {
        return match($this->consultation->type) {
            'pre_booking' => 'Pre-Booking Consultation',
            'design' => 'Design Consultation',
            'planning' => 'Planning Session',
            'technical' => 'Technical Review',
            'follow_up' => 'Follow-Up Meeting',
            default => ucfirst(str_replace('_', ' ', $this->consultation->type))
        };
    }

    /**
     * Get format display name
     */
    private function getFormatDisplay(): string
    {
        return match($this->consultation->format) {
            'phone' => 'Phone Call',
            'video' => 'Video Call',
            'in_person' => 'In-Person Meeting',
            'site_visit' => 'Site Visit',
            default => ucfirst(str_replace('_', ' ', $this->consultation->format))
        };
    }

    /**
     * Get countdown text
     */
    private function getCountdownText(): string
    {
        if ($this->hoursUntil < 1) {
            $minutesUntil = now()->diffInMinutes($this->consultation->scheduled_at);
            return "{$minutesUntil} minutes";
        } elseif ($this->hoursUntil < 24) {
            return "{$this->hoursUntil} hours";
        } else {
            $daysUntil = now()->diffInDays($this->consultation->scheduled_at);
            return "{$daysUntil} days";
        }
    }

    /**
     * Get urgency level
     */
    private function getUrgencyLevel(): string
    {
        if ($this->hoursUntil <= 1) {
            return 'immediate';
        } elseif ($this->hoursUntil <= 24) {
            return 'urgent';
        } else {
            return 'normal';
        }
    }

    /**
     * Get meeting details based on format
     */
    private function getMeetingDetails(): array
    {
        $details = [
            'format' => $this->consultation->format,
            'format_display' => $this->getFormatDisplay(),
        ];

        switch ($this->consultation->format) {
            case 'video':
                $details = array_merge($details, [
                    'platform' => $this->consultation->meeting_platform ?? 'Zoom',
                    'meeting_link' => $this->consultation->meeting_link,
                    'meeting_id' => $this->consultation->meeting_id,
                    'access_code' => $this->consultation->meeting_access_code,
                    'dial_in_number' => $this->consultation->dial_in_number,
                    'join_instructions' => $this->getVideoJoinInstructions(),
                    'technical_support' => $this->getVideoTechnicalSupport(),
                ]);
                break;

            case 'phone':
                $details = array_merge($details, [
                    'phone_number' => $this->consultation->consultant_phone,
                    'call_instructions' => $this->getPhoneCallInstructions(),
                    'backup_number' => config('app.support_phone'),
                ]);
                break;

            case 'in_person':
                $details = array_merge($details, [
                    'location' => $this->consultation->meeting_location,
                    'address' => $this->consultation->meeting_location,
                    'parking_info' => $this->consultation->meeting_instructions['parking'] ?? 'Street parking available',
                    'access_instructions' => $this->consultation->meeting_instructions['access'] ?? null,
                    'what_to_bring' => $this->consultation->meeting_instructions['bring'] ?? [],
                ]);
                break;

            case 'site_visit':
                $details = array_merge($details, [
                    'visit_location' => $this->consultation->meeting_location,
                    'arrival_time' => $this->consultation->scheduled_at->subMinutes(15)->format('g:i A'),
                    'duration_estimate' => $this->formatDuration($this->consultation->duration_minutes + 30),
                    'what_to_prepare' => $this->getSiteVisitPreparation(),
                    'access_requirements' => $this->consultation->meeting_instructions['access'] ?? null,
                ]);
                break;
        }

        return $details;
    }

    /**
     * Get preparation checklist
     */
    private function getPreparationChecklist(): array
    {
        $checklist = [];

        // General preparation
        $checklist[] = 'Review your service requirements and preferences';
        $checklist[] = 'Prepare any questions you have about the service';

        // Format-specific preparation
        if ($this->consultation->format === 'video') {
            $checklist[] = 'Test your camera and microphone';
            $checklist[] = 'Ensure stable internet connection';
            $checklist[] = 'Find a quiet, well-lit space for the call';
        } elseif ($this->consultation->format === 'phone') {
            $checklist[] = 'Ensure your phone is charged';
            $checklist[] = 'Find a quiet space for the call';
        } elseif ($this->consultation->format === 'site_visit') {
            $checklist[] = 'Ensure site access is available';
            $checklist[] = 'Clear the area for easier assessment';
            $checklist[] = 'Gather any relevant measurements or plans';
        }

        // Service-specific preparation
        if ($this->mainBooking) {
            $checklist[] = 'Review your main booking details';
            if ($this->mainBooking->special_requirements) {
                $checklist[] = 'Prepare to discuss your special requirements';
            }
        }

        // Type-specific preparation
        if ($this->consultation->type === 'design') {
            $checklist[] = 'Gather inspiration images or ideas';
            $checklist[] = 'Think about color preferences and themes';
        } elseif ($this->consultation->type === 'technical') {
            $checklist[] = 'Prepare technical specifications or constraints';
            $checklist[] = 'List any equipment or setup concerns';
        }

        return $checklist;
    }

    /**
     * Get documents needed
     */
    private function getDocumentsNeeded(): array
    {
        $documents = [];

        if ($this->consultation->format === 'site_visit') {
            $documents[] = 'Site plans or layout diagrams (if available)';
            $documents[] = 'Any permits or access documentation';
        }

        if ($this->consultation->type === 'design') {
            $documents[] = 'Inspiration photos or mood boards';
            $documents[] = 'Event timeline or schedule';
        }

        if ($this->mainBooking && $this->mainBooking->special_requirements) {
            $documents[] = 'Details about special requirements or constraints';
        }

        return $documents;
    }

    /**
     * Get information to gather
     */
    private function getInformationToGather(): array
    {
        $info = [
            'Budget range and any cost considerations',
            'Timeline and key deadlines',
            'Guest count and venue capacity',
            'Style preferences and themes',
            'Any constraints or limitations',
        ];

        if ($this->consultation->type === 'site_visit') {
            $info[] = 'Venue access times and restrictions';
            $info[] = 'Electrical and utility locations';
            $info[] = 'Load-in and setup requirements';
        }

        return $info;
    }

    /**
     * Get technical requirements for video calls
     */
    private function getTechnicalRequirements(): array
    {
        if ($this->consultation->format !== 'video') {
            return [];
        }

        return [
            'Computer, tablet, or smartphone with camera',
            'Stable internet connection (minimum 1 Mbps)',
            'Quiet environment with good lighting',
            'Headphones or earbuds recommended',
            'Updated browser or app for the meeting platform',
        ];
    }

    /**
     * Get consultation agenda
     */
    private function getConsultationAgenda(): array
    {
        $agenda = [];

        switch ($this->consultation->type) {
            case 'pre_booking':
                $agenda = [
                    'Welcome and introductions (5 minutes)',
                    'Review your event requirements (10 minutes)',
                    'Discuss service options and possibilities (15 minutes)',
                    'Timeline and logistics planning (10 minutes)',
                    'Pricing and next steps (10 minutes)',
                    'Questions and answers (10 minutes)',
                ];
                break;

            case 'design':
                $agenda = [
                    'Welcome and design brief review (5 minutes)',
                    'Discuss your vision and inspiration (15 minutes)',
                    'Review space and practical considerations (10 minutes)',
                    'Explore design options and concepts (20 minutes)',
                    'Finalize design direction (5 minutes)',
                    'Next steps and timeline (5 minutes)',
                ];
                break;

            case 'technical':
                $agenda = [
                    'Technical requirements review (10 minutes)',
                    'Site assessment and constraints (15 minutes)',
                    'Equipment and setup planning (15 minutes)',
                    'Timeline and coordination (10 minutes)',
                    'Risk assessment and contingencies (5 minutes)',
                    'Final technical specifications (5 minutes)',
                ];
                break;

            default:
                $agenda = [
                    'Welcome and agenda review (5 minutes)',
                    'Main discussion topics (40 minutes)',
                    'Questions and clarifications (10 minutes)',
                    'Summary and next steps (5 minutes)',
                ];
        }

        return $agenda;
    }

    /**
     * Get consultation expectations
     */
    private function getConsultationExpectations(): array
    {
        return [
            'Professional and friendly discussion about your needs',
            'Expert advice and recommendations',
            'Clear explanation of options and pricing',
            'No pressure sales approach',
            'Detailed follow-up with recommendations',
            'Transparent timeline and next steps',
        ];
    }

    /**
     * Get expected outcomes
     */
    private function getExpectedOutcomes(): array
    {
        $outcomes = [
            'Clear understanding of your requirements',
            'Detailed service recommendations',
            'Accurate pricing estimate',
            'Project timeline and milestones',
        ];

        if ($this->consultation->type === 'design') {
            $outcomes[] = 'Initial design concepts and ideas';
            $outcomes[] = 'Color schemes and style direction';
        } elseif ($this->consultation->type === 'technical') {
            $outcomes[] = 'Technical specifications and requirements';
            $outcomes[] = 'Setup and logistics plan';
        }

        $outcomes[] = 'Written summary and next steps';

        return $outcomes;
    }

    /**
     * Get reschedule deadline
     */
    private function getRescheduleDeadline(): ?string
    {
        if ($this->hoursUntil <= 2) {
            return 'Please call us immediately if you need to reschedule';
        } elseif ($this->hoursUntil <= 24) {
            return 'Please reschedule at least 2 hours in advance';
        } else {
            return 'Please reschedule at least 24 hours in advance';
        }
    }

    /**
     * Get reschedule instructions
     */
    private function getRescheduleInstructions(): array
    {
        return [
            'Log into your account to reschedule online',
            'Call our support team for immediate assistance',
            'Email us with your preferred alternative times',
            'Use the reschedule link in this email',
        ];
    }

    /**
     * Get what happens next
     */
    private function getWhatHappensNext(): array
    {
        return [
            'Detailed consultation summary within 24 hours',
            'Customized service proposal and pricing',
            'Timeline for your project',
            'Next steps to confirm your booking',
            'Any additional consultations if needed',
        ];
    }

    /**
     * Get timeline after consultation
     */
    private function getTimelineAfterConsultation(): array
    {
        return [
            'Immediate: Consultation summary and recommendations',
            '24 hours: Detailed proposal and pricing',
            '48 hours: Follow-up call to discuss proposal',
            '1 week: Booking confirmation deadline',
            'Ongoing: Project planning and coordination',
        ];
    }

    /**
     * Get booking process next steps
     */
    private function getBookingProcessNext(): array
    {
        $steps = [];

        if ($this->mainBooking) {
            $steps[] = 'Review consultation outcomes against your existing booking';
            $steps[] = 'Confirm any changes or additions to your service';
            $steps[] = 'Finalize timing and logistics';
        } else {
            $steps[] = 'Review our detailed proposal';
            $steps[] = 'Confirm your service selection';
            $steps[] = 'Schedule your event date';
            $steps[] = 'Complete booking and payment';
        }

        return $steps;
    }

    /**
     * Get helpful tips
     */
    private function getHelpfulTips(): array
    {
        $tips = [
            'Arrive a few minutes early to get settled',
            'Have a notepad ready for important points',
            'Be open about your budget and constraints',
            'Ask questions - no question is too small',
            'Take notes on recommendations and next steps',
        ];

        if ($this->consultation->format === 'video') {
            $tips[] = 'Test your technology 10 minutes before the call';
            $tips[] = 'Use headphones for better audio quality';
        }

        return $tips;
    }

    /**
     * Get frequently asked questions
     */
    private function getFrequentlyAskedQuestions(): array
    {
        return [
            [
                'question' => 'What if I need to reschedule?',
                'answer' => 'You can reschedule up to 2 hours before your consultation through your account or by calling us.',
            ],
            [
                'question' => 'How long will the consultation take?',
                'answer' => "Your consultation is scheduled for {$this->formatDuration($this->consultation->duration_minutes)}. We may run slightly over if needed to cover all your questions.",
            ],
            [
                'question' => 'What happens after the consultation?',
                'answer' => 'You\'ll receive a detailed summary and proposal within 24 hours, followed by a follow-up call to discuss next steps.',
            ],
            [
                'question' => 'Is there any cost for the consultation?',
                'answer' => 'This consultation is complimentary as part of our service process.',
            ],
            [
                'question' => 'Can I bring someone else to the consultation?',
                'answer' => 'Absolutely! You\'re welcome to include key decision-makers in the consultation.',
            ],
        ];
    }

    /**
     * Get video join instructions
     */
    private function getVideoJoinInstructions(): array
    {
        return [
            'Click the meeting link 5 minutes before the scheduled time',
            'Allow camera and microphone access when prompted',
            'Wait in the lobby - we\'ll admit you at the scheduled time',
            'If you have issues, call our support number for assistance',
        ];
    }

    /**
     * Get video technical support
     */
    private function getVideoTechnicalSupport(): array
    {
        return [
            'Test your setup at: ' . config('app.tech_test_url', 'https://zoom.us/test'),
            'Technical support: ' . config('app.support_phone', 'Available in account'),
            'Alternative dial-in available if video fails',
            'We can switch to phone call if needed',
        ];
    }

    /**
     * Get phone call instructions
     */
    private function getPhoneCallInstructions(): array
    {
        return [
            'We will call you at the scheduled time',
            'Please ensure your phone is available and charged',
            'If we miss each other, we\'ll try again in 5 minutes',
            'You can also call us directly if preferred',
        ];
    }

    /**
     * Get site visit preparation
     */
    private function getSiteVisitPreparation(): array
    {
        return [
            'Ensure clear access to all relevant areas',
            'Have any building plans or layouts available',
            'Clear pathways for easy movement around the space',
            'Identify key contact person who will be present',
            'Prepare list of any restrictions or requirements',
        ];
    }
}
