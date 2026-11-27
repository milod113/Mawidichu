<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use App\Support\Translations;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $locales = config('mawid.locales');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->getRoleNames(),
                    'service_id' => $user->medecin?->service_id ?? $user->affectation_service_id,
                ] : null,
            ],
            'app' => [
                'name' => config('app.name'),
                'etablissement' => config('mawid.etablissement'),
                'default_theme' => config('mawid.default_theme'),
            ],
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'locale' => $locale,
            'dir' => $locales[$locale]['dir'] ?? 'ltr',
            'locales' => collect($locales)
                ->map(fn (array $l, string $code) => ['code' => $code, 'label' => $l['label'], 'dir' => $l['dir']])
                ->values(),
            'translations' => fn () => Translations::for($locale),
            'navigation' => fn () => Navigation::for($user),
        ];
    }
}
