<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class BaseSystemMail extends Mailable
{
    use SerializesModels;

    protected array $emailData;
    protected string $templateName;

    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: "emails.{$this->templateName}",
            with: $this->emailData,
        );
    }

    abstract protected function getSubject(): string;

    protected function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }
}
