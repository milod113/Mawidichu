import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export default function File({ rdvs, preconsultation }) {
    const { t, te } = useTrans();
    const { errors, auth } = usePage().props;
    const serviceId = auth?.user?.service_id;

    useEffect(() => {
        const refresh = () => router.reload({ only: ['rdvs'] });
        const timer = setInterval(refresh, 20000);
        const channel = serviceId && window.Echo ? window.Echo.private(`service.${serviceId}`).listen('.service.activity', refresh) : null;
        return () => { clearInterval(timer); if (channel) window.Echo.leave(`service.${serviceId}`); };
    }, [serviceId]);

    const post = (name, r, extra = {}) => router.post(route(`medecin.${name}`, r.id), extra, { preserveScroll: true });
    const btn = 'whitespace-nowrap rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800';
    const primary = 'whitespace-nowrap rounded-md bg-primary-600 px-2 py-1 text-xs font-medium text-white hover:bg-primary-700';
    const tone = (s) => (s === 'pret_medecin' ? 'green' : s === 'appele' || s === 'en_consultation' ? 'teal' : 'amber');
    const attente = rdvs.filter((r) => r.appelable).length;

    const columns = [
        { key: 'ticket', label: t('rdv.ticket'), render: (r) => <span className="font-mono font-semibold" dir="ltr">{r.numero_ticket}</span> },
        { key: 'patient', label: t('rdv.patient'), render: (r) => `${r.patient.nom.toUpperCase()} ${r.patient.prenom}` },
        { key: 'type', label: t('rdv.type'), render: (r) => r.type },
        { key: 'checkin', label: t('medecin.arrivee'), render: (r) => <span dir="ltr">{r.checkin ?? '—'}</span> },
        { key: 'statut', label: t('jour.statut'), render: (r) => (
            <div className="flex flex-wrap items-center gap-1">
                <Badge tone={tone(r.statut)}>{t(`enum.statut_rdv.${r.statut}`)}</Badge>
                {r.priorite !== 'normal' && <span title={r.motif_priorite ?? ''}><Badge tone="amber">{t(`enum.priorite.${r.priorite}`)}</Badge></span>}
            </div>
        ) },
        { key: 'a', label: '', render: (r) => (
            <div className="flex flex-wrap justify-end gap-1">
                {r.appelable && <button type="button" className={btn} onClick={() => post('appeler', r)}>{t('medecin.appeler')}</button>}
                {r.statut === 'appele' && <>
                    <button type="button" className={btn} onClick={() => post('rappeler', r)}>{t('medecin.rappeler')}</button>
                    <button type="button" className={btn} onClick={() => post('absent', r)}>{t('medecin.absent_appel')}</button>
                    <button type="button" className={primary} onClick={() => post('demarrer', r)}>{t('medecin.demarrer')}</button>
                </>}
                {r.statut === 'en_consultation' && r.consultation_id && <Link href={route('medecin.consultations.show', r.consultation_id)} className={primary}>{t('medecin.reprendre')}</Link>}
                <Link href={route('dossier.show', r.patient.id)} className={btn}>{t('medecin.dossier')}</Link>
            </div>
        ) },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('medecin.title')}</h2>}>
            <Head title={t('medecin.title')} />
            <div className="mx-auto max-w-6xl space-y-4">
                {errors.file && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{te(errors.file)}</p>}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-gray-600 dark:text-gray-400">{preconsultation ? t('medecin.hint_pre') : t('medecin.hint')}</p>
                    <button type="button" disabled={attente === 0} onClick={() => router.post(route('medecin.suivant'), {}, { preserveScroll: true })} className="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-40">{t('medecin.suivant')}</button>
                </div>
                <DataTable columns={columns} rows={rdvs} empty={t('medecin.empty')} />
            </div>
        </AuthenticatedLayout>
    );
}
