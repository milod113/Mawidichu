<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Construit le menu latéral à partir de config/navigation.php en ne
 * gardant que les entrées dont la route existe et que l'utilisateur a le
 * droit de voir.
 */
class Navigation
{
    /**
     * @return list<array{section: string, items: list<array{route: string, label: string, icon: string}>}>
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $sections = [];

        foreach (config('navigation', []) as $section) {
            $items = [];

            foreach ($section['items'] ?? [] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }

                $roles = $item['roles'] ?? null;

                if ($roles !== null && ! $user->hasAnyRole($roles)) {
                    continue;
                }

                $items[] = [
                    'route' => $item['route'],
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? 'dot',
                ];
            }

            if ($items !== []) {
                $sections[] = ['section' => $section['section'], 'items' => $items];
            }
        }

        return $sections;
    }
}
