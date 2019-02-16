<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'access',
//        'aj/admin/wechat/giftcode/add'
    ];

    public function handle($request, \Closure $next)
    {
        if ($this->isReading($request) || $this->excludedRoutes($request) || $this->tokensMatch($request))
        {
            return $this->addCookieToResponse($request, $next($request));
        }

        throw new TokenMismatchException;
    }

    protected function excludedRoutes($request)
    {
        $routes = [
            'aj/admin/wechat/giftcode/add'
        ];

        foreach($routes as $route)
            if ($request->is($route))
                return true;

        return false;
    }
}
