import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import Icon from '@/Components/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PatientModal from './Partials/PatientModal';

export default function Show({ patient, rendezVous, canEdit, canBook }) {
    const { t, locale } = useTrans();
    const [editing, setEditing] = useState(false);

    const info = [
        ['patient.ipp', patient.ipp], ['patient.naissance', patient.date_naissance ? String(patient.date_naissance).slice(0, 10) : null],
        ['patient.sexe', patient.sexe ? t(`enum.sexe.${patient.sexe}`) : null], ['patient.telephone', patient.telephone],
        ['patient.nin', patient.nin], ['patient.chifa', patient.numero_chifa], ['patient.wilaya', patient.wilaya],
        ['patient.commune', patient.commune], ['patient.adresse', patient.adresse], ['patient.absences', String(patient.nb_absences)],
    ];

    const columns = [
        { key: 'date', label: t('rdv.date'), render: (r) => <span dir="ltr">{r.date_rdv}{r.heure ? ` ${r.heure}` : ''}</span> },
        { key: 'ticket', label: t('rdv.ticket'), render: (r) => <Link href={route('accueil.rendez-vous.show', r.id)} className="font-mono text-xs text-primary-700 hover:underline dark:text-primary-300">{r.numero_ticket}</Link> },
        { key: 'service', label: t('rdv.service'), render: (r) => (locale === 'ar' && r.service.nom_ar ? r.service.nom_ar : r.service.nom) },
        { key: 'type', label: t('rdv.type'), render: (r) => r.type },
        { key: 'medecin', label: t('rdv.medecin'), render: (r) => r.medecin ?? t('common.none') },
        { key: 'statut', label: '', render: (r) => <Badge tone={r.statut === 'termine' ? 'green' : r.statut === 'absent' ? 'amber' : 'teal'}>{t(`enum.statut_rdv.${r.statut}`)}</Badge> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{patient.nom.toUpperCase()} {patient.prenom}</h2>}>
            <Head title={`${patient.nom} ${patient.prenom}`} />
            <div className="mx-auto max-w-5xl space-y-6">
                <Link href={route('accueil.patients.index')} className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                    <Icon name="back" className="h-4 w-4 rtl:rotate-180" />{t('common.back')}
                </Link>

                <section className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('patient.admin_only')}</p>
                        <div className="flex gap-2">
                            {canEdit && (
                                <button type="button" onClick={() => setEditing(true)} className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('common.edit')}</button>
                            )}
                            {canBook && <AddButton onClick={() => router.visit(route('accueil.rendez-vous.create', { patient: patient.id }))}>{t('patient.book')}</AddButton>}
                        </div>
                    </div>
                    <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                        {info.map(([k, v]) => (
                            <div key={k}>
                                <dt className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{t(k)}</dt>
                                <dd className="text-sm" dir="auto">{v || t('common.none')}</dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section className="space-y-3">
                    <h3 className="text-base font-semibold">{t('patient.history')}</h3>
                    <DataTable columns={columns} rows={rendezVous} empty={t('patient.empty')} />
                </section>
            </div>
            <PatientModal show={editing} onClose={() => setEditing(false)} patient={patient} />
        </AuthenticatedLayout>
    );
}
