import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { t } = useTrans();
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={<h2 className="truncate text-lg font-semibold">{t('dashboard.title')}</h2>}
        >
            <Head title={t('dashboard.title')} />

            <div className="mx-auto max-w-5xl space-y-6">
                <section className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <h3 className="text-xl font-semibold">
                        {t('dashboard.welcome', { name: user.name })}
                    </h3>

                    <p className="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                        {t('dashboard.your_roles')}
                    </p>
                    {user.roles.length > 0 ? (
                        <ul className="mt-2 flex flex-wrap gap-2">
                            {user.roles.map((role) => (
                                <li
                                    key={role}
                                    className="rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300"
                                >
                                    {t(`roles.${role}`)}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-2 text-sm text-amber-700 dark:text-amber-400">
                            {t('dashboard.no_role')}
                        </p>
                    )}
                </section>

                <p className="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                    {t('dashboard.step_notice')}
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
