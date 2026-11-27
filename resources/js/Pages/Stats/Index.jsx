import Field from '@/Components/Field';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import DataTable from '@/Components/DataTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function Kpi({ label, value, sub, tone = '' }) {
    return (
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p>
            <p className={`mt-1 text-3xl font-bold ${tone}`} dir="ltr">{value}</p>
            {sub && <p className="text-xs text-gray-500 dark:text-gray-400">{sub}</p>}
        </div>
    );
}

/** Barres horizontales (SVG/CSS, sans dépendance) : une ligne par valeur. */
function Bars({ rows }) {
    const max = Math.max(1, ...rows.map((r) => r.value));
    return (
        <ul className="space-y-2">
            {rows.map((r) => (
                <li key={r.label} className="text-sm">
                    <div className="flex justify-between"><span>{r.label}</span><span className="font-semibold" dir="ltr">{r.value}</span></div>
                    <div className="mt-1 h-2.5 rounded-full bg-gray-100 dark:bg-gray-800"><div className="h-2.5 rounded-full bg-primary-500" style={{ width: `${(r.value / max) * 100}%` }} /></div>
                </li>
            ))}
        </ul>
    );
}

/** Histogramme par jour : prévus (barre claire) et réalisés (barre pleine). */
function Daily({ jours, t }) {
    const max = Math.max(1, ...jours.map((j) => j.prevus));
    const w = Math.max(10, Math.min(36, Math.floor(720 / Math.max(1, jours.length))));
    return (
        <div>
            <div className="flex items-end gap-1 overflow-x-auto pb-1" style={{ height: 160 }} dir="ltr">
                {jours.map((j) => (
                    <div key={j.date} title={`${j.date} — ${t('stats.prevus')} ${j.prevus} · ${t('stats.realises')} ${j.realises} · ${t('stats.absents')} ${j.absents}`} className="flex flex-col items-center justify-end" style={{ width: w, height: '100%' }}>
                        <div className="relative w-full rounded-t bg-primary-200 dark:bg-primary-900" style={{ height: `${(j.prevus / max) * 100}%` }}>
                            <div className="absolute bottom-0 w-full rounded-t bg-primary-600" style={{ height: `${j.prevus ? (j.realises / j.prevus) * 100 : 0}%` }} />
                        </div>
                    </div>
                ))}
            </div>
            <p className="mt-2 flex gap-4 text-xs text-gray-500"><span><i className="me-1 inline-block h-2 w-2 rounded bg-primary-200" />{t('stats.prevus')}</span><span><i className="me-1 inline-block h-2 w-2 rounded bg-primary-600" />{t('stats.realises')}</span></p>
        </div>
    );
}

