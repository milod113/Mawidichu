import Badge from '@/Components/Badge';
import CheckField from '@/Components/CheckField';
import Field, { inputClass } from '@/Components/Field';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

function formatDate(value, locale) {
    return new Date(`${value}T00:00:00Z`).toLocaleDateString(locale === 'ar' ? 'ar-DZ-u-nu-latn' : 'fr-FR', {
        weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC',
    });
}

export default function Create({ patient, services, selection, entries, priorites, verrouSecondes }) {
    const { t, locale } = useTrans();
    const { errors } = usePage().props;

    const [serviceId, setServiceId] = useState(selection.service ?? services[0]?.id ?? '');
    const service = services.find((s) => s.id === Number(serviceId));
    const [typeId, setTypeId] = useState(selection.type ?? '');
    const [medecinId, setMedecinId] = useState(selection.medecin ?? '');
    const [priorite, setPriorite] = useState(selection.priorite ?? 'normal');
    const [motif, setMotif] = useState('');
    const [surbooking, setSurbooking] = useState(false);
    const [selected, setSelected] = useState(null); // { entry, heure }
    const [secondes, setSecondes] = useState(0);
    const [notice, setNotice] = useState(null);
    const [processing, setProcessing] = useState(false);

    const query = useCallback((over = {}) => ({
        patient: patient?.id, type: typeId || undefined, medecin: medecinId || undefined, priorite, ...over,
    }), [patient, typeId, medecinId, priorite]);

    const load = (over = {}) => {
        setSelected(null);
        router.get(route('accueil.rendez-vous.create'), query(over), { preserveState: true, preserveScroll: true, only: ['entries', 'selection'] });
    };

    // Temps réel : une réservation ailleurs rafraîchit les places restantes.
    useEffect(() => {
        if (!window.Echo || !service) return undefined;
        const channel = window.Echo.private(`service.${service.id}`).listen('.service.activity', () => {
            router.reload({ only: ['entries'], preserveScroll: true });
        });
        return () => window.Echo.leave(`service.${service.id}`);
    }, [service?.id]);

    // Compte à rebours du verrou de 60 s.
    useEffect(() => {
        if (!selected) return undefined;
        const timer = setInterval(() => {
            setSecondes((s) => {
                if (s <= 1) {
                    clearInterval(timer);
                    setSelected(null);
                    setNotice(t('rdv.lock_expired'));
                    return 0;
                }
                return s - 1;
            });
        }, 1000);
        return () => clearInterval(timer);
    }, [selected, t]);

    const type = service?.types.find((x) => x.id === Number(typeId));

    const choose = async (entry, heure = null) => {
        setNotice(null);
        try {
            const { data } = await window.axios.post(route('accueil.rendez-vous.verrou'), {
                type_consultation_id: type.id, planning_id: entry.planning_id, date: entry.date, heure, priorite,
            });
            setSecondes(data.ttl ?? verrouSecondes);
            setSelected({ entry, heure });
            setSurbooking(false);
        } catch {
            setNotice(t('rdv.lock_failed'));
            setSelected(null);
            router.reload({ only: ['entries'], preserveScroll: true });
        }
    };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(route('accueil.rendez-vous.store'), {
            patient_id: patient.id, type_consultation_id: type.id, planning_id: selected.entry.planning_id,
            date: selected.entry.date, heure: selected.heure, priorite, motif_priorite: motif || null, surbooking,
        }, { onFinish: () => setProcessing(false) });
    };

    const needSurbooking = selected && selected.entry.slots === null && selected.entry.remaining === 0;
    const motifManquant = priorite !== 'normal' && !motif.trim();

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('rdv.title')}</h2>}>
            <Head title={t('rdv.title')} />
            <div className="mx-auto max-w-5xl space-y-5">
                <section className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    {patient ? (
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p><span className="text-sm text-gray-500 dark:text-gray-400">{t('rdv.patient')} : </span><strong>{patient.nom.toUpperCase()} {patient.prenom}</strong> <span className="font-mono text-xs" dir="ltr">({patient.ipp})</span></p>
                            <Link href={route('accueil.patients.index')} className="text-sm text-primary-700 hover:underline dark:text-primary-300">{t('rdv.change_patient')}</Link>
                        </div>
                    ) : (
                        <p className="text-sm">{t('rdv.pick_patient')} <Link href={route('accueil.patients.index')} className="font-medium text-primary-700 hover:underline dark:text-primary-300">{t('nav.patients')}</Link></p>
                    )}
                </section>

                {patient && (services.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm dark:border-gray-700">{t('rdv.none_types')}</p>
                ) : (
                    <>
                        <section className="grid gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2 lg:grid-cols-4 dark:bg-gray-900 dark:ring-gray-800">
                            <Field label={t('rdv.service')} htmlFor="service">
                                <Select id="service" value={serviceId} onChange={(e) => { setServiceId(e.target.value); setTypeId(''); setMedecinId(''); setSelected(null); }}>
                                    {services.map((s) => <option key={s.id} value={s.id}>{locale === 'ar' && s.nom_ar ? s.nom_ar : s.nom}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('rdv.type')} htmlFor="type">
                                <Select id="type" value={typeId} onChange={(e) => { setTypeId(e.target.value); load({ type: e.target.value || undefined }); }}>
                                    <option value="">{t('planning.none_target')}</option>
                                    {service?.types.map((x) => <option key={x.id} value={x.id}>{x.libelle} ({x.duree_minutes} min)</option>)}
                                </Select>
                            </Field>
                            <Field label={t('rdv.medecin')} htmlFor="medecin">
                                <Select id="medecin" value={medecinId} onChange={(e) => { setMedecinId(e.target.value); load({ medecin: e.target.value || undefined }); }}>
                                    <option value="">{t('rdv.any_medecin')}</option>
                                    {service?.medecins.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('rdv.priorite')} htmlFor="priorite">
                                <Select id="priorite" value={priorite} onChange={(e) => { setPriorite(e.target.value); load({ priorite: e.target.value }); }}>
                                    {priorites.map((p) => <option key={p} value={p}>{t(`enum.priorite.${p}`)}</option>)}
                                </Select>
                            </Field>
                        </section>

                        {notice && <p role="alert" className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">{notice}</p>}
                        <InputError message={errors.booking} />

                        {type && (
                            <section className="space-y-3" aria-label={t('rdv.search')}>
                                {entries.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{t('rdv.no_availability')}</p>
                                ) : entries.map((e) => {
                                    const isSel = selected?.entry.key === e.key;
                                    const complet = !e.bookable;
                                    return (
                                        <article key={e.key} className={`rounded-xl p-4 ring-1 ${isSel ? 'bg-primary-50 ring-primary-500 dark:bg-primary-500/10' : 'bg-white ring-gray-200 dark:bg-gray-900 dark:ring-gray-800'}`}>
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-semibold capitalize">{formatDate(e.date, locale)}</p>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                        <span dir="ltr">{e.debut} – {e.fin}</span> · {e.medecin_nom ?? e.box_nom}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Badge tone={complet ? 'gray' : e.remaining > 0 ? 'green' : 'amber'}>
                                                        {complet ? t('rdv.full') : e.remaining > 0 ? `${e.remaining} ${t('rdv.places_left')}` : `${e.surbooking_remaining} ${t('rdv.surbooking')}`}
                                                    </Badge>
                                                    {e.slots === null && !complet && (
                                                        <button type="button" onClick={() => choose(e)} className="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">
                                                            {isSel ? t('rdv.chosen') : t('rdv.choose')}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                            {e.slots !== null && e.slots.length > 0 && (
                                                <div className="mt-3 flex flex-wrap gap-2" role="group" aria-label={t('rdv.pick_slot')}>
                                                    {e.slots.map((h) => (
                                                        <button key={h} type="button" dir="ltr" onClick={() => choose(e, h)}
                                                            className={`rounded-md border px-2.5 py-1 text-sm ${isSel && selected.heure === h ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'}`}>
                                                            {h}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                            {isSel && e.slots === null && <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{t('rdv.vague')} : <span dir="ltr">{e.next_time}</span></p>}
                                        </article>
                                    );
                                })}
                            </section>
                        )}

                        {selected && (
                            <form onSubmit={submit} className="sticky bottom-4 space-y-3 rounded-xl bg-white p-5 shadow-lg ring-1 ring-primary-500 dark:bg-gray-900">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="font-semibold capitalize">{formatDate(selected.entry.date, locale)} · <span dir="ltr">{selected.heure ?? selected.entry.next_time}</span></p>
                                    <Badge tone={secondes <= 15 ? 'amber' : 'teal'}>{t('rdv.lock', { s: secondes })}</Badge>
                                </div>
                                {priorite !== 'normal' && (
                                    <Field label={t('rdv.motif_priorite')} error={errors.motif_priorite} htmlFor="motif">
                                        <textarea id="motif" rows={2} className={inputClass} value={motif} onChange={(e) => setMotif(e.target.value)} />
                                    </Field>
                                )}
                                {needSurbooking && <CheckField id="surbooking" label={t('rdv.surbooking_confirm')} checked={surbooking} onChange={setSurbooking} />}
                                <PrimaryButton disabled={processing || motifManquant || (needSurbooking && !surbooking)}>{t('rdv.confirm')}</PrimaryButton>
                            </form>
                        )}
                    </>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
