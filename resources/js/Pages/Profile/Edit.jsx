import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

const card = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-8 dark:bg-gray-900 dark:ring-gray-800';

export default function Edit({ mustVerifyEmail, status }) {
    const { t } = useTrans();

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('profile.title')}</h2>}>
            <Head title={t('profile.title')} />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className={card}>
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} className="max-w-xl" />
                </div>
                <div className={card}>
                    <UpdatePasswordForm className="max-w-xl" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
