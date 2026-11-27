import Icon from '@/Components/Icon';
import { useTrans } from '@/lib/i18n';
import { THEMES, useTheme } from '@/lib/theme';
import { usePage } from '@inertiajs/react';

const icons = { light: 'sun', dark: 'moon', system: 'system' };

export default function ThemeSwitcher({ className = '' }) {
    const { t } = useTrans();
    const { app } = usePage().props;
    const { theme, setTheme } = useTheme(app?.default_theme);

    return (
        <div
            role="radiogroup"
            aria-label={t('theme.label')}
            className={`inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-800 ${className}`}
        >
            {THEMES.map((value) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={theme === value}
                    title={t(`theme.${value}`)}
                    onClick={() => setTheme(value)}
                    className={`rounded-md p-1.5 transition ${
                        theme === value
                            ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-700 dark:text-primary-300'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                    }`}
                >
                    <Icon name={icons[value]} className="h-4 w-4" />
                    <span className="sr-only">{t(`theme.${value}`)}</span>
                </button>
            ))}
        </div>
    );
}
