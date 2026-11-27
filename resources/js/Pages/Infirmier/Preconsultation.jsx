import Badge from '@/Components/Badge';
import Field from '@/Components/Field';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const CHAMPS = [
    ['tension_sys', 'pre.tension_sys', '1'], ['tension_dia', 'pre.tension_dia', '1'], ['frequence_cardiaque', 'pre.fc', '1'],
    ['poids', 'pre.poids', '0.1'], ['taille', 'pre.taille', '0.1'], ['temperature', 'pre.temperature', '0.1'], ['glycemie', 'pre.glycemie', '0.01'],
];

export default function Preconsultation({ services, service, rdvs }) {
    const { t, te, locale } = useTrans();
    const { errors } = usePage().props;
    const [sel, setSel] = useState(null);
    const [data, setData] = useState({});
    const current = rdvs.find((r) => r.id === sel);

    useEffect(() => {
        const timer = setInterval(() => router.reload({ only: ['rdvs'] }), 20000);
        return () => clearInterval(timer);
    }, []);

    const choose = (r) => { setSel(r.id); setData({ ...(r.constantes ?? {}) }); if (r.statut === 'arrive') router.post(route('infirmier.preconsultation.demarrer', r.id), {}, { preserveScroll: true }); };
    const submit = (e) => {
        e.preventDefault();
        router.post(route('infirmier.preconsultation.store', current.id), data, { preserveScroll: true, onSuccess: () => setSel(null) });
    };

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('pre.title')}</h2>}>
            <Head title={t('pre.title')} />
            <div className="mx-auto max-w-5xl space-y-4">
                {errors.file && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{te(errors.file)}</p>}
                {services.length === 0 && <p className="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">{t('pre.no_service')}</p>}
                {services.length > 1 && (
                    <Select value={service ?? ''} onChange={(e) => router.get(route('infirmier.preconsultation.index'), { service: e.target.value })} className="max-w-xs">
                        {services.map((s) => <option key={s.id} value={s.id}>{locale === 'ar' && s.nom_ar ? s.nom_ar : s.nom}</option>)}
                    </Select>
                )}
                <div className="grid gap-6 lg:grid-cols-5">
                    <ul className="space-y-2 lg:col-span-2">
                        {rdvs.length === 0 && service && <li className="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">{t('pre.empty')}</li>}
                        {rdvs.map((r) => (
                            <li key={r.id}>
                                <button type="button" onClick={() => choose(r)} className={`w-full rounded-lg p-3 text-start ring-1 ${sel === r.id ? 'bg-primary-50 ring-primary-500 dark:bg-primary-500/10' : 'bg-white ring-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:ring-gray-800'}`}>
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-mono font-semibold" dir="ltr">{r.numero_ticket}</span>
                                        <Badge tone={r.statut === 'pret_medecin' ? 'green' : 'amber'}>{t(`enum.statut_rdv.${r.statut}`)}</Badge>
                                    </div>
                                    <p className="mt-1 text-sm">{r.patient.nom.toUpperCase()} {r.patient.prenom}</p>
                                    <p className="text-xs text-gray-500">{r.type} · <span dir="ltr">{r.checkin}</span>{r.priorite !== 'normal' ? ` · ${t(`enum.priorite.${r.priorite}`)}` : ''}</p>
                                </button>
                            </li>
                        ))}
                    </ul>
                    <form onSubmit={submit} className="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800 lg:col-span-3">
                        {!current ? <p className="text-sm text-gray-500">{t('pre.select')}</p> : (
                            <>
                                <h3 className="font-semibold">{current.patient.nom.toUpperCase()} {current.patient.prenom} <span className="font-mono text-xs text-gray-500" dir="ltr">{current.patient.ipp}</span></h3>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {CHAMPS.map(([k, label, step]) => (
                                        <Field key={k} label={t(label)} error={errors[k]} htmlFor={k}>
                                            <TextInput id={k} type="number" step={step} dir="ltr" className="w-full" value={data[k] ?? ''} onChange={(e) => setData({ ...data, [k]: e.target.value })} />
                                        </Field>
                                    ))}
                                </div>
                                <Field label={t('pre.notes')} htmlFor="notes">
                                    <textarea id="notes" rows="3" className="block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" value={data.notes_ecg_soins ?? ''} onChange={(e) => setData({ ...data, notes_ecg_soins: e.target.value })} />
                                </Field>
                                <button type="submit" className="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{t('pre.done')}</button>
                            </>
                        )}
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
