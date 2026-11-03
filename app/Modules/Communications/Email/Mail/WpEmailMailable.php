<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Mail;

use App\Modules\Communications\Email\Models\EmailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WpEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly EmailMessage $emailMessage,
    ) {
        if ($this->emailMessage->body_text) {
            $this->withSymfonyMessage(function ($message) {
                $message->text($this->emailMessage->body_text);
            });
        }
    }

    public function envelope(): Envelope
    {
        $profile = $this->emailMessage->senderProfile;

        $envelopeArgs = [
            'subject' => $this->emailMessage->subject,
        ];

        if ($profile) {
            $envelopeArgs['from'] = new \Illuminate\Mail\Mailables\Address(
                $profile->email,
                $profile->name,
            );
            if ($profile->reply_to) {
                $envelopeArgs['replyTo'] = [new \Illuminate\Mail\Mailables\Address($profile->reply_to)];
            }
        }

        $headers = [];
        if ($this->emailMessage->message_id) {
            $headers['Message-ID'] = $this->emailMessage->message_id;
        }
        if ($this->emailMessage->in_reply_to) {
            $headers['In-Reply-To'] = $this->emailMessage->in_reply_to;
        }
        if ($this->emailMessage->references) {
            $headers['References'] = $this->emailMessage->references;
        }

        if (! empty($headers)) {
            $envelopeArgs['using'] = [
                function ($message) use ($headers) {
                    $idHeaders = ['Message-ID', 'In-Reply-To', 'References'];
                    foreach ($headers as $key => $val) {
                        if (in_array($key, $idHeaders, true)) {
                            $cleanId = trim($val, " <>\t\n\r\0\x0B");
                            $message->getHeaders()->addIdHeader($key, $cleanId);
                        } else {
                            $message->getHeaders()->addTextHeader($key, $val);
                        }
                    }
                },
            ];
        }

        return new Envelope(...$envelopeArgs);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->emailMessage->body_html,
        );
    }

    public function getTextBody(): ?string
    {
        return $this->emailMessage->body_text;
    }

    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->emailMessage->attachments as $attachment) {
            if ($attachment->content_b64) {
                $attachments[] = Attachment::fromData(
                    fn () => base64_decode($attachment->content_b64),
                    $attachment->filename,
                )->withMime($attachment->mime_type);
            } elseif ($attachment->storage_path && $attachment->storage_disk) {
                $disk = \Illuminate\Support\Facades\Storage::disk($attachment->storage_disk);
                if ($disk->exists($attachment->storage_path)) {
                    $attachments[] = Attachment::fromData(
                        fn () => $disk->get($attachment->storage_path),
                        $attachment->filename,
                    )->withMime($attachment->mime_type);
                }
            }
        }

        return $attachments;
    }
}
