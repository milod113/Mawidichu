import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import CheckField from '@/Components/CheckField';
import DataTable from '@/Components/DataTable';
import Field from '@/Components/Field';
import ItemModal from '@/Components/ItemModal';
import RowActions from '@/Components/RowActions';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import { useTrans } from '@/lib/i18n';
import { useState } from 'react';

export default function BoxesTab({ service, boxes, medecins }) {
    const { t } = useTrans();
    const [editing, setEditing] = useState(null); // null = fermé, {} = création, box = modification
    const open = editing !== null;
    const item = editing?.id ? editing : null;
    const base = route('parametrage.services.boxes.store', service.id);

    const columns = [
        { key: 'numero_box', label: t('box.numero') },
        { key: 'nom_affichage', label: t('box.nom_affichage'), render: (b) => b.nom_affichage ?? t('common.none') },
        { key: 'medecin', label: t('box.medecin_actuel'), render: (b) => medecins.find((m) => m.id === b.medecin_actuel_id)?.user?.name ?? t('common.none') },
        { key: 'is_active', label: '', render: (b) => <Badge tone={b.is_active ? 'green' : 'gray'}>{t(b.is_active ? 'common.active' : 'common.inactive')}</Badge> },
        { key: 'actions', label: '', render: (b) => <RowActions onEdit={() => setEditing(b)} deleteUrl={route('parametrage.services.boxes.destroy', [service.id, b.id])} /> },
    ];

    return (
        <div className="space-y-4">
            <div className="flex justify-end"><AddButton onClick={() => setEditing({})}>{t('box.new')}</AddButton></div>
            <DataTable columns={columns} rows={boxes} empty={t('box.empty')} />

            <ItemModal
                show={open}
                onClose={() => setEditing(null)}
                title={item ? t('box.edit') : t('box.new')}
                item={item}
                storeUrl={base}
                updateUrl={item ? route('parametrage.services.boxes.update', [service.id, item.id]) : ''}
                initial={{
                    numero_box: item?.numero_box ?? (Math.max(0, ...boxes.map((b) => b.numero_box)) + 1),
                    nom_affichage: item?.nom_affichage ?? '',
                    medecin_actuel_id: item?.medecin_actuel_id ?? '',
                    is_active: item?.is_active ?? true,
                }}
            >
                {({ data, setData, errors }) => (
                    <>
                        <Field label={t('box.numero')} error={errors.numero_box} htmlFor="numero_box">
                            <TextInput id="numero_box" type="number" min="1" className="w-full" value={data.numero_box} onChange={(e) => setData('numero_box', e.target.value)} />
                        </Field>
                        <Field label={t('box.nom_affichage')} error={errors.nom_affichage} htmlFor="nom_affichage">
                            <TextInput id="nom_affichage" className="w-full" value={data.nom_affichage ?? ''} onChange={(e) => setData('nom_affichage', e.target.value)} />
                        </Field>
                        <Field label={t('box.medecin_actuel')} error={errors.medecin_actuel_id} htmlFor="medecin_actuel_id">
                            <Select id="medecin_actuel_id" value={data.medecin_actuel_id ?? ''} onChange={(e) => setData('medecin_actuel_id', e.target.value)}>
                                <option value="">{t('common.none')}</option>
                                {medecins.map((m) => <option key={m.id} value={m.id}>{m.user?.name}</option>)}
                            </Select>
                        </Field>
                        <CheckField id="box_active" label={t('common.active')} checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    </>
                )}
            </ItemModal>
        </div>
    );
}
