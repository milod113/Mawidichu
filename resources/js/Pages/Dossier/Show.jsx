import Badge from '@/Components/Badge';
import Field from '@/Components/Field';
import Icon from '@/Components/Icon';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({ patient, consultations, documents, services, types, maxKb }) {
    const { t } = useTrans();
    const form = useForm({ service_id: services[0]?.id ?? '', type_document: 'autre', titre: '', fichier: null });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('dossier.documents.store', patient.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('titre', 'fichier') });
    };

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('dossier.title')} — {patient.nom.toUpperCase()} {patient.prenom}</h2>}>
            <Head title={t('dossier.title')} />
            <div className="mx-auto max-w-5xl space-y-6">
                <Link href={route('accueil.patients.show', patient.id)} className="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400"><Icon name="back" className="h-4 w-4 rtl:rotate-180" />{t('common.back')}</Link>
                <p className="rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:bg-primary-500/10 dark:text-primary-200">{t('dossier.cloison')}</p>

                <section className="space-y-3">
                    <h3 className="text-base font-semibold">{t('dossier.consultations')}</h3>
                    {consultations.length === 0 && <p className="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">{t('dossier.empty')}</p>}
                    {consultations.map((c) => (
                        <article key={c.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm font-semibold"><span dir="ltr">{c.date}</span> — {c.service} <span className="font-normal text-gray-500">({c.medecin})</span></p>
                                <Link href={route('medecin.consultations.show', c.id)} className="text-xs text-primary-700 hover:underline dark:text-primary-300">{t('dossier.open')}</Link>
                            </div>
                            {c.diagnostic && <p className="mt-2 text-sm"><span className="text-gray-500">{t('consult.diagnostic')} : </span>{c.diagnostic}</p>}
                            {c.conduite && <p className="mt-1 text-sm"><span className="text-gray-500">{t('consult.conduite')} : </span>{c.conduite}</p>}
                            {c.ordonnance.length > 0 && <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{c.ordonnance.map((o) => o.medicament).join(' · ')}</p>}
                            {c.bilans.length > 0 && <div className="mt-2 flex flex-wrap gap-1">{c.bilans.map((b) => <Badge key={b}>{b}</Badge>)}</div>}
                        </article>
                    ))}
                </section>

                <section className="space-y-3">
                    <h3 className="text-base font-semibold">{t('dossier.documents')}</h3>
                    <ul className="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-200 dark:divide-gray-800 dark:bg-gray-900 dark:ring-gray-800">
                        {documents.length === 0 && <li className="p-4 text-sm text-gray-500">{t('dossier.no_documents')}</li>}
                        {documents.map((d) => (
                            <li key={d.id} className="flex items-center justify-between gap-2 p-3 text-sm">
                                <a href={route('dossier.documents.download', d.id)} className="text-primary-700 hover:underline dark:text-primary-300">{d.titre}</a>
                                <span className="flex items-center gap-2 text-xs text-gray-500"><Badge>{t(`enum.type_document.${d.type}`)}</Badge>{d.service} · <span dir="ltr">{d.date}</span>
                                    <button type="button" className="text-red-600" onClick={() => window.confirm(t('common.confirm_delete')) && router.delete(route('dossier.documents.destroy', d.id), { preserveScroll: true })}>{t('common.delete')}</button></span>
                            </li>
                        ))}
                    </ul>
                    <form onSubmit={submit} className="grid gap-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800 sm:grid-cols-2">
                        <Field label={t('dossier.titre')} error={form.errors.titre} htmlFor="titre"><TextInput id="titre" className="w-full" value={form.data.titre} onChange={(e) => form.setData('titre', e.target.value)} /></Field>
                        <Field label={t('dossier.type')} error={form.errors.type_document} htmlFor="td">
                            <Select id="td" value={form.data.type_document} onChange={(e) => form.setData('type_document', e.target.value)}>{types.map((v) => <option key={v} value={v}>{t(`enum.type_document.${v}`)}</option>)}</Select>
                        </Field>
                        <Field label={t('dossier.fichier')} error={form.errors.fichier} hint={t('dossier.hint_fichier', { kb: Math.round(maxKb / 1024) })} htmlFor="fichier">
                            <input id="fichier" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('fichier', e.target.files[0])} className="text-sm" />
                        </Field>
                        <div className="flex items-end"><button type="submit" disabled={form.processing} className="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{t('dossier.upload')}</button></div>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
