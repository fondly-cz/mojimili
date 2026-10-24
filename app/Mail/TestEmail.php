<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Zpráva z nastavení pro ověření odesílání. Posílá se dvakrát – přímo a přes
 * frontu: funkční SMTP s mrtvým queue workerem doručí přímou kopii a tváří se
 * zdravě, zatímco všechny ostatní e-maily z aplikace tiše neodcházejí.
 */
class TestEmail extends Mailable
{
    use SerializesModels;

    public function __construct(public bool $viaQueue = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->viaQueue
                ? 'Mojimili: testovací e-mail (přes frontu)'
                : 'Mojimili: testovací e-mail (přímo)',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.test');
    }
}
