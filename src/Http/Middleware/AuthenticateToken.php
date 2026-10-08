<?php

namespace AbdullahDheir\BlogMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protects the endpoint with one static bearer token; with no token configured it does not exist (404). */
class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('blog-mcp.token');

        if ($token === '') {
            abort(404);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['error' => 'Unauthorized'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        return $next($request);
    }
}
