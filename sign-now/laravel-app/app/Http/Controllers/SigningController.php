<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Recipient;
use App\Services\SigningTokenService;

class SigningController extends Controller
{
    public function __construct(
        private SigningTokenService $tokenService,
    ) {}

    public function validation(string $token)
    {
        if (strlen($token) !== 64) {
            return view('signing.invalid');
        }

        $recipient = Recipient::where('token', $token)->first();
        if (!$recipient) {
            return view('signing.invalid');
        }

        $doc = $recipient->document;
        if (!$doc) {
            return view('signing.invalid');
        }

        if ($recipient->isExpired()) {
            return view('signing.expired', [
                'docTitle' => $doc->title ?: $doc->original_filename,
            ]);
        }

        if ($recipient->status === 'signed') {
            return view('signing.done', [
                'recipientEmail' => $recipient->email,
                'signedAt' => $recipient->signed_at,
                'docTitle' => $doc->title ?: $doc->original_filename,
            ]);
        }

        if ($recipient->status === 'sent') {
            $recipient->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }

        $this->tokenService->grantAccess($recipient);

        AuditLog::log('recipient_opened', $doc, [
            'performed_by' => $recipient->email,
            'performed_by_type' => 'recipient',
        ]);

        return redirect()->route('signing.sign', [
            'uuid' => $doc->uuid,
            't' => $token,
        ]);
    }

    public function signing(string $uuid)
    {
        $token = request()->query('t', '');
        $access = $this->tokenService->validate($token);

        if (!$access || ($access['document_uuid'] ?? '') !== $uuid) {
            return view('signing.unauthorized');
        }

        $recipient = Recipient::find($access['recipient_id']);
        $doc = $recipient?->document;

        if (!$doc) {
            return view('signing.invalid');
        }

        return view('signing.signing', [
            'docId' => $doc->uuid,
            'docTitle' => $doc->title ?: $doc->original_filename,
            'recipientId' => $recipient->id,
            'recipientEmail' => $recipient->email,
            'accessToken' => $token,
        ]);
    }

    public function done(string $uuid)
    {
        return view('signing.done', [
            'docTitle' => '',
            'recipientEmail' => '',
            'signedAt' => '',
        ]);
    }
}
