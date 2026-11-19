<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $htmlContent,
        public string $subjectLine,
        public string $senderEmail,
        public string $senderName,
        public string $unsubscribeUrl,
        public string $footerText,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->senderEmail, $this->senderName),
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $footer = '<div class="unsubscribe-footer"><p>'.e($this->footerText).'</p><p><a href="'.e($this->unsubscribeUrl).'">Unsubscribe</a></p></div>';
        $html = preg_replace('/<\/body\s*>/i', $footer.'</body>', $this->htmlContent, 1, $count);
        if ($count === 0) {
            $html = $this->htmlContent.$footer;
        }

        return new Content(htmlString: $html);
    }
}
