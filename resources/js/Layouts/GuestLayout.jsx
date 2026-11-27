import ApplicationLogo from '@/Components/ApplicationLogo';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeSwitcher from '@/Components/ThemeSwitcher';
import { useTrans } from '@/lib/i18n';
import { useDocumentDirection } from '@/lib/useDocumentDirection';
import { usePage } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    useDocumentDirection();
    const { t, locale } = useTrans();
    const { app } = usePage().props;
    const etab = app?.etablissement ?? {};

    return (
        <div className="flex min-h-screen flex-col items-center px-4 pt-6 sm:justify-center sm:pt-0">
            <div className="mb-6 flex w-full max-w-md justify-end gap-2">
                <LocaleSwitcher />
                <ThemeSwitcher />
            </div>

            <div className="flex flex-col items-center text-center">
                <ApplicationLogo className="h-14 w-14" />
                <h1 className="mt-3 text-xl font-semibold">{app?.name}</h1>
                <p className="text-sm text-gray-500 dark:text-gray-400">{t('app.tagline')}</p>
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    {locale === 'ar'
                        ? `${etab.nom_ar} — ${etab.rattachement_ar}`
                        : `${etab.nom} — ${etab.rattachement}`}
                </p>
            </div>

            <div className="mt-6 w-full max-w-md overflow-hidden rounded-xl bg-white px-6 py-6 shadow-md ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                {children}
            </div>
        </div>
    );
}
