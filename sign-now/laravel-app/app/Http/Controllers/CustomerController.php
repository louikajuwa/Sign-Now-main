<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        return view('customers.index');
    }

    public function data(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;
        $search = trim((string) $request->input('search', ''));

        $query = Customer::orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('user_id', 'like', $like);
            });
        }

        $total = $query->count();
        $customers = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $customers->map(fn (Customer $c) => $this->row($c)),
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'current_page' => $page,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $customer = Customer::create($data);

        AuditLog::log('customer_created', null, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['customer_id' => $customer->id, 'email' => $customer->email],
        ]);

        return response()->json(['result' => 'success', 'customer' => $this->row($customer)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $data = $this->validated($request, $customer->id);

        $customer->update($data);

        AuditLog::log('customer_updated', null, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['customer_id' => $customer->id, 'email' => $customer->email],
        ]);

        return response()->json(['result' => 'success', 'customer' => $this->row($customer)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        AuditLog::log('customer_deleted', null, [
            'performed_by' => (string) auth()->id(),
            'performed_by_type' => 'admin',
            'meta' => ['customer_id' => $id, 'email' => $customer->email],
        ]);

        return response()->json(['result' => 'success']);
    }

    /**
     * Customer search consumed by the builder's recipient panel
     * (public/js/builder.js — request/response shape is fixed there).
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $searchKey = (string) $request->input('searchKey', 'all');

        if ($search === '') {
            return response()->json(['result' => 'success', 'data' => []]);
        }

        $like = "%{$search}%";
        $query = Customer::query();

        switch ($searchKey) {
            case 'email':
                $query->where('email', 'like', $like);
                break;
            case 'company':
                $query->where('company', 'like', $like);
                break;
            case 'name':
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)->orWhere('lastname', 'like', $like);
                });
                break;
            case 'UserID':
                $query->where(function ($q) use ($search, $like) {
                    $q->where('user_id', 'like', $like);
                    if (ctype_digit($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
                break;
            default:
                $query->where(function ($q) use ($like) {
                    $q->where('email', 'like', $like)
                        ->orWhere('company', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('lastname', 'like', $like)
                        ->orWhere('user_id', 'like', $like);
                });
        }

        $customers = $query->orderByDesc('id')->limit(40)->get();

        return response()->json([
            'result' => 'success',
            'data' => $customers->map(fn (Customer $c) => $c->toCustomerData()),
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'string', 'max:64', 'unique:customers,user_id' . ($ignoreId ? ",{$ignoreId}" : '')],
            'name' => ['nullable', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email' . ($ignoreId ? ",{$ignoreId}" : '')],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'apt' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:128'],
            'state' => ['nullable', 'string', 'max:128'],
            'zip' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:128'],
        ]);

        $data['email'] = strtolower(trim($data['email']));

        // Empty strings arrive as null (ConvertEmptyStringsToNull) but the
        // columns are NOT NULL with '' defaults — normalize explicitly. Only
        // touch keys present in the request so partial updates don't wipe
        // the other fields.
        if (array_key_exists('user_id', $data)) {
            $userId = trim((string) ($data['user_id'] ?? ''));
            $data['user_id'] = $userId !== '' ? $userId : null;
        }

        foreach (['name', 'lastname', 'company', 'phone', 'street', 'apt', 'city', 'state', 'zip', 'country'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = trim((string) ($data[$key] ?? ''));
            }
        }

        return $data;
    }

    private function row(Customer $c): array
    {
        return [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'name' => $c->name,
            'lastname' => $c->lastname,
            'full_name' => $c->full_name,
            'email' => $c->email,
            'company' => $c->company,
            'phone' => $c->phone,
            'street' => $c->street,
            'apt' => $c->apt,
            'city' => $c->city,
            'state' => $c->state,
            'zip' => $c->zip,
            'country' => $c->country,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
