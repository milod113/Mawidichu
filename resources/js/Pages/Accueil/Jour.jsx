import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const tones = { arrive: 'amber', en_preconsultation: 'amber', pret_medecin: 'green', appele: 'teal', en_consultation: 'teal', termine: 'gray', planifie: 'gray', absent: 'gray' };

export default function Jour({ services, service, date, rdvs, replanifier, controles, priorites, peutAgir }) {
    const { t, te, locale } = useTrans();
    const { errors } = usePage().props;
    const [prio, setPrio] = useState(null);
    const [form, setForm] = useState({ priorite: 'personnel_chu', motif_priorite: '' });

    // Rafraîchissement temps réel (canal privé du service) + repli par sondage toutes les 30 s.
    useEffect(() => {
        const timer = setInterval(() => router.reload({ only: ['rdvs', 'replanifier', 'controles'] }), 30000);
        const channel = service && window.Echo ? window.Echo.private(`service.${service}`).listen('.service.activity', () => router.reload({ only: ['rdvs', 'replanifier', 'controles'] })) : null;
        return () => { clearInterval(timer); if (channel) window.Echo.leave(`service.${service}`); };
    }, [service]);

    const post = (name, rdv, data = {}) => router.post(route(`accueil.rendez-vous.${name}`, rdv.id), data, { preserveScroll: true });
    const changeService = (e) => router.get(route('accueil.jour'), { service: e.target.value });
    const btn = 'whitespace-nowrap rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800';

    const columns = [
        { key: 'rang', label: '#', render: (r) => r.rang ?? '' },
        { key: 'ticket', label: t('rdv.ticket'), render: (r) => <span className="font-mono font-semibold" dir="ltr">{r.numero_ticket}</span> },
        { key: 'patient', label: t('rdv.patient'), render: (r) => (
            <Link href={route('accueil.patients.show', r.patient.id)} className="hover:underline">
                {r.patient.nom.toUpperCase()} {r.patient.prenom}
                {r.patient.nb_absences > 0 && <span className="ms-1 text-xs text-red-600 dark:text-red-400">({t('jour.absences', { n: r.patient.nb_absences })})</span>}
            </Link>
        ) },
        { key: 'heure', label: t('rdv.heure'), render: (r) => <span dir="ltr">{r.heure}{r.checkin ? ` → ${r.checkin}` : ''}</span> },
        { key: 'type', label: t('rdv.type'), render: (r) => r.type.libelle },
        { key: 'statut', label: t('jour.statut'), render: (r) => (
            <div className="flex flex-wrap items-center gap-1">
                <Badge tone={tones[r.statut] ?? 'gray'}>{t(`enum.statut_rdv.${r.statut}`)}</Badge>
                {r.priorite !== 'normal' && <span title={r.motif_priorite ?? ''}><Badge tone="amber">{t(`enum.priorite.${r.priorite}`)}</Badge></span>}
            </div>
        ) },
        ...(peutAgir ? [{ key: 'actions', label: '', render: (r) => (
            <div className="flex flex-wrap justify-end gap-1">
                {['planifie', 'en_attente_retour'].includes(r.statut) && <button type="button" onClick={() => post('arrivee', r)} className="whitespace-nowrap rounded-md bg-primary-600 px-2 py-1 text-xs font-medium text-white hover:bg-primary-700">{t('jour.checkin')}</button>}
                {['planifie', 'arrive', 'en_preconsultation', 'pret_medecin', 'en_attente_retour'].includes(r.statut) && <button type="button" onClick={() => { setPrio(r); setForm({ priorite: r.priorite === 'normal' ? 'personnel_chu' : r.priorite, motif_priorite: r.motif_priorite ?? '' }); }} className={btn}>{t('jour.priorite')}</button>}
                {['planifie', 'arrive', 'en_attente_retour'].includes(r.statut) && <button type="button" onClick={() => window.confirm(t('jour.confirm_absent')) && post('absent', r)} className={btn}>{t('jour.absent')}</button>}
            </div>
        ) }] : []),
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('jour.title')} — <span dir="ltr">{date}</span></h2>}>
            <Head title={t('jour.title')} />
            <div className="mx-auto max-w-6xl space-y-6">
                {errors.file && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{te(errors.file)}</p>}
                {errors.priorite && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{te(errors.priorite)}</p>}

                <Select value={service ?? ''} onChange={changeService} className="max-w-xs" aria-label={t('rdv.service')}>
                    {services.map((s) => <option key={s.id} value={s.id}>{locale === 'ar' && s.nom_ar ? s.nom_ar : s.nom}</option>)}
                </Select>

                <DataTable columns={columns} rows={rdvs} empty={t('jour.empty')} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-2">
                        <h3 className="text-sm font-semibold">{t('jour.replanifier')}</h3>
                        <DataTable rows={replanifier} empty={t('jour.replanifier_empty')} columns={[
                            { key: 'patient', label: t('rdv.patient'), render: (r) => `${r.patient.nom.toUpperCase()} ${r.patient.prenom}` },
                            { key: 'date', label: t('jour.date_initiale'), render: (r) => <span dir="ltr">{r.date_rdv}</span> },
                            { key: 'a', label: '', render: (r) => <Link href={route('accueil.rendez-vous.create', { patient: r.patient_id, type: r.type_id })} className="text-xs font-medium text-primary-700 hover:underline dark:text-primary-300">{t('jour.replanifier_action')}</Link> },
                        ]} />
                    </section>
                    <section className="space-y-2">
                        <h3 className="text-sm font-semibold">{t('jour.controles')}</h3>
                        <DataTable rows={controles} empty={t('jour.controles_empty')} columns={[
                            { key: 'patient', label: t('rdv.patient'), render: (r) => `${r.patient.nom.toUpperCase()} ${r.patient.prenom}` },
                            { key: 'echeance', label: t('jour.echeance'), render: (r) => <span dir="ltr">{r.echeance}</span> },
                            { key: 'a', label: '', render: (r) => <Link href={route('accueil.rendez-vous.create', { patient: r.patient_id })} className="text-xs font-medium text-primary-700 hover:underline dark:text-primary-300">{t('jour.planifier')}</Link> },
                        ]} />
                    </section>
                </div>
            </div>

            <Modal show={prio !== null} onClose={() => setPrio(null)} maxWidth="md">
                <form className="space-y-4 p-6" onSubmit={(e) => { e.preventDefault(); router.post(route('accueil.rendez-vous.priorite', prio.id), form, { preserveScroll: true, onSuccess: () => setPrio(null) }); }}>
                    <h3 className="text-base font-semibold">{t('jour.priorite')}</h3>
                    <Field label={t('rdv.priorite')} htmlFor="prio">
                        <Select id="prio" value={form.priorite} onChange={(e) => setForm({ ...form, priorite: e.target.value })}>
                            {priorites.map((p) => <option key={p} value={p}>{t(`enum.priorite.${p}`)}</option>)}
                        </Select>
                    </Field>
                    {form.priorite !== 'normal' && (
                        <Field label={t('rdv.motif_priorite')} error={te(errors.motif_priorite)} htmlFor="motif">
                            <TextInput id="motif" className="w-full" value={form.motif_priorite} onChange={(e) => setForm({ ...form, motif_priorite: e.target.value })} />
                        </Field>
                    )}
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setPrio(null)} className={btn}>{t('common.cancel')}</button>
                        <button type="submit" className="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">{t('common.save')}</button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
