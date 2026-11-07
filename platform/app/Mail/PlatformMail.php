<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Crypt;

/** Bütün platforma e-mailləri: şablon `lang/{az,en,ru}/mail.php`-dan, istifadəçinin dilində. */
class PlatformMail extends Mailable
{
    public function __construct(public string $event, public array $data, public string $lang, public string $userName)
    {
        $this->locale($lang);
        if (isset($this->data['url_enc'])) {
            $this->data['url'] = Crypt::decryptString($this->data['url_enc']);
            unset($this->data['url_enc']);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __("mail.{$this->event}.subject", $this->vars(), $this->lang));
    }

    public function content(): Content
    {
        $lines = __("mail.{$this->event}.lines", $this->vars(), $this->lang);

        return new Content(view: 'mail.generic', text: 'mail.generic-text', with: [
            'lines' => is_array($lines) ? $lines : [],
            'greeting' => __('mail.greeting', ['name' => $this->userName], $this->lang),
            'actionUrl' => $this->data['url'] ?? null,
            'actionLabel' => isset($this->data['url']) ? __("mail.{$this->event}.action", $this->vars(), $this->lang) : null,
            'footer' => __('mail.footer', [], $this->lang),
            'lang' => $this->lang,
        ]);
    }

    public function attachments(): array
    {
        if (empty($this->data['ics'])) {
            return [];
        }

        return [Attachment::fromData(fn () => $this->data['ics'], 'event.ics')->withMime('text/calendar; charset=utf-8; method='.($this->data['ics_method'] ?? 'REQUEST'))];
    }

    private function vars(): array
    {
        return array_filter($this->data, fn ($v) => is_scalar($v));
    }
}
