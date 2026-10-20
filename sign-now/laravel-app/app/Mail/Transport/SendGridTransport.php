<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through the SendGrid v3 HTTP API using Laravel's HTTP client
 * (cURL under the hood) — no SDK dependency. Select it with
 * MAIL_MAILER=sendgrid and set SENDGRID_API_KEY.
 */
class SendGridTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    protected function doSend(SentMessage $message): void
    {
        $apiKey = (string) config('services.sendgrid.api_key');
        if ($apiKey === '') {
            throw new TransportException('SendGrid API key is not configured (SENDGRID_API_KEY).');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? null;
        if (!$from) {
            throw new TransportException('SendGrid: message has no from address.');
        }

        $personalization = ['to' => $this->addresses($email->getTo())];
        if ($email->getCc()) {
            $personalization['cc'] = $this->addresses($email->getCc());
        }
        if ($email->getBcc()) {
            $personalization['bcc'] = $this->addresses($email->getBcc());
        }

        $payload = [
            'personalizations' => [$personalization],
            'from' => $this->address($from),
            'subject' => (string) $email->getSubject(),
            'content' => [],
        ];

        if ($email->getReplyTo()) {
            $payload['reply_to'] = $this->address($email->getReplyTo()[0]);
        }

        // SendGrid requires text/plain before text/html.
        if ($email->getTextBody() !== null && $email->getTextBody() !== '') {
            $payload['content'][] = ['type' => 'text/plain', 'value' => (string) $email->getTextBody()];
        }
        if ($email->getHtmlBody() !== null && $email->getHtmlBody() !== '') {
            $payload['content'][] = ['type' => 'text/html', 'value' => (string) $email->getHtmlBody()];
        }
        if (empty($payload['content'])) {
            $payload['content'][] = ['type' => 'text/plain', 'value' => ' '];
        }

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'content' => base64_encode($attachment->getBody()),
                'type' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
                'filename' => $attachment->getFilename() ?: 'attachment',
                'disposition' => 'attachment',
            ];
        }
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            throw new TransportException(
                "SendGrid API error ({$response->status()}): " . $response->body()
            );
        }
    }

    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $a) => $this->address($a), $addresses);
    }

    private function address(Address $address): array
    {
        $entry = ['email' => $address->getAddress()];
        if ($address->getName() !== '') {
            $entry['name'] = $address->getName();
        }

        return $entry;
    }

    public function __toString(): string
    {
        return 'sendgrid';
    }
}
