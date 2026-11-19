<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Throwable;

class FailureAlert extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $source,
        public Throwable $exception,
        public array $context,
        public int $suppressed,
        public int $throttleMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] Background job failed: '.class_basename($this->source),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.failure-alert');
    }
}
