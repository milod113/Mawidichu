import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import DataTable from '@/Components/DataTable';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ absences, services, actions }) {
    const { t, te, locale } = useTrans();
    const { errors } = usePage().props;
    const [open, setOpen] = useState(false);
    const [apply, setApply] = useState(null);
    const today = new Date().toISOString().slice(0, 10);
    const vide = { service_id: services[0]?.id ?? '', medecin_id: '', date_debut: today, date_fin: today, motif: '', action: '', confrere_id: '' };
    const [form, setForm] = useState(vide);
    const [applyForm, setApplyForm] = useState({ action: 'a_replanifier', confrere_id: '' });
    const medecins = services.find((s) => String(s.id) === String(form.service_id))?.medecins ?? [];
    const medecinsApply = services.find((s) => s.id === apply?.service_id)?.medecins.filter((m) => m.id !== apply?.medecin_id) ?? [];
    const btn = 'whitespace-nowrap rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800';

    const ActionSelect = ({ value, onChange, confrere, onConfrere, list }) => (
        <>
            <Field label={t('absence.action')} error={te(errors.action)} htmlFor="action">
                <Select id="action" value={value} onChange={(e) => onChange(e.target.value)}>
                    <option value="">{t('absence.action_none')}</option>
                    {actions.map((a) => <option key={a} value={a}>{t(`enum.action_planning.${a}`)}</option>)}
                </Select>
            </Field>
            {value === 'reaffecter_confrere' && (
                <Field label={t('absence.confrere')} htmlFor="confrere">
                    <Select id="confrere" value={confrere} onChange={(e) => onConfrere(e.target.value)}>
                        <option value="">—</option>
                        {list.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                    </Select>
                </Field>
            )}
        </>
    );

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('absence.title')}</h2>}>
            <Head title={t('absence.title')} />
            <div className="mx-auto max-w-5xl space-y-4">
                <p className="rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:bg-primary-500/10 dark:text-primary-200">{t('absence.hint')}</p>
                <div className="flex justify-end"><AddButton onClick={() => { setForm(vide); setOpen(true); }}>{t('absence.add')}</AddButton></div>
                <DataTable rows={absences} empty={t('absence.empty')} columns={[
                    { key: 'service', label: t('rdv.service'), render: (a) => (locale === 'ar' && a.service.nom_ar ? a.service.nom_ar : a.service.nom) },
                    { key: 'medecin', label: t('rdv.medecin'), render: (a) => a.medecin ?? <span className="text-gray-500">{t('absence.tout_service')}</span> },
                    { key: 'periode', label: t('absence.periode'), render: (a) => <span dir="ltr">{a.date_debut} → {a.date_fin}</span> },
                    { key: 'motif', label: t('absence.motif') },
                    { key: 'action', label: t('absence.action'), render: (a) => (a.action ? <Badge tone="green">{t(`enum.action_planning.${a.action}`)}</Badge> : <Badge tone="gray">{t('absence.aucune')}</Badge>) },
                    { key: 'affectes', label: t('absence.affectes'), render: (a) => (a.affectes > 0 ? <Badge tone="amber">{a.affectes}</Badge> : '0') },
                    { key: 'a', label: '', render: (a) => (
                        <div className="flex justify-end gap-1">
                            {a.affectes > 0 && <button type="button" className={btn} onClick={() => { setApply(a); setApplyForm({ action: 'a_replanifier', confrere_id: '' }); }}>{t('absence.basculer')}</button>}
                            <button type="button" className={btn} onClick={() => window.confirm(t('common.confirm_delete')) && router.delete(route('parametrage.absences.destroy', a.id), { preserveScroll: true })}>{t('common.delete')}</button>
                        </div>
                    ) },
                ]} />
            </div>

            <Modal show={open} onClose={() => setOpen(false)} maxWidth="lg">
                <form className="space-y-4 p-6" onSubmit={(e) => { e.preventDefault(); router.post(route('parametrage.absences.store'), form, { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
                    <h3 className="text-base font-semibold">{t('absence.add')}</h3>
                    <Field label={t('rdv.service')} error={errors.service_id} htmlFor="service">
                        <Select id="service" value={form.service_id} onChange={(e) => setForm({ ...form, service_id: e.target.value, medecin_id: '' })}>
                            {services.map((s) => <option key={s.id} value={s.id}>{locale === 'ar' && s.nom_ar ? s.nom_ar : s.nom}</option>)}
                        </Select>
                    </Field>
                    <Field label={t('rdv.medecin')} error={te(errors.medecin_id)} htmlFor="medecin">
                        <Select id="medecin" value={form.medecin_id} onChange={(e) => setForm({ ...form, medecin_id: e.target.value })}>
                            <option value="">{t('absence.tout_service')}</option>
                            {medecins.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </Select>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('absence.du')} error={errors.date_debut} htmlFor="du"><TextInput id="du" type="date" dir="ltr" className="w-full" value={form.date_debut} onChange={(e) => setForm({ ...form, date_debut: e.target.value })} /></Field>
                        <Field label={t('absence.au')} error={errors.date_fin} htmlFor="au"><TextInput id="au" type="date" dir="ltr" className="w-full" value={form.date_fin} onChange={(e) => setForm({ ...form, date_fin: e.target.value })} /></Field>
                    </div>
                    <Field label={t('absence.motif')} error={errors.motif} htmlFor="motif"><TextInput id="motif" className="w-full" value={form.motif} onChange={(e) => setForm({ ...form, motif: e.target.value })} /></Field>
                    <ActionSelect value={form.action} onChange={(v) => setForm({ ...form, action: v })} confrere={form.confrere_id} onConfrere={(v) => setForm({ ...form, confrere_id: v })} list={medecins.filter((m) => String(m.id) !== String(form.medecin_id))} />
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setOpen(false)} className={btn}>{t('common.cancel')}</button>
                        <button type="submit" className="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">{t('common.save')}</button>
                    </div>
                </form>
            </Modal>

            <Modal show={apply !== null} onClose={() => setApply(null)} maxWidth="md">
                <form className="space-y-4 p-6" onSubmit={(e) => { e.preventDefault(); router.post(route('parametrage.absences.appliquer', apply.id), applyForm, { preserveScroll: true, onSuccess: () => setApply(null) }); }}>
                    <h3 className="text-base font-semibold">{t('absence.basculer')} — {t('absence.n_rdv', { n: apply?.affectes })}</h3>
                    <ActionSelect value={applyForm.action} onChange={(v) => setApplyForm({ ...applyForm, action: v })} confrere={applyForm.confrere_id} onConfrere={(v) => setApplyForm({ ...applyForm, confrere_id: v })} list={medecinsApply} />
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setApply(null)} className={btn}>{t('common.cancel')}</button>
                        <button type="submit" className="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">{t('absence.appliquer')}</button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
