<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class PaymentReminderMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'payment.reminder';

    private Booking $booking;
    private string $reminderType;
    private ?Carbon $originalDueDate;
    private ?int $daysOverdue;
    private bool $isFinalNotice;

    public function __construct(
        Booking $booking,
        string $reminderType = 'upcoming',
        ?Carbon $originalDueDate = null,
        bool $isFinalNotice = false
    ) {
        $this->booking = $booking;
        $this->reminderType = $reminderType;
        $this->originalDueDate = $originalDueDate;
        $this->isFinalNotice = $isFinalNotice;
        $this->daysOverdue = $this->calculateDaysOverdue();

        // Prepare email data using the booking
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        $urgency = $this->getUrgencyText();
        $paymentType = $this->getPaymentTypeText();

        return "{$urgency} {$paymentType} Payment - {$this->booking->service->name} (#{$this->booking->booking_reference})";
    }

    private function prepareEmailData(): array
    {
        return [
            'booking' => [
                'id' => $this->booking->id,
                'reference' => $this->booking->booking_reference,
                'status' => ucfirst($this->booking->status),
                'scheduled_at' => $this->booking->scheduled_at->format('l, F j, Y'),
                'scheduled_time' => $this->booking->scheduled_at->format('g:i A'),
                'duration_display' => $this->formatDuration($this->booking->duration_minutes),
                'days_until_service' => $this->getDaysUntilService(),
                'is_upcoming' => $this->booking->scheduled_at > now(),
            ],

            'payment_details' => [
                'total_amount' => $this->booking->total_amount,
                'formatted_total_amount' => $this->formatPrice($this->booking->total_amount),
                'amount_paid' => $this->getAmountPaid(),
                'formatted_amount_paid' => $this->formatPrice($this->getAmountPaid()),
                'amount_due' => $this->getAmountDue(),
                'formatted_amount_due' => $this->formatPrice($this->getAmountDue()),
                'payment_status' => $this->booking->payment_status,
                'payment_status_display' => ucfirst(str_replace('_', ' ', $this->booking->payment_status)),
                'is_deposit_only' => $this->isDepositOnly(),
                'is_final_payment' => $this->isFinalPayment(),
                'payment_type_description' => $this->getPaymentTypeDescription(),
            ],

            'reminder_details' => [
                'type' => $this->reminderType,
                'type_display' => $this->getReminderTypeDisplay(),
                'urgency_level' => $this->getUrgencyLevel(),
                'is_overdue' => $this->isOverdue(),
                'days_overdue' => $this->daysOverdue,
                'is_final_notice' => $this->isFinalNotice,
                'due_date' => $this->getDueDate(),
                'formatted_due_date' => $this->getFormattedDueDate(),
                'time_until_due' => $this->getTimeUntilDue(),
                'grace_period_end' => $this->getGracePeriodEnd(),
            ],

            'service' => [
                'name' => $this->booking->service->name,
                'description' => $this->booking->service->short_description ?? $this->booking->service->description,
                'category' => $this->booking->service->category,
            ],

            'client' => [
                'name' => $this->booking->client_name,
                'email' => $this->booking->client_email,
                'phone' => $this->booking->client_phone,
            ],

            'payment_breakdown' => $this->getPaymentBreakdown(),
            'payment_options' => $this->getPaymentOptions(),
            'payment_methods' => $this->getPaymentMethods(),
            'late_payment_policy' => $this->getLatePaymentPolicy(),

            'consequences' => $this->getConsequences(),
            'next_steps' => $this->getNextSteps(),
            'assistance_options' => $this->getAssistanceOptions(),

            'invoice_details' => [
                'invoice_number' => $this->generateInvoiceNumber(),
                'invoice_date' => now()->format('F j, Y'),
                'due_date' => $this->getFormattedDueDate(),
                'payment_terms' => $this->getPaymentTerms(),
                'currency' => 'GBP',
                'tax_information' => $this->getTaxInformation(),
            ],

            'contact_info' => [
                'accounts_email' => config('mail.accounts_email', config('mail.from.address')),
                'support_phone' => config('app.support_phone'),
                'payment_support_hours' => 'Monday-Friday, 9AM-6PM GMT',
                'emergency_contact' => config('app.emergency_contact'),
            ],

            'security_reminders' => $this->getSecurityReminders(),
            'frequently_asked_questions' => $this->getFrequentlyAskedQuestions(),
        ];
    }

    /**
     * Calculate days overdue
     */
    private function calculateDaysOverdue(): ?int
    {
        if (!$this->originalDueDate || $this->originalDueDate->isFuture()) {
            return null;
        }

        return now()->diffInDays($this->originalDueDate);
    }

    /**
     * Get urgency text for subject
     */
    private function getUrgencyText(): string
    {
        return match($this->reminderType) {
            'final_notice' => 'FINAL NOTICE',
            'overdue' => 'OVERDUE',
            'due_today' => 'DUE TODAY',
            'due_tomorrow' => 'Payment Due Tomorrow',
            'due_soon' => 'Payment Reminder',
            'upcoming' => 'Payment Reminder',
            default => 'Payment Notice'
        };
    }

    /**
     * Get payment type text
     */
    private function getPaymentTypeText(): string
    {
        if ($this->isDepositOnly()) {
            return 'Deposit';
        } elseif ($this->isFinalPayment()) {
            return 'Final';
        } else {
            return 'Outstanding';
        }
    }

    /**
     * Get reminder type display
     */
    private function getReminderTypeDisplay(): string
    {
        return match($this->reminderType) {
            'final_notice' => 'Final Payment Notice',
            'overdue' => 'Overdue Payment Reminder',
            'due_today' => 'Payment Due Today',
            'due_tomorrow' => 'Payment Due Tomorrow',
            'due_soon' => 'Upcoming Payment Due',
            'upcoming' => 'Payment Reminder',
            default => 'Payment Notification'
        };
    }

    /**
     * Get urgency level
     */
    private function getUrgencyLevel(): string
    {
        return match($this->reminderType) {
            'final_notice' => 'critical',
            'overdue' => 'urgent',
            'due_today' => 'high',
            'due_tomorrow' => 'medium',
            default => 'normal'
        };
    }

    /**
     * Check if payment is overdue
     */
    private function isOverdue(): bool
    {
        return $this->daysOverdue !== null && $this->daysOverdue > 0;
    }

    /**
     * Get days until service
     */
    private function getDaysUntilService(): int
    {
        return max(0, now()->diffInDays($this->booking->scheduled_at, false));
    }

    /**
     * Get amount paid so far
     */
    private function getAmountPaid(): int
    {
        return $this->booking->payments()
            ->whereIn('status', ['completed', 'succeeded'])
            ->sum('amount');
    }

    /**
     * Get amount still due
     */
    private function getAmountDue(): int
    {
        return max(0, $this->booking->total_amount - $this->getAmountPaid());
    }

    /**
     * Check if this is deposit only
     */
    private function isDepositOnly(): bool
    {
        return $this->booking->payment_status === 'pending' && $this->booking->deposit_amount > 0;
    }

    /**
     * Check if this is final payment
     */
    private function isFinalPayment(): bool
    {
        return $this->booking->payment_status === 'deposit_paid';
    }

    /**
     * Get payment type description
     */
    private function getPaymentTypeDescription(): string
    {
        if ($this->isDepositOnly()) {
            return "This is your booking deposit to secure your {$this->booking->service->name} service.";
        } elseif ($this->isFinalPayment()) {
            return "This is your final payment to complete your booking.";
        } else {
            return "This is the outstanding balance for your booking.";
        }
    }

    /**
     * Get due date
     */
    private function getDueDate(): Carbon
    {
        if ($this->originalDueDate) {
            return $this->originalDueDate;
        }

        // Calculate due date based on booking type
        if ($this->isDepositOnly()) {
            return now()->addDays(3); // 3 days for deposit
        } else {
            return $this->booking->scheduled_at->subDays(7); // 1 week before service
        }
    }

    /**
     * Get formatted due date
     */
    private function getFormattedDueDate(): string
    {
        return $this->getDueDate()->format('l, F j, Y');
    }

    /**
     * Get time until due
     */
    private function getTimeUntilDue(): string
    {
        $dueDate = $this->getDueDate();

        if ($dueDate->isPast()) {
            return $dueDate->diffForHumans() . ' ago';
        } else {
            return $dueDate->diffForHumans();
        }
    }

    /**
     * Get grace period end
     */
    private function getGracePeriodEnd(): ?string
    {
        if (!$this->isOverdue()) {
            return null;
        }

        $gracePeriodDays = 3; // 3-day grace period
        $gracePeriodEnd = $this->getDueDate()->addDays($gracePeriodDays);

        if ($gracePeriodEnd->isFuture()) {
            return $gracePeriodEnd->format('l, F j, Y');
        }

        return null;
    }

    /**
     * Get payment breakdown
     */
    private function getPaymentBreakdown(): array
    {
        $breakdown = [
            'service_cost' => [
                'label' => $this->booking->service->name,
                'amount' => $this->booking->base_price,
                'formatted_amount' => $this->formatPrice($this->booking->base_price),
            ]
        ];

        // Add add-ons
        foreach ($this->booking->bookingAddOns as $addOn) {
            $breakdown['addons'][] = [
                'label' => $addOn->serviceAddOn->name . ' (×' . $addOn->quantity . ')',
                'amount' => $addOn->total_price,
                'formatted_amount' => $this->formatPrice($addOn->total_price),
            ];
        }

        // Add location charges
        if ($this->booking->location_surcharge > 0) {
            $breakdown['location_charge'] = [
                'label' => 'Location Surcharge',
                'amount' => $this->booking->location_surcharge,
                'formatted_amount' => $this->formatPrice($this->booking->location_surcharge),
            ];
        }

        // Add totals
        $breakdown['subtotal'] = [
            'label' => 'Subtotal',
            'amount' => $this->booking->total_amount,
            'formatted_amount' => $this->formatPrice($this->booking->total_amount),
        ];

        if ($this->booking->deposit_amount > 0) {
            $breakdown['deposit'] = [
                'label' => 'Deposit Required',
                'amount' => $this->booking->deposit_amount,
                'formatted_amount' => $this->formatPrice($this->booking->deposit_amount),
            ];

            $breakdown['remaining'] = [
                'label' => 'Remaining Balance',
                'amount' => $this->booking->remaining_amount,
                'formatted_amount' => $this->formatPrice($this->booking->remaining_amount),
            ];
        }

        return $breakdown;
    }

    /**
     * Get payment options
     */
    private function getPaymentOptions(): array
    {
        return [
            'online' => [
                'title' => 'Pay Online (Recommended)',
                'description' => 'Secure payment through your customer account',
                'methods' => ['Credit/Debit Card', 'Bank Transfer', 'Digital Wallet'],
                'processing_time' => 'Instant',
                'fees' => 'No additional fees',
                'link' => route('bookings.payment', $this->booking->id),
            ],
            'bank_transfer' => [
                'title' => 'Direct Bank Transfer',
                'description' => 'Transfer directly to our business account',
                'processing_time' => '1-3 business days',
                'fees' => 'Bank charges may apply',
                'account_details' => $this->getBankAccountDetails(),
            ],
            'phone' => [
                'title' => 'Pay by Phone',
                'description' => 'Call our accounts team to pay securely',
                'phone' => config('app.payments_phone', config('app.support_phone')),
                'hours' => 'Monday-Friday, 9AM-6PM GMT',
                'processing_time' => 'Instant',
            ],
        ];
    }

    /**
     * Get payment methods
     */
    private function getPaymentMethods(): array
    {
        return [
            'Visa, Mastercard, American Express',
            'UK Bank Transfer (Faster Payments)',
            'PayPal and Apple Pay',
            'Google Pay and Samsung Pay',
            'Bank debit (Direct Debit setup available)',
        ];
    }

    /**
     * Get late payment policy
     */
    private function getLatePaymentPolicy(): array
    {
        return [
            'grace_period' => '3 days grace period after due date',
            'late_fee' => $this->isOverdue() && $this->daysOverdue > 3 ?
                '£25 late payment fee may apply' : 'No late fees within grace period',
            'service_risk' => $this->getDaysUntilService() <= 7 ?
                'Service may be at risk if payment not received' :
                'Payment required to secure booking',
            'cancellation_policy' => 'Bookings may be cancelled for non-payment',
        ];
    }

    /**
     * Get consequences of non-payment
     */
    private function getConsequences(): array
    {
        $consequences = [];

        if ($this->isDepositOnly()) {
            $consequences[] = 'Booking will not be confirmed without deposit payment';
            $consequences[] = 'Preferred date and time may become unavailable';
        } else {
            $consequences[] = 'Service delivery may be suspended';
            $consequences[] = 'Additional late payment charges may apply';
        }

        if ($this->getDaysUntilService() <= 7) {
            $consequences[] = 'Last-minute cancellation may result in full charge';
        }

        if ($this->isFinalNotice) {
            $consequences[] = 'This is your final notice before booking cancellation';
            $consequences[] = 'Credit reporting may be initiated for significant overdue amounts';
        }

        return $consequences;
    }

    /**
     * Get next steps
     */
    private function getNextSteps(): array
    {
        $steps = [];

        if ($this->isOverdue()) {
            $steps[] = 'Make payment immediately to avoid service interruption';
            $steps[] = 'Contact us if you\'re experiencing payment difficulties';
        } else {
            $steps[] = 'Review the payment details below';
            $steps[] = 'Choose your preferred payment method';
            $steps[] = 'Complete payment by the due date';
        }

        $steps[] = 'You\'ll receive confirmation once payment is processed';
        $steps[] = 'Contact our support team with any questions';

        return $steps;
    }

    /**
     * Get assistance options
     */
    private function getAssistanceOptions(): array
    {
        return [
            'payment_plans' => [
                'title' => 'Payment Plan Options',
                'description' => 'We may be able to arrange a payment plan for larger amounts',
                'contact' => 'Contact our accounts team to discuss options',
            ],
            'financial_difficulty' => [
                'title' => 'Financial Difficulty',
                'description' => 'If you\'re experiencing financial hardship, please let us know',
                'contact' => 'We\'ll work with you to find a solution',
            ],
            'technical_support' => [
                'title' => 'Payment Technical Issues',
                'description' => 'Having trouble with online payment?',
                'contact' => 'Our technical team can help you complete your payment',
            ],
        ];
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        return 'INV-' . $this->booking->booking_reference . '-' . now()->format('Ymd');
    }

    /**
     * Get payment terms
     */
    private function getPaymentTerms(): string
    {
        if ($this->isDepositOnly()) {
            return 'Deposit due within 3 days of booking confirmation';
        } elseif ($this->isFinalPayment()) {
            return 'Final payment due 7 days before service date';
        } else {
            return 'Payment due as specified in booking terms';
        }
    }

    /**
     * Get tax information
     */
    private function getTaxInformation(): array
    {
        return [
            'vat_registered' => true,
            'vat_number' => config('app.vat_number', 'GB123456789'),
            'vat_rate' => 20,
            'vat_included' => true,
            'note' => 'All prices include VAT where applicable',
        ];
    }

    /**
     * Get bank account details
     */
    private function getBankAccountDetails(): array
    {
        return [
            'account_name' => config('app.company_name', 'Company Name'),
            'account_number' => config('app.bank_account_number', '12345678'),
            'sort_code' => config('app.bank_sort_code', '12-34-56'),
            'bank_name' => config('app.bank_name', 'Bank Name'),
            'reference' => $this->booking->booking_reference,
            'note' => 'Please include your booking reference in the payment description',
        ];
    }

    /**
     * Get security reminders
     */
    private function getSecurityReminders(): array
    {
        return [
            'We will never ask for your full card details via email',
            'Always use the secure payment links provided',
            'Verify the website URL before entering payment information',
            'We will never request payment via unusual methods',
            'Contact us directly if you receive suspicious payment requests',
        ];
    }

    /**
     * Get frequently asked questions
     */
    private function getFrequentlyAskedQuestions(): array
    {
        return [
            [
                'question' => 'Why do I need to pay a deposit?',
                'answer' => 'The deposit secures your booking date and confirms your commitment to the service. It\'s deducted from your final bill.',
            ],
            [
                'question' => 'When is the final payment due?',
                'answer' => 'Final payment is typically due 7 days before your service date to allow time for final preparations.',
            ],
            [
                'question' => 'What happens if I pay late?',
                'answer' => 'We offer a 3-day grace period. After that, late fees may apply and your service could be at risk.',
            ],
            [
                'question' => 'Can I change my payment method?',
                'answer' => 'Yes, you can update your payment method in your account or contact our support team for assistance.',
            ],
            [
                'question' => 'Is my payment information secure?',
                'answer' => 'Yes, we use industry-standard encryption and secure payment processors to protect your information.',
            ],
            [
                'question' => 'What if I need to cancel after paying?',
                'answer' => 'Cancellation terms depend on timing. Please review your booking confirmation or contact us for specific details.',
            ],
        ];
    }
}
