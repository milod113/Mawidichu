import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'mawid-theme';
export const THEMES = ['light', 'dark', 'system'];

function readStoredTheme(fallback) {
    try {
        return localStorage.getItem(STORAGE_KEY) || fallback;
    } catch {
        return fallback;
    }
}

function applyTheme(theme) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = theme === 'dark' || (theme === 'system' && prefersDark);
    document.documentElement.classList.toggle('dark', dark);
}

/**
 * Thème clair / sombre / système, mémorisé par poste (localStorage).
 */
export function useTheme(fallback = 'system') {
    const [theme, setThemeState] = useState(() => readStoredTheme(fallback));

    useEffect(() => {
        applyTheme(theme);

        if (theme !== 'system') {
            return undefined;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => applyTheme('system');
        media.addEventListener('change', listener);

        return () => media.removeEventListener('change', listener);
    }, [theme]);

    const setTheme = useCallback((next) => {
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Stockage indisponible : le choix reste valable pour la session en cours.
        }
        setThemeState(next);
    }, []);

    return { theme, setTheme };
}
