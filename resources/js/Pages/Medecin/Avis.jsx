import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Avis({ recues, envoyees }) {
    const { t } = useTrans();
    const [reponse, setReponse] = useState({});

    const cols = (recu) => [
        { key: 'patient', label: t('rdv.patient'), render: (a) => <Link href={route('dossier.show', a.patient.id)} className="hover:underline">{a.patient.nom.toUpperCase()} {a.patient.prenom}</Link> },
        { key: 'type', label: t('rdv.type'), render: (a) => t(`enum.type_avis.${a.type}`) },
        { key: 'de', label: recu ? t('avis.de') : t('avis.vers'), render: (a) => (recu ? a.de : a.vers) },
        { key: 'motif', label: t('avis.motif'), render: (a) => <span className="whitespace-pre-line">{a.motif}</span> },
        { key: 'exp', label: t('avis.expire'), render: (a) => (a.expire_le ? <span dir="ltr" className={a.expire ? 'text-red-600' : ''}>{a.expire_le}</span> : '—') },
        { key: 'statut', label: t('jour.statut'), render: (a) => <Badge tone={a.statut === 'repondu' ? 'green' : 'amber'}>{t(`enum.statut_avis.${a.statut}`)}</Badge> },
        { key: 'rep', label: t('avis.reponse'), render: (a) => (a.reponse && !recu ? a.reponse : recu && !a.expire ? (
            <form className="flex gap-1" onSubmit={(e) => { e.preventDefault(); router.put(route('medecin.avis.repondre', a.id), { reponse_avis: reponse[a.id] ?? a.reponse ?? '' }, { preserveScroll: true }); }}>
                <input className="w-48 rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" value={reponse[a.id] ?? a.reponse ?? ''} onChange={(e) => setReponse({ ...reponse, [a.id]: e.target.value })} />
                <button type="submit" className="rounded-md bg-primary-600 px-2 text-xs font-medium text-white">{t('avis.answer')}</button>
            </form>
        ) : a.reponse) },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('avis.title')}</h2>}>
            <Head title={t('avis.title')} />
            <div className="mx-auto max-w-6xl space-y-8">
                <section className="space-y-2"><h3 className="text-sm font-semibold">{t('avis.recues')}</h3><DataTable columns={cols(true)} rows={recues} empty={t('avis.empty')} /></section>
                <section className="space-y-2"><h3 className="text-sm font-semibold">{t('avis.envoyees')}</h3><DataTable columns={cols(false)} rows={envoyees} empty={t('avis.empty')} /></section>
            </div>
        </AuthenticatedLayout>
    );
}
