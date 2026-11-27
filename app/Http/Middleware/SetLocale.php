<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la langue de l'interface : préférence du compte, sinon choix
 * mémorisé en session, sinon langue par défaut de l'application.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('mawid.locales', []));

        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (! in_array($locale, $supported, true)) {
            $locale = in_array(config('app.locale'), $supported, true)
                ? config('app.locale')
                : ($supported[0] ?? config('app.locale'));
        }

        App::setLocale($locale);

        return $next($request);
    }
}
