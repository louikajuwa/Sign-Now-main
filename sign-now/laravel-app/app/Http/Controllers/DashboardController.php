<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }

    public function data(Request $request): JsonResponse
    {
        $rawQ = $request->input('q', '');
        $q = is_string($rawQ) ? trim($rawQ) : '';
        $status = $request->input('status', 'all');
        $sort = $request->input('sort', 'recent');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;
        $user = auth()->user();

        $query = Document::accessibleBy($user)->where('is_template', false);

        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $query->where(function ($qb) use ($like) {
                $qb->where('title', 'like', $like)
                    ->orWhere('original_filename', 'like', $like);
            });
        }

        if ($status === 'in_progress') {
            $query->whereIn('status', ['draft', 'ready', 'filled']);
        } elseif ($status === 'done') {
            $query->where('status', 'completed');
        }

        match ($sort) {
            'name' => $query->orderBy('title'),
            'pages' => $query->orderByDesc('page_count'),
            'created_asc' => $query->orderBy('created_at'),
            'created_desc' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('last_activity_at')->orderByDesc('updated_at'),
        };

        $total = $query->count();
        $documents = $query->skip(($page - 1) * $perPage)->take($perPage)
            ->withCount(['fields', 'recipients'])
            ->get();

        $pendingCount = Document::accessibleBy($user)->where('is_template', false)
            ->whereIn('status', ['draft', 'ready', 'filled'])->count();
        $completedCount = Document::accessibleBy($user)->where('is_template', false)
            ->where('status', 'completed')->count();
        $templateCount = Document::accessibleBy($user)->where('is_template', true)->count();

        return response()->json([
            'documents' => $documents->map(fn ($doc) => [
                'uuid' => $doc->uuid,
                'title' => $doc->title ?: $doc->original_filename,
                'original_filename' => $doc->original_filename,
                'page_count' => $doc->page_count,
                'field_count' => $doc->fields_count,
                'recipient_count' => $doc->recipients_count,
                'status' => $doc->status,
                'template_id' => $doc->template_id,
                'created_at' => $doc->created_at?->toIso8601String(),
                'updated_at' => $doc->updated_at?->toIso8601String(),
                'last_activity_at' => $doc->last_activity_at?->toIso8601String(),
            ]),
            'total' => $total,
            'totalPages' => (int) ceil($total / $perPage),
            'page' => $page,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'templateCount' => $templateCount,
        ]);
    }
}
