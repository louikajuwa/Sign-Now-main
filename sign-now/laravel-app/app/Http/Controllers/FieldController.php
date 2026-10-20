<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Field;
use App\Models\Recipient;
use App\Models\Submission;
use App\Models\SubmissionValue;
use App\Models\User;
use App\Services\SigningTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldController extends Controller
{
    public function __construct(
        private SigningTokenService $tokenService,
    ) {}

    public function edit(string $uuid)
    {
        $doc = Document::findAccessible($uuid);

        return view('documents.builder', [
            'docId' => $doc->uuid,
            'docTitle' => $doc->title ?: $doc->original_filename,
            'isTemplate' => $doc->is_template,
            'isApiTemplate' => $doc->is_api_template,
            'webhookUrl' => $doc->webhook_url,
        ]);
    }

    public function getFields(Request $request, string $uuid): JsonResponse
    {
        $accessType = 'admin';
        $recipientId = 0;
        $recipientEmail = '';

        $t = $request->query('t');
        $token = is_string($t) ? trim($t) : '';
        if ($token !== '') {
            $access = $this->tokenService->validateForDocument($token, $uuid);
            if (!$access) {
                return response()->json(['result' => 'error', 'message' => 'Unauthorized'], 403);
            }
            $this->tokenService->refreshTtl($token);
            $accessType = 'recipient';
            $recipientId = (int) ($access['recipient_id'] ?? 0);
            $recipientEmail = strtolower($access['email'] ?? '');
            $doc = Document::where('uuid', $uuid)->firstOrFail();
        } elseif (!auth()->check()) {
            return response()->json(['result' => 'error', 'message' => 'Unauthorized'], 403);
        } else {
            $doc = Document::findAccessible($uuid);
        }

        $fields = Field::where('document_id', $doc->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $fieldData = $fields->map(function ($f) {
            return [
                'id' => $f->field_uid,
                'name' => $f->name,
                'page' => $f->page,
                'type' => $f->type,
                'x' => $f->pos_x,
                'y' => $f->pos_y,
                'w' => $f->width,
                'h' => $f->height,
                'label' => $f->label,
                'required' => $f->required,
                'assigned_to' => $f->assigned_to,
                'font_size' => $f->font_size,
                'customer_key' => $f->customer_key,
                'customer_value' => $f->customer_value,
                'default_value' => $f->default_value,
            ];
        });

        // Customer data for auto-filling mapped fields (customer_key).
        $customerData = null;
        $customersData = [];
        $adminCustomerData = null;

        if ($accessType === 'recipient') {
            $customerData = Customer::forDocumentRecipient($doc, $recipientEmail)?->toCustomerData();
            if ($customerData) {
                // The fill JS matches fields via customerData.email === assigned_to,
                // so the linked recipient's email wins over the record's.
                $customerData['email'] = $recipientEmail;
            }
        } else {
            $recipients = Recipient::with('customer')
                ->where('document_id', $doc->id)
                ->get();

            foreach ($recipients as $r) {
                $customer = $r->customer
                    ?: ($r->email !== '__admin__' ? Customer::forDocumentRecipient($doc, $r->email) : null);
                if (!$customer) {
                    continue;
                }

                if ($r->email === '__admin__') {
                    $adminCustomerData = $customer->toCustomerData();
                    continue;
                }

                $data = $customer->toCustomerData();
                $data['email'] = strtolower($r->email);
                $customersData[] = $data;
                $customerData ??= $data;
            }

            // Fall back to the document creator's signing profile, and always
            // carry their saved signature so finalize can pre-sign admin fields.
            $owner = ($doc->user_id ? User::find($doc->user_id) : null) ?: auth()->user();
            if ($owner) {
                $adminCustomerData ??= $owner->toSigningData();
                $adminCustomerData['signature'] = $owner->signature;
            }
        }

        // Values already submitted by other recipients — shown locked in the UI.
        $signedValues = [];
        if ($accessType === 'recipient') {
            $fieldIdToUid = $fields->pluck('field_uid', 'id')->toArray();

            $subs = Submission::where('document_id', $doc->id)
                ->whereNotNull('recipient_id')
                ->where('recipient_id', '!=', $recipientId)
                ->orderBy('id')
                ->get();

            foreach ($subs as $sub) {
                $svRows = SubmissionValue::where('submission_id', $sub->id)->get();
                foreach ($svRows as $sv) {
                    $val = (string) $sv->value;
                    if ($val === '') continue;
                    $fuid = $fieldIdToUid[$sv->field_id] ?? null;
                    if ($fuid !== null) {
                        $signedValues[$fuid] = $val;
                    }
                }
            }
        }

        return response()->json([
            'result' => 'success',
            'render_scale' => $doc->render_scale,
            'fields' => $fieldData,
            'doc_title' => $doc->title ?: $doc->original_filename,
            'customer_data' => $customerData,
            'customers_data' => $customersData,
            'admin_customer_data' => $adminCustomerData,
            'signed_values' => $signedValues,
            'recipient_id' => $recipientId ?: null,
            'recipient_email' => $recipientEmail ?: null,
        ]);
    }

    public function saveFields(Request $request): JsonResponse
    {
        $docUuid = $request->input('doc_id');
        $doc = Document::findAccessible(is_string($docUuid) ? $docUuid : '');

        if ($doc->status === 'completed') {
            return response()->json([
                'result' => 'error',
                'message' => 'Completed documents cannot be edited.',
            ], 422);
        }

        $request->validate([
            'fields' => 'present|array',
            'fields.*' => 'array',
            'fields.*.id' => 'required|string|max:64',
            'fields.*.type' => 'required|in:text,date,sign,initials',
            'fields.*.x' => 'required|numeric',
            'fields.*.y' => 'required|numeric',
            'fields.*.w' => 'required|numeric',
            'fields.*.h' => 'required|numeric',
            'fields.*.page' => 'nullable|integer|min:1',
            'render_scale' => 'nullable|numeric|gt:0',
        ]);

        $incoming = array_values($request->input('fields', []));
        $renderScale = (float) $request->input('render_scale', 1.5);

        if ($renderScale !== $doc->render_scale) {
            $doc->update(['render_scale' => $renderScale]);
        }

        $existingFields = Field::where('document_id', $doc->id)
            ->get()
            ->keyBy('field_uid');

        $incomingUids = [];

        DB::transaction(function () use ($incoming, $doc, $existingFields, &$incomingUids) {
        foreach ($incoming as $i => $f) {
            $uid = (string) $f['id'];
            $incomingUids[] = $uid;

            $data = [
                'document_id' => $doc->id,
                'field_uid' => $uid,
                'page' => (int) ($f['page'] ?? 1),
                'type' => $f['type'] ?? 'text',
                'pos_x' => (float) ($f['x'] ?? 0),
                'pos_y' => (float) ($f['y'] ?? 0),
                'width' => (float) ($f['w'] ?? 100),
                'height' => (float) ($f['h'] ?? 24),
                'label' => $f['label'] ?? '',
                'name' => $f['name'] ?? '',
                'required' => !empty($f['required']),
                'assigned_to' => (isset($f['assigned_to']) && is_string($f['assigned_to']) && trim($f['assigned_to']) !== '')
                    ? strtolower(trim($f['assigned_to']))
                    : null,
                'font_size' => !empty($f['font_size']) ? (float) $f['font_size'] : null,
                'customer_key' => ($f['customer_key'] ?? '') !== '' ? $f['customer_key'] : null,
                'customer_value' => ($f['customer_value'] ?? '') !== '' ? $f['customer_value'] : null,
                'default_value' => ($f['default_value'] ?? '') !== '' ? $f['default_value'] : null,
                'sort_order' => $i,
            ];

            if ($existingFields->has($uid)) {
                $existingFields[$uid]->update($data);
            } else {
                Field::create($data);
            }
        }

        // Never delete fields that already hold submitted values.
        $toDelete = $existingFields->keys()->diff($incomingUids);
        if ($toDelete->isNotEmpty()) {
            $withValues = SubmissionValue::whereIn('field_id', $existingFields->only($toDelete->all())->pluck('id'))
                ->pluck('field_id');
            Field::where('document_id', $doc->id)
                ->whereIn('field_uid', $toDelete)
                ->whereNotIn('id', $withValues)
                ->delete();
        }
        });

        if ($doc->status === 'draft' && count($incoming) > 0) {
            $doc->update(['status' => 'ready', 'last_activity_at' => now()]);
        } else {
            $doc->update(['last_activity_at' => now()]);
        }

        AuditLog::log('fields_saved', $doc, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['field_count' => count($incoming)],
        ]);

        return response()->json([
            'result' => 'success',
            'count' => count($incoming),
        ]);
    }
}
