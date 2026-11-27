import AddButton from '@/Components/AddButton';
import CheckField from '@/Components/CheckField';
import DataTable from '@/Components/DataTable';
import Field from '@/Components/Field';
import ItemModal from '@/Components/ItemModal';
import RowActions from '@/Components/RowActions';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import { useTrans } from '@/lib/i18n';
import { useState } from 'react';

export default function MedecinsTab({ service, medecins, candidates, options }) {
    const { t } = useTrans();
    const [editing, setEditing] = useState(null);
    const open = editing !== null;
    const item = editing?.id ? editing : null;

    const columns = [
        { key: 'name', label: t('common.name'), render: (m) => <span className="font-medium">{m.user?.name}</span> },
        { key: 'grade', label: t('medecin.grade'), render: (m) => t(`enum.grade.${m.grade}`) },
        { key: 'specialite', label: t('medecin.specialite'), render: (m) => m.specialite ?? t('common.none') },
        { key: 'surbooking', label: t('medecin.surbooking'), render: (m) => m.tolere_surbooking
            ? `${m.valeur_surbooking} ${t(`enum.surbooking_unite.${m.surbooking_unite}`)}` : t('common.no') },
        { key: 'actions', label: '', render: (m) => <RowActions onEdit={() => setEditing(m)} deleteUrl={route('parametrage.services.medecins.destroy', [service.id, m.id])} /> },
    ];

    return (
        <div className="space-y-4">
            <div className="flex justify-end"><AddButton onClick={() => setEditing({})}>{t('medecin.new')}</AddButton></div>
            <DataTable columns={columns} rows={medecins} empty={t('medecin.empty')} />

            <ItemModal
                show={open}
                onClose={() => setEditing(null)}
                title={item ? t('medecin.edit') : t('medecin.new')}
                item={item}
                storeUrl={route('parametrage.services.medecins.store', service.id)}
                updateUrl={item ? route('parametrage.services.medecins.update', [service.id, item.id]) : ''}
                initial={{
                    user_id: '',
                    grade: item?.grade ?? options.grade[0],
                    specialite: item?.specialite ?? '',
                    duree_consult_defaut: item?.duree_consult_defaut ?? '',
                    quota_max_defaut: item?.quota_max_defaut ?? '',
                    tolere_surbooking: item?.tolere_surbooking ?? false,
                    valeur_surbooking: item?.valeur_surbooking ?? '',
                    surbooking_unite: item?.surbooking_unite ?? 'nombre',
                }}
            >
                {({ data, setData, errors }) => (
                    <>
                        {!item && (
                            <Field label={t('medecin.user')} error={errors.user_id} htmlFor="user_id" hint={candidates.length === 0 ? t('medecin.candidates_empty') : undefined}>
                                <Select id="user_id" value={data.user_id} onChange={(e) => setData('user_id', e.target.value)}>
                                    <option value="">{t('planning.none_target')}</option>
                                    {candidates.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                                </Select>
                            </Field>
                        )}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('medecin.grade')} error={errors.grade} htmlFor="grade">
                                <Select id="grade" value={data.grade} onChange={(e) => setData('grade', e.target.value)}>
                                    {options.grade.map((g) => <option key={g} value={g}>{t(`enum.grade.${g}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('medecin.specialite')} error={errors.specialite} htmlFor="specialite">
                                <TextInput id="specialite" className="w-full" value={data.specialite ?? ''} onChange={(e) => setData('specialite', e.target.value)} />
                            </Field>
                            <Field label={t('medecin.duree_defaut')} error={errors.duree_consult_defaut} htmlFor="duree_defaut" hint={t('medecin.inherit')}>
                                <TextInput id="duree_defaut" type="number" min="1" className="w-full" value={data.duree_consult_defaut ?? ''} onChange={(e) => setData('duree_consult_defaut', e.target.value)} />
                            </Field>
                            <Field label={t('medecin.quota_defaut')} error={errors.quota_max_defaut} htmlFor="quota_defaut" hint={t('medecin.inherit')}>
                                <TextInput id="quota_defaut" type="number" min="1" className="w-full" value={data.quota_max_defaut ?? ''} onChange={(e) => setData('quota_max_defaut', e.target.value)} />
                            </Field>
                        </div>
                        <CheckField id="surbooking" label={t('medecin.surbooking')} checked={data.tolere_surbooking} onChange={(v) => setData('tolere_surbooking', v)} />
                        {data.tolere_surbooking && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('medecin.valeur')} error={errors.valeur_surbooking} htmlFor="valeur">
                                    <TextInput id="valeur" type="number" min="0" className="w-full" value={data.valeur_surbooking ?? ''} onChange={(e) => setData('valeur_surbooking', e.target.value)} />
                                </Field>
                                <Field label={t('medecin.unite')} error={errors.surbooking_unite} htmlFor="unite">
                                    <Select id="unite" value={data.surbooking_unite} onChange={(e) => setData('surbooking_unite', e.target.value)}>
                                        {options.surbooking_unite.map((u) => <option key={u} value={u}>{t(`enum.surbooking_unite.${u}`)}</option>)}
                                    </Select>
                                </Field>
                            </div>
                        )}
                    </>
                )}
            </ItemModal>
        </div>
    );
}
