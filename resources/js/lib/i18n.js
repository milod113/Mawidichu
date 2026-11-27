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

    /**
     * Traduit un message d'erreur serveur s'il s'agit d'une clé (« rdv.erreur.complet »,
     * « patient.doublon:IPP-2030-000001 ») ; sinon le renvoie tel quel.
     */
    const te = useCallback(
        (message) => {
            if (!message || typeof message !== 'string') return message;
            const [key, ...rest] = message.split(':');
            return translations[key] ? t(key, rest.length ? { ipp: rest.join(':') } : {}) : message;
        },
        [t, translations],
    );

    return { t, te, locale, dir, isRtl: dir === 'rtl' };
}
