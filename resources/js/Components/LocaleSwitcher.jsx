import Icon from '@/Components/Icon';
import { useTrans } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';

export default function LocaleSwitcher({ className = '' }) {
    const { t, locale } = useTrans();
    const { locales = [] } = usePage().props;

    const change = (code) => {
        if (code === locale) return;
        router.post(route('locale.update'), { locale: code }, { preserveScroll: true });
    };

    return (
        <div
            role="radiogroup"
            aria-label={t('locale.label')}
            className={`inline-flex items-center gap-1 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-800 ${className}`}
        >
            <Icon name="language" className="mx-1 h-4 w-4 text-gray-500 dark:text-gray-400" />
            {locales.map((l) => (
                <button
                    key={l.code}
                    type="button"
                    role="radio"
                    aria-checked={locale === l.code}
                    lang={l.code}
                    onClick={() => change(l.code)}
                    className={`rounded-md px-2 py-1 text-xs font-medium transition ${
                        locale === l.code
                            ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-700 dark:text-primary-300'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
                    }`}
                >
                    {l.label}
                </button>
            ))}
        </div>
    );
}
