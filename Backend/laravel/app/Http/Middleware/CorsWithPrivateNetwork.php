<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsWithPrivateNetwork
{
    public function handle(Request $request, Closure $next)
    {
        $rawOrigin = $request->header('Origin');
        $isNullOrEmpty = empty($rawOrigin) || strtolower(trim($rawOrigin)) === 'null';
        $origin = $isNullOrEmpty ? '*' : $rawOrigin;
        $allowCreds = $isNullOrEmpty ? 'false' : 'true';
        $allowHeaders = 'Origin, Content-Type, Accept, Authorization, X-Requested-With, Access-Control-Request-Private-Network, X-User-Id, X-User-Role, X-User-Email, x-user-id, x-user-role, x-user-email';

        if ($request->isMethod('OPTIONS')) {
            $resp = response('', 204)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', $allowHeaders)
                ->header('Access-Control-Allow-Private-Network', 'true')
                ->header('Access-Control-Max-Age', '86400');
            if ($allowCreds === 'true') {
                $resp->header('Access-Control-Allow-Credentials', 'true');
            }
            return $resp;
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);
        $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        if ($allowCreds === 'true') {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
