<?php

namespace App\Mail;

use App\Models\Calculation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalculationConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Calculation $calculation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zákazník potvrdil kalkulaci #'.str_pad((string) $this->calculation->id, 6, '0', STR_PAD_LEFT),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.calculation-confirmed',
            with: [
                'calculation' => $this->calculation,
                'url' => route('calculations.show', $this->calculation),
            ],
        );
    }
}