export default function Index({ stats, services, medecins, grades }) {
    const { t } = useTrans();
    const f = stats.filtres;
    const [form, setForm] = useState({ du: f.du, au: f.au, service_id: f.service_id ?? '', medecin_id: f.medecin_id ?? '', grade: f.grade ?? '' });
    const clean = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v !== null));
    const apply = (e) => { e.preventDefault(); router.get(route('stats.index'), clean(form), { preserveState: true }); };
    const qs = new URLSearchParams(clean(form)).toString();
    const v = stats.volume;
    const card = 'rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800';
    const meds = medecins.filter((m) => !form.service_id || String(m.service_id) === String(form.service_id));

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('stats.title')}</h2>}>
            <Head title={t('stats.title')} />
            <div className="mx-auto max-w-6xl space-y-6">
                <form onSubmit={apply} className={`${card} grid gap-3 sm:grid-cols-2 lg:grid-cols-6`}>
                    <Field label={t('absence.du')} htmlFor="du"><TextInput id="du" type="date" dir="ltr" className="w-full" value={form.du} onChange={(e) => setForm({ ...form, du: e.target.value })} /></Field>
                    <Field label={t('absence.au')} htmlFor="au"><TextInput id="au" type="date" dir="ltr" className="w-full" value={form.au} onChange={(e) => setForm({ ...form, au: e.target.value })} /></Field>
                    <Field label={t('stats.specialite')} htmlFor="sp">
                        <Select id="sp" value={form.service_id} onChange={(e) => setForm({ ...form, service_id: e.target.value, medecin_id: '' })}><option value="">{t('stats.tous')}</option>{services.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}</Select>
                    </Field>
                    <Field label={t('rdv.medecin')} htmlFor="md">
                        <Select id="md" value={form.medecin_id} onChange={(e) => setForm({ ...form, medecin_id: e.target.value })}><option value="">{t('stats.tous')}</option>{meds.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}</Select>
                    </Field>
                    <Field label={t('stats.grade')} htmlFor="gr">
                        <Select id="gr" value={form.grade} onChange={(e) => setForm({ ...form, grade: e.target.value })}><option value="">{t('stats.tous')}</option>{grades.map((g) => <option key={g} value={g}>{t(`enum.grade.${g}`)}</option>)}</Select>
                    </Field>
                    <div className="flex items-end gap-2"><button type="submit" className="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{t('stats.appliquer')}</button></div>
                </form>

                <div className="flex flex-wrap justify-end gap-2">
                    <a href={`${route('stats.pdf')}?${qs}`} target="_blank" rel="noreferrer" className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('stats.export_pdf')}</a>
                    <a href={`${route('stats.excel')}?${qs}`} className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('stats.export_excel')}</a>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Kpi label={t('stats.prevus')} value={v.prevus} />
                    <Kpi label={t('stats.realises')} value={v.realises} tone="text-green-600 dark:text-green-400" />
                    <Kpi label={t('stats.absents')} value={v.absents} sub={`${v.taux_absence} %`} tone="text-amber-600 dark:text-amber-400" />
                    <Kpi label={t('stats.reportes')} value={v.reportes} />
                    <Kpi label={t('stats.attente')} value={stats.attente_moyenne !== null ? `${stats.attente_moyenne} min` : '—'} sub={t('stats.attente_sub')} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className={`${card} lg:col-span-2`}><h3 className="mb-3 text-sm font-semibold">{t('stats.par_jour')}</h3>{stats.par_jour.length ? <Daily jours={stats.par_jour} t={t} /> : <p className="text-sm text-gray-500">{t('stats.aucune')}</p>}</section>
                    <section className={card}><h3 className="mb-3 text-sm font-semibold">{t('stats.repartition')}</h3>
                        <Bars rows={[['premiere_consultation', 'stats.premieres'], ['controle', 'stats.controles'], ['retour_bilan', 'stats.bilans']].map(([k, l]) => ({ label: t(l), value: stats.repartition[k] }))} />
                        <h3 className="mb-3 mt-6 text-sm font-semibold">{t('stats.seniors_residents')}</h3>
                        <Bars rows={[{ label: t('stats.seniors'), value: stats.seniors_residents.seniors }, { label: t('stats.residents'), value: stats.seniors_residents.residents }]} />
                    </section>
                </div>

                <section className="space-y-2"><h3 className="text-sm font-semibold">{t('stats.charge')}</h3>
                    <DataTable rows={stats.charge} rowKey={(r) => r.medecin_id} empty={t('stats.aucune')} columns={[
                        { key: 'medecin', label: t('rdv.medecin') }, { key: 'grade', label: t('stats.grade'), render: (r) => t(`enum.grade.${r.grade}`) }, { key: 'service', label: t('rdv.service') },
                        { key: 'total', label: t('stats.prevus') }, { key: 'realises', label: t('stats.realises') }, { key: 'attente', label: t('stats.attente'), render: (r) => (r.attente_moyenne !== null ? `${r.attente_moyenne} min` : '—') },
                    ]} />
                </section>
                <section className="space-y-2"><h3 className="text-sm font-semibold">{t('stats.delais')}</h3>
                    <DataTable rows={stats.delais} rowKey={(r) => r.service} empty={t('stats.aucune')} columns={[
                        { key: 'service', label: t('rdv.service') }, { key: 'date', label: t('stats.prochaine'), render: (r) => <span dir="ltr">{r.date ?? '—'}</span> }, { key: 'jours', label: t('stats.jours'), render: (r) => r.jours ?? '—' },
                    ]} />
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
