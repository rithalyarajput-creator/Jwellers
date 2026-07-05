<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $reportOnly = (bool) config('app.csp_report_only', true);
        $reportUri = config('app.csp_report_uri');

        $directives = [
            "default-src 'self'",
            // Inline-script allowance kept while we migrate Blade templates to use the nonce.
            // Once all inline <script> blocks carry nonce=\"{{ request()->attributes->get('csp_nonce') }}\",
            // drop 'unsafe-inline' from script-src.
            "script-src 'self' 'unsafe-inline' 'nonce-{$nonce}' https://fonts.bunny.net https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' https://fonts.bunny.net https://cdnjs.cloudflare.com",
            "connect-src 'self' https://www.google-analytics.com https://www.facebook.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        if ($reportUri) {
            $directives[] = "report-uri {$reportUri}";
        }

        $headerName = $reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($headerName, implode('; ', $directives));

        return $response;
    }
}
