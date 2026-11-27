import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import CheckField from '@/Components/CheckField';
import DataTable from '@/Components/DataTable';
import Field from '@/Components/Field';
import Icon from '@/Components/Icon';
import ItemModal from '@/Components/ItemModal';
import RowActions from '@/Components/RowActions';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const TYPES = ['fixe', 'mobile'];
const STATUTS = ['confirme', 'estimatif'];

function formatDate(value, locale) {
    return new Date(`${value}T00:00:00Z`).toLocaleDateString(locale === 'ar' ? 'ar-DZ-u-nu-latn' : 'fr-FR', {
        weekday: 'short', day: 'numeric', month: 'long', timeZone: 'UTC',
    });
}

export default function Index({ jours, annee }) {
    const { t, locale } = useTrans();
    const [editing, setEditing] = useState(null);
    const open = editing !== null;
    const item = editing?.id ? editing : null;

    const post = (name, jour, data = {}) =>
        router.post(route(`parametrage.jours-feries.${name}`, jour.id), data, { preserveScroll: true });

    const changeYear = (delta) =>
        router.get(route('parametrage.jours-feries.index'), { annee: annee + delta }, { preserveScroll: true });

    const columns = [
        { key: 'date', label: t('ferie.date'), render: (j) => <span className="font-medium">{formatDate(j.date_affichee, locale)}</span> },
        { key: 'libelle', label: t('ferie.libelle'), render: (j) => (locale === 'ar' && j.libelle_ar ? j.libelle_ar : j.libelle) },
        { key: 'type', label: t('ferie.type'), render: (j) => t(`enum.type_jour_ferie.${j.type}`) },
        { key: 'statut', label: t('ferie.statut'), render: (j) => (
            <Badge tone={j.statut_confirmation === 'confirme' ? 'green' : 'amber'}>{t(`enum.statut_confirmation.${j.statut_confirmation}`)}</Badge>
        ) },
        { key: 'plage', label: t('ferie.range'), render: (j) => (j.groupe ? (
            <div className="flex items-center gap-1">
                <button type="button" onClick={() => post('decaler', j, { jours: -1 })} className="whitespace-nowrap rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('ferie.shift_minus')}</button>
                <button type="button" onClick={() => post('decaler', j, { jours: 1 })} className="whitespace-nowrap rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('ferie.shift_plus')}</button>
                {j.statut_confirmation === 'estimatif' && (
                    <button type="button" onClick={() => post('confirmer', j)} className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-primary-600 px-2 py-1 text-xs font-medium text-white hover:bg-primary-700">
                        <Icon name="check" className="h-3.5 w-3.5" />{t('ferie.confirm')}
                    </button>
                )}
            </div>
        ) : null) },
        { key: 'actions', label: '', render: (j) => <RowActions onEdit={() => setEditing(j)} deleteUrl={route('parametrage.jours-feries.destroy', j.id)} /> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('ferie.title')}</h2>}>
            <Head title={t('ferie.title')} />

            <div className="mx-auto max-w-5xl space-y-4">
                <p className="rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:bg-primary-500/10 dark:text-primary-200">{t('ferie.hint')}</p>

                <div className="flex items-center justify-between gap-3">
                    <div className="inline-flex items-center gap-2">
                        <button type="button" onClick={() => changeYear(-1)} aria-label={`${annee - 1}`} className="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">‹</button>
                        <span className="min-w-16 text-center text-base font-semibold" dir="ltr">{annee}</span>
                        <button type="button" onClick={() => changeYear(1)} aria-label={`${annee + 1}`} className="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">›</button>
                    </div>
                    <AddButton onClick={() => setEditing({})}>{t('ferie.add')}</AddButton>
                </div>

                <DataTable columns={columns} rows={jours} empty={t('ferie.empty')} />
            </div>

            <ItemModal
                show={open}
                onClose={() => setEditing(null)}
                title={item ? t('ferie.edit') : t('ferie.add')}
                item={item}
                storeUrl={route('parametrage.jours-feries.store')}
                updateUrl={item ? route('parametrage.jours-feries.update', item.id) : ''}
                initial={{
                    libelle: item?.libelle ?? '',
                    libelle_ar: item?.libelle_ar ?? '',
                    date_ferie: item?.date_ferie ?? `${annee}-01-01`,
                    type: item?.type ?? 'mobile',
                    est_recurrent: item?.est_recurrent ?? false,
                    statut_confirmation: item?.statut_confirmation ?? 'estimatif',
                }}
            >
                {({ data, setData, errors }) => (
                    <>
                        <Field label={t('ferie.libelle')} error={errors.libelle} htmlFor="libelle">
                            <TextInput id="libelle" className="w-full" value={data.libelle} onChange={(e) => setData('libelle', e.target.value)} />
                        </Field>
                        <Field label={t('ferie.libelle_ar')} error={errors.libelle_ar} htmlFor="libelle_ar">
                            <TextInput id="libelle_ar" dir="rtl" className="w-full" value={data.libelle_ar ?? ''} onChange={(e) => setData('libelle_ar', e.target.value)} />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label={t('ferie.date')} error={errors.date_ferie} htmlFor="date_ferie">
                                <TextInput id="date_ferie" type="date" dir="ltr" className="w-full" value={data.date_ferie} onChange={(e) => setData('date_ferie', e.target.value)} />
                            </Field>
                            <Field label={t('ferie.type')} error={errors.type} htmlFor="type">
                                <Select id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                    {TYPES.map((v) => <option key={v} value={v}>{t(`enum.type_jour_ferie.${v}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('ferie.statut')} error={errors.statut_confirmation} htmlFor="statut">
                                <Select id="statut" value={data.statut_confirmation} onChange={(e) => setData('statut_confirmation', e.target.value)}>
                                    {STATUTS.map((v) => <option key={v} value={v}>{t(`enum.statut_confirmation.${v}`)}</option>)}
                                </Select>
                            </Field>
                        </div>
                        <CheckField id="recurrent" label={t('ferie.recurrent')} checked={data.est_recurrent} onChange={(v) => setData('est_recurrent', v)} />
                    </>
                )}
            </ItemModal>
        </AuthenticatedLayout>
    );
}
