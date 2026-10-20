<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Services\PdfConversionService;
use App\Services\PdfStampingService;
use App\Services\SigningTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(
        private PdfConversionService $conversionService,
        private PdfStampingService $stampingService,
        private SigningTokenService $tokenService,
    ) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:26624|mimes:pdf,doc,docx',
        ]);

        $file = $request->file('file');
        $result = $this->conversionService->handleUpload($file);

        $doc = Document::create([
            'uuid' => $result['uuid'],
            'user_id' => auth()->id(),
            'original_filename' => $file->getClientOriginalName(),
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $result['file_path'],
            'page_count' => $result['page_count'],
            'render_scale' => 1.50,
            'status' => 'draft',
        ]);

        AuditLog::log('document_uploaded', $doc, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
        ]);

        return response()->json([
            'result' => 'success',
            'doc_id' => $doc->uuid,
            'page_count' => $doc->page_count,
            'pdf_url' => route('documents.serve', [$doc->uuid, 'original']),
            'edit_url' => route('documents.edit', $doc->uuid),
        ]);
    }

    public function rename(Request $request, string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $request->validate(['title' => 'required|string|max:255']);
        $doc->update(['title' => $request->input('title')]);

        return response()->json(['result' => 'success']);
    }

    public function delete(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);

        Storage::deleteDirectory("docsign/{$uuid}");

        AuditLog::log('document_deleted', $doc, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
        ]);

        $doc->delete();

        return response()->json(['result' => 'success']);
    }

    public function serve(Request $request, string $uuid, string $which)
    {
        // Shared by documents.serve (auth) and signing.serve (public): a valid
        // signing token for THIS document, otherwise an authenticated owner/admin.
        $t = $request->query('t');
        $token = is_string($t) ? trim($t) : '';

        if ($token !== '') {
            if (!$this->tokenService->validateForDocument($token, $uuid)) {
                abort(403);
            }
            $this->tokenService->refreshTtl($token);
            $doc = Document::where('uuid', $uuid)->firstOrFail();
        } elseif (auth()->check()) {
            $doc = Document::findAccessible($uuid);
        } else {
            abort(403);
        }

        if ($which === 'latest') {
            try {
                $outPath = $this->stampingService->renderFromDb($doc);
            } catch (\Throwable $e) {
                \Log::error("DocumentController: render failed for {$uuid}", ['error' => $e->getMessage()]);
                abort(422, 'Could not render PDF');
            }
            if ($outPath && file_exists($outPath)) {
                return response()->file($outPath, [
                    'Content-Type' => 'application/pdf',
                    'Cache-Control' => 'no-cache, no-store',
                ]);
            }
        }

        $path = Storage::path($doc->file_path);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download(string $uuid, string $which)
    {
        $doc = Document::findAccessible($uuid);

        $title = (string) preg_replace('/[\/\\\\\x00-\x1F\x7F]/', '', (string) ($doc->title ?: $doc->original_filename));
        $title = trim($title);
        $filename = $title !== '' ? $title : 'document';
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        if ($which === 'latest') {
            try {
                $outPath = $this->stampingService->renderFromDb($doc);
            } catch (\Throwable $e) {
                \Log::error("DocumentController: render failed for {$uuid}", ['error' => $e->getMessage()]);
                abort(422, 'Could not render PDF');
            }
            if ($outPath && file_exists($outPath)) {
                return response()->download($outPath, preg_replace('/\.pdf$/i', '', $filename) . '_signed.pdf');
            }
        }

        $path = Storage::path($doc->file_path);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $filename);
    }

    /**
     * Activity timeline for the audit panel on the document view page.
     */
    public function audit(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);

        $logs = AuditLog::where('doc_uuid', $uuid)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'result' => 'success',
            'logs' => $logs->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'performed_by' => $log->performed_by,
                'performed_by_type' => $log->performed_by_type,
                'ip_address' => $log->ip_address,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function view(string $uuid)
    {
        $doc = Document::findAccessible($uuid);
        $hasSubmissions = $doc->submissions()->exists();

        $canSendComplete = $doc->recipients()
            ->where('email', '!=', '__admin__')
            ->where('status', 'signed')
            ->exists();

        return view('documents.view', [
            'docId' => $doc->uuid,
            'docTitle' => $doc->title ?: $doc->original_filename,
            'hasSubmissions' => $hasSubmissions,
            'pageCount' => $doc->page_count,
            'canSendComplete' => $canSendComplete,
        ]);
    }
}
