<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class DigitalProductDeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $emailData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $productName = $this->emailData['product']['name'];

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            subject: "Your Digital Product is Ready: {$productName}",
            tags: ['digital-product', 'order-fulfillment'],
            metadata: [
                'order_id' => $this->emailData['order']['id'],
                'product_id' => $this->emailData['product']['id'],
                'user_id' => $this->emailData['user']['email'],
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.digital-product-delivered',
            text: 'emails.digital-product-delivered-text',
            with: [
                'user' => $this->emailData['user'],
                'order' => $this->emailData['order'],
                'product' => $this->emailData['product'],
                'downloadAccesses' => $this->emailData['download_accesses'],
                'licenseKeys' => $this->emailData['license_keys'] ?? [],
                'files' => $this->emailData['files'] ?? [],
                'supportUrl' => config('app.support_url', route('support.contact')),
                'dashboardUrl' => route('my-digital-products.index'),
            ],
        );
    }
}
