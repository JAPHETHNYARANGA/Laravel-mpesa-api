<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IPWhitelist
{
     // List of allowed IP addresses
     private $allowedIps = [
        '51.83.128.210',    
    ];

    public function handle(Request $request, Closure $next)
    {
        $clientIp = $request->ip();
        
        if (!in_array($clientIp, $this->allowedIps)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        return $next($request);
    }
}
