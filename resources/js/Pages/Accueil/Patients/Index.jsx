import AddButton from '@/Components/AddButton';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import PatientModal from './Partials/PatientModal';

export default function Index({ patients, q, canEdit }) {
    const { t } = useTrans();
    const [term, setTerm] = useState(q ?? '');
    const [creating, setCreating] = useState(false);
    const first = useRef(true);

    // Recherche instantanée (500 ms après la dernière frappe).
    useEffect(() => {
        if (first.current) { first.current = false; return undefined; }
        const timer = setTimeout(() => {
            router.get(route('accueil.patients.index'), term ? { q: term } : {}, { preserveState: true, replace: true });
        }, 400);
        return () => clearTimeout(timer);
    }, [term]);

    const columns = [
        { key: 'ipp', label: t('patient.ipp'), render: (p) => <span className="font-mono text-xs" dir="ltr">{p.ipp}</span> },
        { key: 'nom', label: t('common.name'), render: (p) => (
            <Link href={route('accueil.patients.show', p.id)} className="font-medium text-primary-700 hover:underline dark:text-primary-300">
                {p.nom.toUpperCase()} {p.prenom}
            </Link>
        ) },
        { key: 'naissance', label: t('patient.naissance'), render: (p) => (p.date_naissance ? String(p.date_naissance).slice(0, 10) : t('common.none')) },
        { key: 'telephone', label: t('patient.telephone'), render: (p) => <span dir="ltr">{p.telephone ?? t('common.none')}</span> },
        { key: 'wilaya', label: t('patient.wilaya'), render: (p) => p.wilaya ?? t('common.none') },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('patient.title')}</h2>}>
            <Head title={t('patient.title')} />
            <div className="mx-auto max-w-5xl space-y-4">
                <div className="flex flex-wrap items-center gap-3">
                    <TextInput type="search" className="min-w-0 flex-1" placeholder={t('patient.search')} value={term} onChange={(e) => setTerm(e.target.value)} isFocused aria-label={t('patient.search')} />
                    {canEdit && <AddButton onClick={() => setCreating(true)}>{t('patient.new')}</AddButton>}
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">{t('patient.admin_only')}</p>
                <DataTable columns={columns} rows={patients.data} empty={t('patient.empty')} />
                <Pagination links={patients.links} />
            </div>
            <PatientModal show={creating} onClose={() => setCreating(false)} patient={null} />
        </AuthenticatedLayout>
    );
}
