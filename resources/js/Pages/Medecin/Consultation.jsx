import Badge from '@/Components/Badge';
import Field from '@/Components/Field';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const area = 'block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
const btn = 'rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800';
const primary = 'rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700';

function Card({ title, children }) {
    return (
        <section className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            {children}
        </section>
    );
}

export default function Consultation({ consultation: c, patient, constantes, precedentes, avis, documents, editable, favoris, bilansCourants, services, seniors }) {
    const { t, te } = useTrans();
    const { errors } = usePage().props;
    const [f, setF] = useState({
        motif_reel: c.motif_reel ?? '', examen_clinique: c.examen_clinique ?? '', diagnostic: c.diagnostic ?? '', conduite_a_tenir: c.conduite_a_tenir ?? '',
        ordonnance: c.ordonnance, bilans: c.bilans, delai_controle_jours: c.delai_controle_jours ?? '',
    });
    const [libre, setLibre] = useState('');
    const [controle, setControle] = useState('assiste');
    const [dispos, setDispos] = useState([]);
    const [choix, setChoix] = useState('');
    const [avisForm, setAvisForm] = useState({ type_avis: 'senior_interne', medecin_cible_id: '', service_cible_id: '', motif: '' });
    const [orient, setOrient] = useState({ service_id: services[0]?.id ?? '', motif: '' });
    const set = (k, v) => setF((o) => ({ ...o, [k]: v }));
    const ro = !editable;

    useEffect(() => {
        if (controle !== 'direct') return;
        fetch(route('medecin.consultations.disponibilites', c.id), { headers: { Accept: 'application/json' } }).then((r) => r.json()).then(setDispos);
    }, [controle, c.id]);

    const ligne = (i, k, v) => set('ordonnance', f.ordonnance.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const toggleBilan = (b) => set('bilans', f.bilans.includes(b) ? f.bilans.filter((x) => x !== b) : [...f.bilans, b]);

    const save = (cloturer) => {
        const payload = { ...f, delai_controle_jours: f.delai_controle_jours || null, cloturer };
        if (cloturer && controle === 'direct' && choix !== '') {
            const d = dispos[Number(choix)];
            payload.controle_direct = { planning_id: d.planning_id, date: d.date, heure: d.heure };
        }
        router.put(route('medecin.consultations.update', c.id), payload, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{patient.nom.toUpperCase()} {patient.prenom} <span className="font-mono text-xs text-gray-500" dir="ltr">{patient.ipp}</span></h2>}>
            <Head title={t('consult.title')} />
            <div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {errors.consultation && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{te(errors.consultation)}</p>}
                    {ro && <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">{t('consult.readonly')}</p>}

                    <Card title={t('consult.clinique')}>
                        <div className="space-y-3">
                            {[['motif_reel', 'consult.motif', 2], ['examen_clinique', 'consult.examen', 4], ['diagnostic', 'consult.diagnostic', 2], ['conduite_a_tenir', 'consult.conduite', 3]].map(([k, label, rows]) => (
                                <Field key={k} label={t(label)} error={errors[k]} htmlFor={k}>
                                    <textarea id={k} rows={rows} disabled={ro} className={area} value={f[k]} onChange={(e) => set(k, e.target.value)} />
                                </Field>
                            ))}
                        </div>
                    </Card>

                    <Card title={t('consult.ordonnance')}>
                        {!ro && favoris.length > 0 && (
                            <div className="mb-3 flex flex-wrap gap-1">
                                <span className="me-1 text-xs text-gray-500">{t('consult.favoris')} :</span>
                                {favoris.map((m, i) => <button key={i} type="button" onClick={() => set('ordonnance', [...f.ordonnance, { ...m }])} className="rounded-full bg-primary-50 px-2.5 py-0.5 text-xs text-primary-700 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300">+ {m.medicament}</button>)}
                            </div>
                        )}
                        <div className="space-y-2">
                            {f.ordonnance.map((l, i) => (
                                <div key={i} className="grid gap-2 sm:grid-cols-12">
                                    <TextInput disabled={ro} className="sm:col-span-5" placeholder={t('consult.medicament')} value={l.medicament ?? ''} onChange={(e) => ligne(i, 'medicament', e.target.value)} />
                                    <TextInput disabled={ro} className="sm:col-span-4" placeholder={t('consult.posologie')} value={l.posologie ?? ''} onChange={(e) => ligne(i, 'posologie', e.target.value)} />
                                    <TextInput disabled={ro} className="sm:col-span-2" placeholder={t('consult.duree')} value={l.duree ?? ''} onChange={(e) => ligne(i, 'duree', e.target.value)} />
                                    {!ro && <button type="button" onClick={() => set('ordonnance', f.ordonnance.filter((_, j) => j !== i))} className="text-red-600 sm:col-span-1" aria-label={t('common.delete')}>✕</button>}
                                </div>
                            ))}
                        </div>
                        {!ro && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                <button type="button" className={btn} onClick={() => set('ordonnance', [...f.ordonnance, { medicament: '', posologie: '', duree: '' }])}>{t('consult.add_line')}</button>
                                <button type="button" className={btn} onClick={() => router.put(route('medecin.favoris'), { favoris: [...favoris, ...f.ordonnance.filter((l) => l.medicament && !favoris.some((x) => x.medicament === l.medicament))] }, { preserveScroll: true })}>{t('consult.save_favoris')}</button>
                            </div>
                        )}
                    </Card>

                    <Card title={t('consult.bilans')}>
                        <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                            {[...bilansCourants, ...f.bilans.filter((b) => !bilansCourants.includes(b))].map((b) => (
                                <label key={b} className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" disabled={ro} checked={f.bilans.includes(b)} onChange={() => toggleBilan(b)} className="rounded border-gray-300 text-primary-600" />{b}
                                </label>
                            ))}
                        </div>
                        {!ro && (
                            <div className="mt-3 flex gap-2">
                                <TextInput className="max-w-xs" placeholder={t('consult.bilan_libre')} value={libre} onChange={(e) => setLibre(e.target.value)} />
                                <button type="button" className={btn} onClick={() => { if (libre.trim()) { set('bilans', [...f.bilans, libre.trim()]); setLibre(''); } }}>{t('common.add')}</button>
                            </div>
                        )}
                    </Card>

                    <Card title={t('consult.controle')}>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('consult.delai')} error={errors.delai_controle_jours} htmlFor="delai">
                                <TextInput id="delai" type="number" min="1" disabled={ro} dir="ltr" className="w-full" value={f.delai_controle_jours} onChange={(e) => set('delai_controle_jours', e.target.value)} />
                            </Field>
                            {!ro && (
                                <Field label={t('consult.controle_mode')} htmlFor="mode">
                                    <Select id="mode" value={controle} onChange={(e) => setControle(e.target.value)}>
                                        <option value="assiste">{t('consult.controle_assiste')}</option>
                                        <option value="direct">{t('consult.controle_direct')}</option>
                                    </Select>
                                </Field>
                            )}
                        </div>
                        {c.controle_rdv_id && <p className="mt-2 text-sm text-green-700 dark:text-green-300">{t('consult.controle_pris')}</p>}
                        {!ro && controle === 'direct' && (
                            <Field className="mt-3" label={t('consult.choisir_date')} htmlFor="dispo">
                                <Select id="dispo" value={choix} onChange={(e) => setChoix(e.target.value)}>
                                    <option value="">{dispos.length ? '—' : t('consult.aucune_dispo')}</option>
                                    {dispos.map((d, i) => <option key={i} value={i}>{d.date} {d.heure ?? d.debut} ({d.restantes})</option>)}
                                </Select>
                            </Field>
                        )}
                    </Card>

                    {!ro && (
                        <div className="flex flex-wrap justify-end gap-2">
                            <button type="button" className={btn} onClick={() => save(false)}>{t('consult.save_draft')}</button>
                            <button type="button" className={primary} onClick={() => window.confirm(t('consult.confirm_close')) && save(true)}>{t('consult.close')}</button>
                        </div>
                    )}
                </div>

                <aside className="space-y-6">
                    <Card title={t('consult.constantes')}>
                        {constantes ? (
                            <dl className="grid grid-cols-2 gap-2 text-sm">
                                {[['pre.tension_sys', constantes.tension_sys], ['pre.tension_dia', constantes.tension_dia], ['pre.fc', constantes.frequence_cardiaque], ['pre.poids', constantes.poids], ['pre.taille', constantes.taille], ['pre.temperature', constantes.temperature], ['pre.glycemie', constantes.glycemie]].filter(([, v]) => v !== null).map(([k, v]) => (
                                    <div key={k}><dt className="text-xs text-gray-500">{t(k)}</dt><dd dir="ltr">{v}</dd></div>
                                ))}
                                {constantes.notes_ecg_soins && <p className="col-span-2 text-xs">{constantes.notes_ecg_soins}</p>}
                            </dl>
                        ) : <p className="text-sm text-gray-500">{t('consult.pas_constantes')}</p>}
                    </Card>

                    <Card title={t('consult.documents')}>
                        <div className="flex flex-wrap gap-2">
                            {['ordonnance', 'bilan'].map((p) => <a key={p} className={btn} target="_blank" rel="noreferrer" href={route('medecin.consultations.pdf', [c.id, p])}>{t(`consult.pdf_${p}`)}</a>)}
                            <a className={btn} target="_blank" rel="noreferrer" href={route('accueil.rendez-vous.certificat', c.rdv_id)}>{t('consult.pdf_certificat')}</a>
                        </div>
                        {services.length > 0 && (
                            <div className="mt-3 space-y-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                                <Select value={orient.service_id} onChange={(e) => setOrient({ ...orient, service_id: e.target.value })}>{services.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}</Select>
                                <TextInput className="w-full" placeholder={t('avis.motif')} value={orient.motif} onChange={(e) => setOrient({ ...orient, motif: e.target.value })} />
                                <a className={btn} target="_blank" rel="noreferrer" href={route('medecin.consultations.pdf', [c.id, 'orientation']) + `?service_id=${orient.service_id}&motif=${encodeURIComponent(orient.motif)}`}>{t('consult.pdf_orientation')}</a>
                            </div>
                        )}
                        {documents.length > 0 && <ul className="mt-3 space-y-1 text-sm">{documents.map((d) => <li key={d.id}><a className="text-primary-700 hover:underline dark:text-primary-300" href={route('dossier.documents.download', d.id)}>{d.titre}</a></li>)}</ul>}
                    </Card>

                    <Card title={t('avis.title')}>
                        <ul className="space-y-2 text-sm">
                            {avis.map((a) => (
                                <li key={a.id} className="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                                    <div className="flex items-center justify-between"><span className="font-medium">{a.cible}</span><Badge tone={a.statut === 'repondu' ? 'green' : 'amber'}>{t(`enum.statut_avis.${a.statut}`)}</Badge></div>
                                    <p className="text-xs text-gray-600 dark:text-gray-400">{a.motif}</p>
                                    {a.reponse && <p className="mt-1 text-xs">↳ {a.reponse}</p>}
                                </li>
                            ))}
                        </ul>
                        {!ro && (
                            <form className="mt-3 space-y-2" onSubmit={(e) => { e.preventDefault(); router.post(route('medecin.avis.store', c.id), avisForm, { preserveScroll: true, onSuccess: () => setAvisForm({ ...avisForm, motif: '' }) }); }}>
                                <Select value={avisForm.type_avis} onChange={(e) => setAvisForm({ ...avisForm, type_avis: e.target.value })}>
                                    <option value="senior_interne">{t('enum.type_avis.senior_interne')}</option>
                                    <option value="inter_specialite">{t('enum.type_avis.inter_specialite')}</option>
                                </Select>
                                {avisForm.type_avis === 'senior_interne' ? (
                                    <Select value={avisForm.medecin_cible_id} onChange={(e) => setAvisForm({ ...avisForm, medecin_cible_id: e.target.value })}>
                                        <option value="">—</option>{seniors.map((m) => <option key={m.id} value={m.id}>{m.name} ({m.grade})</option>)}
                                    </Select>
                                ) : (
                                    <Select value={avisForm.service_cible_id} onChange={(e) => setAvisForm({ ...avisForm, service_cible_id: e.target.value })}>
                                        <option value="">—</option>{services.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}
                                    </Select>
                                )}
                                <textarea rows="2" className={area} placeholder={t('avis.motif')} value={avisForm.motif} onChange={(e) => setAvisForm({ ...avisForm, motif: e.target.value })} />
                                {(errors.medecin_cible_id || errors.service_cible_id || errors.motif) && <p className="text-xs text-red-600">{te(errors.medecin_cible_id || errors.service_cible_id || errors.motif)}</p>}
                                <button type="submit" className={btn}>{t('avis.send')}</button>
                            </form>
                        )}
                    </Card>

                    <Card title={t('consult.historique')}>
                        <ul className="space-y-2 text-sm">
                            {precedentes.length === 0 && <li className="text-gray-500">{t('consult.pas_historique')}</li>}
                            {precedentes.map((p) => <li key={p.id}><Link href={route('medecin.consultations.show', p.id)} className="text-primary-700 hover:underline dark:text-primary-300"><span dir="ltr">{p.date}</span> — {p.service}</Link>{p.diagnostic && <span className="block text-xs text-gray-500">{p.diagnostic}</span>}</li>)}
                        </ul>
                    </Card>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
