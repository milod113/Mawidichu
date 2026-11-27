import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ services, canCreate }) {
    const { t, locale } = useTrans();

    const columns = [
        { key: 'code', label: t('svc.code'), render: (s) => <span className="font-mono text-xs">{s.code}</span> },
        { key: 'nom', label: t('common.name'), render: (s) => (
            <Link href={route('parametrage.services.show', s.id)} className="font-medium text-primary-700 hover:underline dark:text-primary-300">
                {locale === 'ar' && s.nom_ar ? s.nom_ar : s.nom}
            </Link>
        ) },
        { key: 'mode', label: t('svc.mode_planification'), render: (s) => t(`enum.mode_planification.${s.mode_planification}`) },
        { key: 'medecins', label: t('svc.medecins'), render: (s) => s.medecins_count },
        { key: 'boxes', label: t('svc.boxes'), render: (s) => s.boxes_count },
        { key: 'is_active', label: '', render: (s) => <Badge tone={s.is_active ? 'green' : 'gray'}>{t(s.is_active ? 'common.active' : 'common.inactive')}</Badge> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('svc.title')}</h2>}>
            <Head title={t('svc.title')} />
            <div className="mx-auto max-w-5xl space-y-4">
                {canCreate && (
                    <div className="flex justify-end">
                        <AddButton onClick={() => router.visit(route('parametrage.services.create'))}>{t('svc.new')}</AddButton>
                    </div>
                )}
                <DataTable columns={columns} rows={services} empty={t('svc.empty')} />
            </div>
        </AuthenticatedLayout>
    );
}
