<?php

namespace App\Http\Controllers;

use App\Jobs\DocumentCompletedWebhook;
use App\Models\AdminField;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Field;
use App\Models\Recipient;
use App\Models\Submission;
use App\Models\SubmissionValue;
use App\Services\CompletionEmailService;
use App\Services\PdfStampingService;
use App\Services\SigningTokenService;
use App\Services\SocketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PdfController extends Controller
{
    public function __construct(
        private PdfStampingService $stampingService,
        private SigningTokenService $tokenService,
        private SocketNotificationService $socketService,
        private CompletionEmailService $completionService,
    ) {}

    public function fill(string $uuid)
    {
        $doc = Document::findAccessible($uuid);

        return view('documents.fill', [
            'docId' => $doc->uuid,
            'docTitle' => $doc->title ?: $doc->original_filename,
        ]);
    }

    public function finalize(string $uuid)
    {
        $doc = Document::findAccessible($uuid);

        return view('documents.finalize', [
            'docId' => $doc->uuid,
            'docTitle' => $doc->title ?: $doc->original_filename,
        ]);
    }

    public function recipientValues(Request $request, string $uuid): JsonResponse
    {
        $t = $request->query('t');
        $token = is_string($t) ? trim($t) : '';
        if ($token !== '') {
            if (!$this->tokenService->validateForDocument($token, $uuid)) {
                return response()->json(['result' => 'error', 'message' => 'Unauthorized'], 403);
            }
            $this->tokenService->refreshTtl($token);
            $doc = Document::where('uuid', $uuid)->firstOrFail();
        } elseif (!auth()->check()) {
            return response()->json(['result' => 'error', 'message' => 'Unauthorized'], 403);
        } else {
            $doc = Document::findAccessible($uuid);
        }

        $fieldRows = Field::where('document_id', $doc->id)->get();
        $fieldIdToUid = $fieldRows->pluck('field_uid', 'id')->toArray();

        $subs = Submission::where('document_id', $doc->id)
            ->whereNotNull('recipient_id')
            ->orderBy('id')
            ->get();

        $merged = [];
        foreach ($subs as $sub) {
            $svRows = SubmissionValue::where('submission_id', $sub->id)->get();
            foreach ($svRows as $sv) {
                $val = (string) $sv->value;
                if ($val === '') continue;
                $uid = $fieldIdToUid[$sv->field_id] ?? null;
                if ($uid !== null) {
                    $merged[$uid] = $val;
                }
            }
        }

        return response()->json(['result' => 'success', 'values' => $merged]);
    }

    public function generate(Request $request, string $uuid): JsonResponse
    {
        $t = $request->query('t');
        $token = is_string($t) ? trim($t) : '';
        $b = $request->query('base');
        $baseParam = is_string($b) ? trim($b) : '';

        $accessType = 'admin';
        $recipientId = 0;
        $recipientEmail = '';

        if ($token !== '') {
            $access = $this->tokenService->validateForDocument($token, $uuid);
            if (!$access) {
                return response()->json(['result' => 'error', 'message' => 'Unauthorized'], 403);
            }
            $this->tokenService->refreshTtl($token);
            $accessType = 'recipient';
            $recipientId = (int) ($access['recipient_id'] ?? 0);
            $recipientEmail = strtolower((string) ($access['email'] ?? ''));
            $doc = Document::where('uuid', $uuid)->firstOrFail();
        } elseif (!auth()->check()) {
            return response()->json(['result' => 'error', 'message' => 'Unauthorized'], 403);
        } else {
            $doc = Document::findAccessible($uuid);
        }

        // Only an authenticated owner/admin can finalize (admin stamps/overrides).
        $isFinalize = ($baseParam === 'recipient' && $accessType === 'admin');

        $values = $request->input('values', []);
        $adminFieldsList = $request->input('admin_fields', []);
        $recipientOverrides = $request->input('recipient_overrides', []);
        $fieldOverrides = $request->input('field_overrides', []);

        if (!is_array($values) || !is_array($adminFieldsList) || !is_array($recipientOverrides) || !is_array($fieldOverrides)) {
            return response()->json(['result' => 'error', 'message' => 'Invalid payload.'], 422);
        }
        foreach ($values as $k => $v) {
            if (!is_string($k) && !is_int($k) || (!is_scalar($v) && $v !== null)) {
                return response()->json(['result' => 'error', 'message' => 'Invalid field values.'], 422);
            }
        }
        // field_overrides (fill.js) share the recipient_overrides shape.
        $recipientOverrides = array_merge($recipientOverrides, $fieldOverrides);

        $fieldRows = Field::where('document_id', $doc->id)->get();
        if ($fieldRows->isEmpty()) {
            return response()->json(['result' => 'error', 'message' => 'No fields defined'], 400);
        }

        $assignMap = [];
        if ($accessType === 'recipient') {
            foreach ($fieldRows as $f) {
                $assignMap[$f->field_uid] = $f->assigned_to ?? '';
            }
            foreach (array_keys($values) as $uid) {
                $owner = $assignMap[$uid] ?? null;
                if ($owner !== null && $owner !== '' && $owner !== $recipientEmail) {
                    unset($values[$uid]);
                }
            }
        }

        $existingRecipientValues = [];
        if ($isFinalize || $accessType === 'recipient') {
            $subQuery = Submission::where('document_id', $doc->id)->whereNotNull('recipient_id');

            if ($accessType === 'recipient' && !$isFinalize && $recipientId > 0) {
                $subQuery->where('recipient_id', '!=', $recipientId);
            }

            $recipientSubs = $subQuery->orderBy('id')->get();

            $fieldIdToUid = $fieldRows->pluck('field_uid', 'id')->toArray();

            foreach ($recipientSubs as $sub) {
                $svRows = SubmissionValue::where('submission_id', $sub->id)->get();
                foreach ($svRows as $sv) {
                    $val = (string) $sv->value;
                    if ($val === '') continue;

                    $uid = $fieldIdToUid[$sv->field_id] ?? null;
                    if ($uid !== null) {
                        $existingRecipientValues[$uid] = $val;
                    }
                }
            }
        }

        $missing = [];
        foreach ($fieldRows as $f) {
            if (!$f->required) continue;

            if (isset($existingRecipientValues[$f->field_uid])) {
                if ($accessType !== 'recipient') continue;
                $fieldOwner = $assignMap[$f->field_uid] ?? '';
                if ($fieldOwner === '') continue;
            }

            if ($accessType === 'recipient') {
                $fieldOwner = $assignMap[$f->field_uid] ?? '';
                if ($fieldOwner !== '' && $fieldOwner !== $recipientEmail) continue;
            }

            $val = trim((string) ($values[$f->field_uid] ?? ''));
            if ($val === '') {
                $missing[] = $f->name ?: $f->field_uid;
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'result' => 'error',
                'message' => 'Missing required field(s)',
                'missing' => $missing,
            ], 400);
        }

        $fieldByUid = $fieldRows->keyBy('field_uid');

        try {
            DB::beginTransaction();

            $submission = Submission::create([
                'document_id' => $doc->id,
                'user_id' => auth()->id(),
                'recipient_id' => $recipientId > 0 ? $recipientId : null,
                'output_path' => '',
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            ]);

            foreach ($values as $fieldUid => $val) {
                if (!isset($fieldByUid[$fieldUid])) continue;
                SubmissionValue::create([
                    'submission_id' => $submission->id,
                    'field_id' => $fieldByUid[$fieldUid]->id,
                    'value' => (string) $val,
                ]);
            }

            if ($recipientId > 0) {
                $recipient = Recipient::where('id', $recipientId)
                    ->where('document_id', $doc->id)
                    ->first();

                if ($recipient) {
                    $recipient->update([
                        'status' => 'signed',
                        'signed_at' => now(),
                        'submission_id' => $submission->id,
                    ]);
                }
            }

            $previousStatus = $doc->getOriginal('status');

            $totalRecipients = Recipient::where('document_id', $doc->id)
                ->where('email', '!=', '__admin__')
                ->count();

            if ($totalRecipients > 0) {
                $unsignedCount = Recipient::where('document_id', $doc->id)
                    ->where('status', '!=', 'signed')
                    ->where('email', '!=', '__admin__')
                    ->count();
                $doc->status = ($unsignedCount === 0) ? 'completed' : 'filled';
            } else {
                $doc->status = 'filled';
            }

            $doc->last_activity_at = now();
            $doc->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error("PdfController: generate failed for {$uuid}", ['error' => $e->getMessage()]);
            return response()->json([
                'result' => 'error',
                'message' => 'Failed to save submission. Please try again.',
            ], 500);
        }

        $cachedOut = Storage::path("docsign/{$uuid}/output_latest.pdf");
        if (file_exists($cachedOut)) {
            @unlink($cachedOut);
        }

        // Position overrides (finalize's recipient_overrides, fill.js's
        // field_overrides) are admin-only; admin stamps only on finalize.
        if ($accessType === 'admin' && ($isFinalize || !empty($recipientOverrides))) {
            DB::transaction(function () use ($doc, $adminFieldsList, $recipientOverrides, $fieldByUid, $isFinalize) {
                if ($isFinalize) {
                    AdminField::where('document_id', $doc->id)->delete();
                }
                foreach ($isFinalize ? $adminFieldsList : [] as $af) {
                    if (!is_array($af)) continue;
                    $val = trim((string) ($af['value'] ?? ''));
                    if ($val === '') continue;
                    AdminField::create([
                        'document_id' => $doc->id,
                        'page' => (int) ($af['page'] ?? 1),
                        'type' => $af['type'] ?? 'text',
                        'pos_x' => (float) ($af['x'] ?? 0),
                        'pos_y' => (float) ($af['y'] ?? 0),
                        'width' => (float) ($af['w'] ?? 100),
                        'height' => (float) ($af['h'] ?? 30),
                        'value' => $val,
                        'created_by' => auth()->id(),
                    ]);
                }

                foreach ($recipientOverrides as $ov) {
                    if (!is_array($ov)) continue;
                    $uid = trim((string) ($ov['field_uid'] ?? ''));
                    if ($uid === '' || !isset($fieldByUid[$uid])) continue;
                    $update = [
                        'pos_x' => (float) ($ov['x'] ?? 0),
                        'pos_y' => (float) ($ov['y'] ?? 0),
                        'width' => (float) ($ov['w'] ?? 100),
                        'height' => (float) ($ov['h'] ?? 30),
                    ];
                    if (isset($ov['page']) && is_numeric($ov['page']) && (int) $ov['page'] >= 1) {
                        $update['page'] = (int) $ov['page'];
                    }
                    $fieldByUid[$uid]->update($update);
                }
            });

            if ($isFinalize) {
                $this->socketService->documentFinalized($uuid, $doc->title ?: $doc->original_filename);
            }
        }

        // Completion email: fires once when the last recipient signs, and on
        // every admin finalize (so the attached PDF carries the admin stamps).
        $justCompleted = ($doc->status === 'completed' && $previousStatus !== 'completed');
        if ($justCompleted || $isFinalize) {
            try {
                $this->completionService->sendToSignedRecipients($doc, $isFinalize ? 'finalize' : 'auto');
            } catch (\Throwable $e) {
                \Log::error("PdfController: completion email failed for {$uuid}", ['error' => $e->getMessage()]);
            }
        }

        if ($justCompleted) {
            DocumentCompletedWebhook::dispatch($doc);
        }

        if ($recipientId > 0) {
            $this->socketService->recipientSigned(
                $uuid,
                $recipientEmail,
                $doc->title ?: $doc->original_filename
            );

            if ($token !== '') {
                $this->tokenService->revoke($token);
            }
        }

        $this->socketService->documentStatusChanged($uuid, $doc->status);

        AuditLog::log('document_signed', $doc, [
            'performed_by' => $recipientId > 0 ? $recipientEmail : (string) auth()->id(),
            'performed_by_type' => $recipientId > 0 ? 'recipient' : 'admin',
            'meta' => ['recipient_id' => $recipientId ?: null, 'field_count' => count($values)],
        ]);

        return response()->json([
            'result' => 'success',
            'view_url' => route('documents.view', $uuid),
        ]);
    }
}
