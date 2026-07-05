<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pre-launch password gate.
 *
 * To disable: set PRELAUNCH_PASSWORD= (empty) in .env — this middleware will skip.
 * To remove permanently: remove from bootstrap/app.php web middleware group.
 */
class PreLaunchPassword
{
    private const WHITELIST = [
        'coming-soon',
        'coming-soon/*',
        'admin',
        'admin/*',
        'pos',
        'pos/*',
        'seller',
        'seller/*',
        'delivery',
        'delivery/*',
        'webhooks/*',
        'payu/*',
        'auth/*',
        'privacy-policy',
        'terms-of-service',
        'cookie-policy',
        'returns-policy',
        'shipping',
        'about',
        'contact',
        'robots.txt',
        'sitemap*.xml',
        'csrf-token',
        'build/*',
        'images/*',
        'storage/*',
        'favicon.ico',
        'favicon-*.png',
        'apple-touch-icon.png',
        'site.webmanifest',
        'up',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Disabled when no password configured
        if (empty(config('app.prelaunch_password'))) {
            return $next($request);
        }

        // Whitelist check
        foreach (self::WHITELIST as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // Admin bypass
        if (auth()->guard('admin')->check()) {
            return $next($request);
        }

        // Session flag check
        if ($request->session()->get('prelaunch_verified') === true) {
            return $next($request);
        }

        return redirect('/coming-soon');
    }
}
