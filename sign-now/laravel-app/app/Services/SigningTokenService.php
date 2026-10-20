<?php

namespace App\Services;

use App\Models\Recipient;
use Illuminate\Support\Facades\Redis;

class SigningTokenService
{
    private const TTL = 3600;
    private const PREFIX = 'docsign_access:';

    public function grantAccess(Recipient $recipient): void
    {
        $key = self::PREFIX . $recipient->token;
        $data = [
            'email' => $recipient->email,
            'document_id' => $recipient->document_id,
            'document_uuid' => $recipient->document->uuid,
            'recipient_id' => $recipient->id,
            'created_at' => time(),
        ];

        Redis::setex($key, self::TTL, json_encode($data));
    }

    public function validate(string $token): ?array
    {
        $key = self::PREFIX . $token;
        $raw = Redis::get($key);

        if (!$raw) {
            return null;
        }

        return json_decode($raw, true);
    }

    /**
     * Validate a token for a specific document: the Redis grant must name this
     * document AND the recipient row must exist, belong to the document and
     * still own this token (a rotated token is rejected).
     */
    public function validateForDocument(?string $token, string $uuid): ?array
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        $access = $this->validate($token);
        if (!$access || ($access['document_uuid'] ?? '') !== $uuid) {
            return null;
        }

        $ok = Recipient::where('id', (int) ($access['recipient_id'] ?? 0))
            ->where('token', $token)
            ->whereHas('document', fn ($q) => $q->where('uuid', $uuid))
            ->exists();

        return $ok ? $access : null;
    }

    public function refreshTtl(string $token): void
    {
        Redis::expire(self::PREFIX . $token, self::TTL);
    }

    public function revoke(string $token): void
    {
        Redis::del(self::PREFIX . $token);
    }
}
