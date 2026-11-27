import Badge from '@/Components/Badge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';

export default function Show({ rdv }) {
    const { t, locale } = useTrans();
    const rows = [
        ['rdv.patient', `${rdv.patient.nom.toUpperCase()} ${rdv.patient.prenom} (${rdv.patient.ipp})`],
        ['rdv.service', locale === 'ar' && rdv.service.nom_ar ? rdv.service.nom_ar : rdv.service.nom],
        ['rdv.type', rdv.type.libelle], ['rdv.medecin', rdv.medecin], ['rdv.date', rdv.date_rdv], ['rdv.heure', rdv.heure], ['patient.wilaya', null],
    ].filter(([, v]) => v);

    const cancel = () => {
        if (window.confirm(t('common.confirm_delete'))) router.delete(route('accueil.rendez-vous.destroy', rdv.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('rdv.confirmed_title')}</h2>}>
            <Head title={rdv.numero_ticket} />
            <div className="mx-auto max-w-2xl space-y-6">
                <section className="rounded-xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <p className="text-sm text-gray-500 dark:text-gray-400">{t('rdv.ticket')}</p>
                    <p className="my-2 font-mono text-4xl font-bold tracking-wider" dir="ltr">{rdv.numero_ticket}</p>
                    <Badge tone="teal">{t(`enum.statut_rdv.${rdv.statut}`)}</Badge>
                    {rdv.priorite !== 'normal' && <span className="ms-2"><Badge tone="amber">{t(`enum.priorite.${rdv.priorite}`)}</Badge></span>}

                    <dl className="mt-6 space-y-2 text-start">
                        {rows.map(([k, v]) => (
                            <div key={k} className="flex justify-between gap-4 border-b border-gray-100 pb-2 text-sm dark:border-gray-800">
                                <dt className="text-gray-500 dark:text-gray-400">{t(k)}</dt>
                                <dd className="font-medium" dir="auto">{v}</dd>
                            </div>
                        ))}
                    </dl>

                    {rdv.type.bilans_requis_text && (
                        <div className="mt-4 rounded-lg bg-amber-50 p-3 text-start text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                            <p className="font-semibold">{t('rdv.bilans')}</p>
                            <p className="whitespace-pre-line">{rdv.type.bilans_requis_text}</p>
                        </div>
                    )}

                    <div className="mt-6 flex flex-wrap justify-center gap-3">
                        <a href={route('accueil.rendez-vous.ticket', [rdv.id, '80mm'])} target="_blank" rel="noreferrer" className="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{t('rdv.print_80')}</a>
                        <a href={route('accueil.rendez-vous.ticket', [rdv.id, 'a5'])} target="_blank" rel="noreferrer" className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('rdv.print_a5')}</a>
                    </div>
                </section>

                <div className="flex items-center justify-between text-sm">
                    <Link href={route('accueil.patients.show', rdv.patient.id)} className="text-primary-700 hover:underline dark:text-primary-300">{t('patient.history')}</Link>
                    <button type="button" onClick={cancel} className="text-red-600 hover:underline dark:text-red-400">{t('rdv.cancel')}</button>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
