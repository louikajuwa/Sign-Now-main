<?php

namespace App\Http\Controllers;

use App\Mail\SigningInvite;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Recipient;
use App\Services\CompletionEmailService;
use App\Services\SigningTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RecipientController extends Controller
{
    public function __construct(
        private SigningTokenService $tokenService,
        private CompletionEmailService $completionService,
    ) {}

    public function getRecipients(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $recipients = Recipient::where('document_id', $doc->id)->get();

        return response()->json([
            'result' => 'success',
            'recipients' => $recipients->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->email,
                'name' => $r->name,
                'status' => $r->status,
                'sent_at' => $r->sent_at?->toIso8601String(),
                'opened_at' => $r->opened_at?->toIso8601String(),
                'signed_at' => $r->signed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function saveRecipients(Request $request, string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $emails = $request->input('recipients', []);
        if (!is_array($emails)) {
            $emails = [];
        }

        $normalized = collect($emails)
            ->filter(fn ($e) => is_string($e))
            ->map(fn ($e) => strtolower(trim($e)))
            ->filter(fn ($e) => $e !== '' && ($e === '__admin__' || filter_var($e, FILTER_VALIDATE_EMAIL)))
            ->unique()
            ->values();

        $existing = Recipient::where('document_id', $doc->id)->get()->keyBy('email');

        $toRemove = $existing->keys()->diff($normalized);
        foreach ($toRemove as $email) {
            $r = $existing[$email];
            if ($r->status === 'signed') {
                continue; // never delete a recipient who has already signed
            }
            $this->tokenService->revoke($r->token);
            $r->delete();
        }

        foreach ($normalized as $email) {
            if (!$existing->has($email)) {
                Recipient::create([
                    'document_id' => $doc->id,
                    'email' => $email,
                    'token' => Recipient::generateToken(),
                ]);
            }
        }

        $updated = Recipient::where('document_id', $doc->id)->get();

        return response()->json([
            'result' => 'success',
            'recipients' => $updated->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->email,
                'name' => $r->name,
                'status' => $r->status,
                'sent_at' => $r->sent_at?->toIso8601String(),
                'signed_at' => $r->signed_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Add a customer as a recipient (or the __admin__ slot) and link their
     * record via recipients.customer_id so their data auto-fills mapped
     * fields when signing. URL/payload shape is fixed by public/js/builder.js.
     */
    public function addCustomerRecipient(Request $request, string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);

        $email = strtolower(trim((string) $request->input('email')));
        $customerData = $request->input('customer_data', []);

        if ($email !== '__admin__' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['result' => 'error', 'message' => 'Invalid email address.'], 422);
        }

        $customer = Customer::resolveRef($customerData['UserID'] ?? null);
        if (!$customer && !empty($customerData['email'])) {
            $customer = Customer::whereRaw('LOWER(email) = ?', [strtolower(trim($customerData['email']))])->first();
        }

        if (!$customer) {
            return response()->json(['result' => 'error', 'message' => 'Customer not found.'], 404);
        }

        $recipient = Recipient::where('document_id', $doc->id)->where('email', $email)->first();
        if (!$recipient) {
            $recipient = Recipient::create([
                'document_id' => $doc->id,
                'email' => $email,
                'name' => $email === '__admin__' ? '' : $customer->full_name,
                'token' => Recipient::generateToken(),
                'customer_id' => $customer->id,
            ]);
        } else {
            $recipient->update(['customer_id' => $customer->id]);
        }

        AuditLog::log('customer_recipient_added', $doc, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['customer_id' => $customer->id, 'recipient_email' => $email],
        ]);

        $updated = Recipient::where('document_id', $doc->id)->get();

        return response()->json([
            'result' => 'success',
            'recipients' => $updated->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->email,
                'name' => $r->name,
                'status' => $r->status,
                'sent_at' => $r->sent_at?->toIso8601String(),
                'opened_at' => $r->opened_at?->toIso8601String(),
                'signed_at' => $r->signed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function sendInvite(string $uuid, int $recipientId): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $recipient = Recipient::where('id', $recipientId)
            ->where('document_id', $doc->id)
            ->firstOrFail();

        if ($recipient->email === '__admin__') {
            return response()->json(['result' => 'error', 'message' => 'Cannot send invite to admin sentinel']);
        }

        if ($recipient->status === 'signed') {
            return response()->json(['result' => 'error', 'message' => 'Recipient has already signed.'], 422);
        }

        $signingUrl = route('signing.validation', $recipient->token);

        Mail::to($recipient->email)->queue(new SigningInvite($doc, $recipient, $signingUrl));

        $recipient->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        AuditLog::log('invite_sent', $doc, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['recipient_email' => $recipient->email],
        ]);

        return response()->json(['result' => 'success']);
    }

    public function sendAll(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $recipients = Recipient::where('document_id', $doc->id)
            ->where('email', '!=', '__admin__')
            ->whereIn('status', ['pending'])
            ->get();

        $sent = 0;
        foreach ($recipients as $r) {
            $signingUrl = route('signing.validation', $r->token);

            Mail::to($r->email)->queue(new SigningInvite($doc, $r, $signingUrl));

            $r->update(['status' => 'sent', 'sent_at' => now()]);
            $sent++;
        }

        return response()->json(['result' => 'success', 'sent' => $sent]);
    }

    /**
     * Manually send the completion email (signed PDF attached) to everyone
     * who has signed — the admin-triggered counterpart of the automatic send.
     */
    public function sendComplete(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);

        $sent = $this->completionService->sendToSignedRecipients($doc, 'manual');

        if ($sent === 0) {
            return response()->json([
                'result' => 'error',
                'message' => 'No signed recipients to notify yet.',
            ], 400);
        }

        return response()->json([
            'result' => 'success',
            'message' => "Completion email sent to {$sent} recipient" . ($sent !== 1 ? 's' : '') . '.',
        ]);
    }

    public function resendAll(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $recipients = Recipient::where('document_id', $doc->id)
            ->where('email', '!=', '__admin__')
            ->where('status', '!=', 'signed')
            ->get();

        $sent = 0;
        foreach ($recipients as $r) {
            $this->tokenService->revoke($r->token);
            $r->update(['token' => Recipient::generateToken()]);

            $signingUrl = route('signing.validation', $r->token);

            Mail::to($r->email)->queue(new SigningInvite($doc, $r, $signingUrl));

            $r->update(['status' => 'sent', 'sent_at' => now()]);
            $sent++;
        }

        return response()->json(['result' => 'success', 'sent' => $sent]);
    }
}
