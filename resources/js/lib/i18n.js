import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Traduction côté front à partir des chaînes partagées par Laravel
 * (lang/ui/{locale}.json). Usage : const { t } = useTrans(); t('nav.dashboard')
 * Les remplacements suivent la syntaxe Laravel : t('dashboard.welcome', { name }).
 */
export function useTrans() {
    const { translations = {}, locale, dir } = usePage().props;

    const t = useCallback(
        (key, replacements = {}) => {
            let value = translations[key] ?? key;

            Object.entries(replacements).forEach(([name, replacement]) => {
                value = value.replaceAll(`:${name}`, String(replacement));
            });

            return value;
        },
        [translations],
    );

    return { t, locale, dir, isRtl: dir === 'rtl' };
}
