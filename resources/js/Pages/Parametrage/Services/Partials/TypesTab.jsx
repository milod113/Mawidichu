import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import CheckField from '@/Components/CheckField';
import DataTable from '@/Components/DataTable';
import Field, { inputClass } from '@/Components/Field';
import ItemModal from '@/Components/ItemModal';
import RowActions from '@/Components/RowActions';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import { useTrans } from '@/lib/i18n';
import { useState } from 'react';

export default function TypesTab({ service, types, options }) {
    const { t } = useTrans();
    const [editing, setEditing] = useState(null);
    const open = editing !== null;
    const item = editing?.id ? editing : null;

    const columns = [
        { key: 'code', label: t('type.code'), render: (r) => t(`enum.code_consultation.${r.code}`) },
        { key: 'libelle', label: t('type.libelle'), render: (r) => (
            <span className="inline-flex items-center gap-2">
                <span className="h-3 w-3 rounded-full ring-1 ring-black/10" style={{ backgroundColor: r.couleur ?? 'transparent' }} />
                {r.libelle}
            </span>
        ) },
        { key: 'duree_minutes', label: t('type.duree'), render: (r) => `${r.duree_minutes} min` },
        { key: 'is_active', label: '', render: (r) => <Badge tone={r.is_active ? 'green' : 'gray'}>{t(r.is_active ? 'common.active' : 'common.inactive')}</Badge> },
        { key: 'actions', label: '', render: (r) => <RowActions onEdit={() => setEditing(r)} deleteUrl={route('parametrage.services.types-consultation.destroy', [service.id, r.id])} /> },
    ];

    return (
        <div className="space-y-4">
            <div className="flex justify-end"><AddButton onClick={() => setEditing({})}>{t('type.new')}</AddButton></div>
            <DataTable columns={columns} rows={types} empty={t('type.empty')} />

            <ItemModal
                show={open}
                onClose={() => setEditing(null)}
                title={item ? t('type.edit') : t('type.new')}
                item={item}
                storeUrl={route('parametrage.services.types-consultation.store', service.id)}
                updateUrl={item ? route('parametrage.services.types-consultation.update', [service.id, item.id]) : ''}
                initial={{
                    code: item?.code ?? options.code_consultation[0],
                    libelle: item?.libelle ?? '',
                    duree_minutes: item?.duree_minutes ?? '',
                    couleur: item?.couleur ?? '#0d9488',
                    bilans_requis_text: item?.bilans_requis_text ?? '',
                    is_active: item?.is_active ?? true,
                }}
            >
                {({ data, setData, errors }) => (
                    <>
                        <Field label={t('type.code')} error={errors.code} htmlFor="code">
                            <Select id="code" value={data.code} onChange={(e) => setData('code', e.target.value)}>
                                {options.code_consultation.map((c) => <option key={c} value={c}>{t(`enum.code_consultation.${c}`)}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('type.libelle')} error={errors.libelle} htmlFor="libelle">
                            <TextInput id="libelle" className="w-full" value={data.libelle} onChange={(e) => setData('libelle', e.target.value)} />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('type.duree')} error={errors.duree_minutes} htmlFor="duree">
                                <TextInput id="duree" type="number" min="1" className="w-full" value={data.duree_minutes} onChange={(e) => setData('duree_minutes', e.target.value)} />
                            </Field>
                            <Field label={t('type.couleur')} error={errors.couleur} htmlFor="couleur">
                                <input id="couleur" type="color" className="h-10 w-full rounded-md border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-900" value={data.couleur ?? '#0d9488'} onChange={(e) => setData('couleur', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={t('type.bilans')} error={errors.bilans_requis_text} htmlFor="bilans">
                            <textarea id="bilans" rows={3} className={inputClass} value={data.bilans_requis_text ?? ''} onChange={(e) => setData('bilans_requis_text', e.target.value)} />
                        </Field>
                        <CheckField id="type_active" label={t('common.active')} checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    </>
                )}
            </ItemModal>
        </div>
    );
}
