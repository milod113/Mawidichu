import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Garde <html lang dir> synchronisé avec la langue active après une
 * navigation Inertia (le premier rendu est déjà correct côté serveur).
 */
export function useDocumentDirection() {
    const { locale, dir } = usePage().props;

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [locale, dir]);
}
