<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a client's eSIM (Merchant V2) by email: the scannable activation QR
 * (embedded inline so it works even when remote images are blocked), the manual
 * LPA code, an iOS one-tap link, and install steps. Branded with the merchant's
 * business name. Queued — never sent inline in the request cycle.
 */
class ClientEsimMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $steps
     */
    public function __construct(
        public string $clientName,
        public string $brand,
        public string $planName,
        public ?string $lpa,
        public ?string $qrCodeUrl,
        public ?string $universalLink,
        public array $steps,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->brand.': your eSIM is ready to install');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-esim');
    }
}
