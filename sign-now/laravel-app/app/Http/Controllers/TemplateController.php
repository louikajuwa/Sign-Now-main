<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Field;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index()
    {
        return view('templates.index');
    }

    public function data(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;
        $search = trim((string) $request->input('search', ''));

        $query = Document::accessibleBy(auth()->user())->where('is_template', true)->orderByDesc('updated_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $templates = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->withCount('fields')
            ->get();

        return response()->json([
            'templates' => $templates->map(fn ($t) => [
                'uuid' => $t->uuid,
                'title' => $t->title ?: $t->original_filename,
                'page_count' => $t->page_count,
                'field_count' => $t->fields_count,
                'is_api_template' => $t->is_api_template,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'total' => $total,
            'totalPages' => (int) ceil($total / $perPage),
            'page' => $page,
        ]);
    }

    public function markTemplate(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $doc->update(['is_template' => true]);

        return response()->json(['result' => 'success']);
    }

    public function unmarkTemplate(string $uuid): JsonResponse
    {
        $doc = Document::findAccessible($uuid);
        $doc->update(['is_template' => false]);

        return response()->json(['result' => 'success']);
    }

    public function useTemplate(string $uuid): JsonResponse
    {
        $template = Document::accessibleBy(auth()->user())
            ->where('uuid', $uuid)->where('is_template', true)->firstOrFail();
        $newUuid = Str::uuid()->toString();

        $srcDir = "docsign/{$template->uuid}";
        $dstDir = "docsign/{$newUuid}";

        if (!Storage::exists("{$srcDir}/original.pdf")) {
            return response()->json([
                'result' => 'error',
                'message' => 'Template source PDF is missing.',
            ], 422);
        }

        $newDoc = $template->replicate();
        $newDoc->uuid = $newUuid;
        $newDoc->user_id = auth()->id();
        $newDoc->is_template = false;
        $newDoc->is_api_template = false;
        $newDoc->api_key = null;
        $newDoc->main_nda_api = false;
        $newDoc->template_id = $template->id;
        $newDoc->status = 'ready';
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
            $clone->field_uid = Str::random(20);
            $clone->save();
        }

        return response()->json([
            'result' => 'success',
            'doc_id' => $newUuid,
            'edit_url' => route('documents.edit', $newUuid),
        ]);
    }
}
