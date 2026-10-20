<?php

namespace App\Http\Controllers;

use App\Mail\SigningInvite;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Field;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApiTemplateController extends Controller
{
    public function toggle(string $uuid): JsonResponse
    {
        $doc = Document::where('uuid', $uuid)->where('is_template', true)->firstOrFail();

        if ($doc->is_api_template) {
            $doc->update(['is_api_template' => false]);

            return response()->json(['result' => 'success', 'is_api_template' => false]);
        }

        if (!$doc->api_key) {
            $doc->api_key = bin2hex(random_bytes(32));
        }

        $doc->is_api_template = true;
        $doc->save();

        $fields = $doc->fields->map(fn (Field $f) => [
            'name' => $f->name,
            'label' => $f->label,
            'type' => $f->type,
            'required' => (bool) $f->required,
        ]);

        return response()->json([
            'result' => 'success',
            'is_api_template' => true,
            'endpoint' => url('/api/templates/trigger'),
            'fields' => $fields,
        ]);
    }

    public function info(string $uuid): JsonResponse
    {
        $doc = Document::where('uuid', $uuid)
            ->where('is_api_template', true)
            ->firstOrFail();

        $fields = $doc->fields->map(function (Field $f) {
            return [
                'name' => $f->name,
                'label' => $f->label,
                'type' => $f->type,
                'required' => (bool) $f->required,
                'customer_key' => $f->customer_key,
            ];
        });

        return response()->json([
            'result' => 'success',
            'api_key' => $doc->api_key,
            'endpoint' => url('/api/templates/trigger'),
            'auth' => 'Send the api_key in the X-Api-Key header (or as an api_key body field).',
            'fields' => $fields,
            'sample_payload' => [
                'template_id' => $doc->uuid,
                'user_id' => '12345',
                'email' => 'customer@example.com',
                'name' => 'Jane Doe',
                'api_key' => $doc->api_key,
            ],
        ]);
    }

    /**
     * Set or clear the webhook URL notified when documents created from
     * this template are completed.
     */
    public function setWebhook(Request $request, string $uuid): JsonResponse
    {
        $doc = Document::where('uuid', $uuid)->where('is_template', true)->firstOrFail();

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:500'],
        ]);

        $doc->update(['webhook_url' => $data['webhook_url'] ?? null]);

        AuditLog::log('webhook_url_updated', $doc, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['webhook_url' => $doc->webhook_url],
        ]);

        return response()->json(['result' => 'success', 'webhook_url' => $doc->webhook_url]);
    }

    public function setMainNda(string $uuid): JsonResponse
    {
        $doc = Document::where('uuid', $uuid)
            ->where('is_api_template', true)
            ->firstOrFail();

        if ($doc->main_nda_api) {
            $doc->update(['main_nda_api' => false]);

            return response()->json(['result' => 'success', 'main_nda_api' => false]);
        }

        $adminSignField = $doc->fields()
            ->where('type', 'sign')
            ->where('assigned_to', '__admin__')
            ->first();

        if (!$adminSignField) {
            return response()->json([
                'result' => 'error',
                'message' => 'Main NDA requires at least one signature field assigned to admin.',
            ], 422);
        }

        Document::where('main_nda_api', true)->update(['main_nda_api' => false]);
        $doc->update(['main_nda_api' => true]);

        return response()->json(['result' => 'success', 'main_nda_api' => true]);
    }

    public function trigger(Request $request): JsonResponse
    {
        $isCustomerTrigger = $request->boolean('customer_trigger');

        if ($isCustomerTrigger) {
            $template = Document::where('main_nda_api', true)
                ->where('is_template', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'No main NDA template configured.',
                ], 404);
            }
        } else {
            $templateId = $request->input('template_id');
            if (!$templateId) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'template_id is required.',
                ], 422);
            }

            $template = Document::where('uuid', $templateId)
                ->where('is_api_template', true)
                ->where('is_template', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'Template not found or not API-enabled.',
                ], 404);
            }
        }

        $apiKey = $request->header('X-Api-Key') ?: (string) $request->input('api_key');
        if (!$apiKey || !$template->api_key || !hash_equals($template->api_key, $apiKey)) {
            return response()->json([
                'result' => 'error',
                'message' => 'Invalid or missing API key.',
            ], 401);
        }

        $rawUserId = $request->input('user_id');
        $userId = is_scalar($rawUserId) && trim((string) $rawUserId) !== '' ? trim((string) $rawUserId) : null;
        $rawEmail = $request->input('email');
        $rawName = $request->input('name', '');
        $recipientEmail = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
        $recipientName = is_string($rawName) ? trim($rawName) : '';

        if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'result' => 'error',
                'message' => 'Valid email is required.',
            ], 422);
        }

        $customer = $this->upsertCustomer($recipientEmail, $recipientName, $userId);

        if ($isCustomerTrigger) {
            $existing = Document::where('template_id', $template->id)
                ->where('is_template', false)
                ->whereHas('recipients', function ($q) use ($recipientEmail) {
                    $q->where('email', $recipientEmail);
                })
                ->first();

            if ($existing) {
                $recipient = $existing->recipients()->where('email', $recipientEmail)->first();

                if ($recipient && $recipient->status === 'signed') {
                    return response()->json([
                        'result' => 'already_signed',
                        'document_id' => $existing->uuid,
                        'message' => 'Customer has already signed this NDA.',
                    ]);
                }

                if ($recipient && !$recipient->isExpired()) {
                    return response()->json([
                        'result' => 'already_pending',
                        'document_id' => $existing->uuid,
                        'sign_url' => url("/signing/validate/{$recipient->token}"),
                        'message' => 'An active signing request already exists.',
                    ]);
                }
            }
        }

        $newUuid = Str::uuid()->toString();
        $srcDir = "docsign/{$template->uuid}";
        $dstDir = "docsign/{$newUuid}";

        if (!Storage::exists("{$srcDir}/original.pdf")) {
            return response()->json([
                'result' => 'error',
                'message' => 'Template source PDF is missing.',
            ], 422);
        }

        // user_id stays the template owner (replicate() carries it over).
        $newDoc = $template->replicate();
        $newDoc->uuid = $newUuid;
        $newDoc->is_template = false;
        $newDoc->is_api_template = false;
        $newDoc->api_key = null;
        $newDoc->main_nda_api = false;
        $newDoc->template_id = $template->id;
        $newDoc->status = 'ready';
        $newDoc->title = $template->title . ($recipientName ? " — {$recipientName}" : '');
        $newDoc->file_path = "{$dstDir}/original.pdf";
        $newDoc->save();

        Storage::makeDirectory($dstDir);
        Storage::copy("{$srcDir}/original.pdf", "{$dstDir}/original.pdf");

        $templateFields = Field::where('document_id', $template->id)->orderBy('sort_order')->get();

        foreach ($templateFields as $f) {
            // replicate() carries over the customer mapping columns
            // (customer_key, customer_value, default_value) automatically.
            $clone = $f->replicate();
            $clone->document_id = $newDoc->id;
            $clone->field_uid = 'f_' . Str::random(16);

            if ($f->assigned_to && $f->assigned_to !== '__admin__') {
                $clone->assigned_to = $recipientEmail;
            }

            $clone->save();
        }

        $recipient = new Recipient();
        $recipient->document_id = $newDoc->id;
        $recipient->email = $recipientEmail;
        $recipient->name = $recipientName;
        $recipient->token = $recipient->generateToken();
        $recipient->status = 'pending';
        $recipient->customer_id = $customer->id;
        $recipient->save();

        $signUrl = url("/signing/validate/{$recipient->token}");
        Mail::to($recipientEmail)->queue(new SigningInvite($newDoc, $recipient, $signUrl));

        $recipient->update(['status' => 'sent', 'sent_at' => now()]);

        AuditLog::log('api_template_triggered', $newDoc, [
            'performed_by' => $recipientEmail,
            'performed_by_type' => 'api',
            'meta' => [
                'template_uuid' => $template->uuid,
                'user_id' => $userId,
                'recipient_email' => $recipientEmail,
            ],
        ]);

        return response()->json([
            'result' => 'success',
            'document_id' => $newUuid,
            'recipient_id' => $recipient->id,
            'sign_url' => $signUrl,
            'view_url' => url("/documents/{$newUuid}/view"),
            'message' => "Document created and invite sent to {$recipientEmail}",
        ]);
    }

    /**
     * Build the customer list from API triggers — create the record on first
     * sight, fill in blanks on later calls without overwriting existing data.
     */
    private function upsertCustomer(string $email, string $name, ?string $userId): Customer
    {
        $userId = ($userId !== null && trim($userId) !== '') ? trim($userId) : null;

        // External user_id is a string key — match the user_id column only
        // (never the numeric primary key), then fall back to email.
        $customer = $userId ? Customer::where('user_id', $userId)->first() : null;
        if (!$customer) {
            $customer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();
        }

        $firstName = $name;
        $lastName = '';
        if (str_contains($name, ' ')) {
            [$firstName, $lastName] = explode(' ', $name, 2);
        }

        if (!$customer) {
            return Customer::create([
                'user_id' => $userId ? trim((string) $userId) : null,
                'name' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
            ]);
        }

        if (!$customer->user_id && $userId) {
            $customer->user_id = trim((string) $userId);
        }
        if ($customer->name === '' && $firstName !== '') {
            $customer->name = $firstName;
        }
        if ($customer->lastname === '' && $lastName !== '') {
            $customer->lastname = $lastName;
        }
        if ($customer->isDirty()) {
            $customer->save();
        }

        return $customer;
    }

    public function templateList(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 10)));
        $search = $request->input('search', '');

        $query = Document::where('is_api_template', true)
            ->where('is_template', true)
            ->withCount('fields');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderByDesc('updated_at')->paginate($perPage);

        return response()->json([
            'result' => 'success',
            'data' => $templates->map(fn ($t) => [
                'uuid' => $t->uuid,
                'title' => $t->title ?: $t->original_filename,
                'page_count' => $t->page_count,
                'field_count' => $t->fields_count,
                'api_key' => $t->api_key,
                'main_nda_api' => (bool) $t->main_nda_api,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'current_page' => $templates->currentPage(),
            'last_page' => $templates->lastPage(),
            'total' => $templates->total(),
        ]);
    }
}
