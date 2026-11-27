import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import ServiceForm from './Partials/ServiceForm';

export default function Create({ options }) {
    const { t } = useTrans();

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('svc.new')}</h2>}>
            <Head title={t('svc.new')} />
            <div className="mx-auto max-w-3xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <ServiceForm options={options} admin submitLabel={t('common.create')} />
            </div>
        </AuthenticatedLayout>
    );
}
