<?php

namespace App\Http\Middleware;

use App\Services\SigningTokenService;
use Closure;
use Illuminate\Http\Request;

class ValidateSigningToken
{
    public function __construct(
        private SigningTokenService $tokenService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $token = trim($request->query('t', ''));

        if ($token === '') {
            if (auth()->check()) {
                return $next($request);
            }
            return response()->view('signing.unauthorized', [], 403);
        }

        $access = $this->tokenService->validate($token);

        if (!$access) {
            return response()->view('signing.unauthorized', [], 403);
        }

        $request->merge([
            'signing_access' => $access,
            'signing_token' => $token,
        ]);

        return $next($request);
    }
}
