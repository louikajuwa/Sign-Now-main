<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the signing profile — reusable signature and the personal
     * details that auto-fill admin-assigned fields at finalization.
     */
    public function updateSigning(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lastname' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'apt' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:128'],
            'state' => ['nullable', 'string', 'max:128'],
            'zip' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:128'],
            'signature' => ['nullable', 'string', 'max:500000', 'regex:/^data:image\/(png|jpeg|webp);base64,/'],
            'clear_signature' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        foreach (['lastname', 'company', 'phone', 'street', 'apt', 'city', 'state', 'zip', 'country'] as $key) {
            if (array_key_exists($key, $data)) {
                $user->{$key} = trim((string) ($data[$key] ?? ''));
            }
        }

        if ($request->boolean('clear_signature')) {
            $user->signature = null;
        } elseif (!empty($data['signature'])) {
            $user->signature = $data['signature'];
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'signing-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
