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

export default function PlanningsTab({ service, plannings, medecins, boxes, options }) {
    const { t } = useTrans();
    const [editing, setEditing] = useState(null);
    const open = editing !== null;
    const item = editing?.id ? editing : null;
    const days = [0, 1, 2, 3, 4, 5, 6];

    const target = (p) => p.medecin?.user?.name ?? (p.box ? (p.box.nom_affichage ?? `Box ${p.box.numero_box}`) : t('common.none'));

    const columns = [
        { key: 'jour', label: t('planning.jour'), render: (p) => t(`weekday.${p.jour_semaine}`) },
        { key: 'horaire', label: t('planning.debut'), render: (p) => <span dir="ltr">{p.heure_debut} – {p.heure_fin}</span> },
        { key: 'affectation', label: t('planning.affectation'), render: target },
        { key: 'mode', label: t('planning.mode'), render: (p) => p.mode_planification
            ? t(`enum.mode_planification.${p.mode_planification}`)
            : <span className="text-gray-500 dark:text-gray-400">{t('planning.inherit_service')}</span> },
        { key: 'quota', label: t('planning.quota'), render: (p) => (p.quota_rdv ?? '—') + (p.taille_vague ? ` · ${p.taille_vague}/${p.intervalle_vague_min} min` : '') },
        { key: 'is_active', label: '', render: (p) => <Badge tone={p.is_active ? 'green' : 'gray'}>{t(p.is_active ? 'common.active' : 'common.inactive')}</Badge> },
        { key: 'actions', label: '', render: (p) => <RowActions onEdit={() => setEditing(p)} deleteUrl={route('parametrage.services.plannings.destroy', [service.id, p.id])} /> },
    ];

    const num = (data, setData, errors, name, label) => (
        <Field label={label} error={errors[name]} htmlFor={name}>
            <TextInput id={name} type="number" min="0" className="w-full" value={data[name] ?? ''} onChange={(e) => setData(name, e.target.value)} />
        </Field>
    );

    return (
        <div className="space-y-4">
            <div className="flex justify-end"><AddButton onClick={() => setEditing({})}>{t('planning.new')}</AddButton></div>
            <DataTable columns={columns} rows={plannings} empty={t('planning.empty')} />

            <ItemModal
                show={open}
                onClose={() => setEditing(null)}
                title={item ? t('planning.edit') : t('planning.new')}
                item={item}
                storeUrl={route('parametrage.services.plannings.store', service.id)}
                updateUrl={item ? route('parametrage.services.plannings.update', [service.id, item.id]) : ''}
                initial={{
                    medecin_id: item?.medecin_id ?? '',
                    box_id: item?.box_id ?? '',
                    jour_semaine: item?.jour_semaine ?? 1,
                    heure_debut: item?.heure_debut ?? '08:30',
                    heure_fin: item?.heure_fin ?? '11:30',
                    mode_planification: item?.mode_planification ?? '',
                    quota_rdv: item?.quota_rdv ?? '',
                    quota_sans_rdv: item?.quota_sans_rdv ?? '',
                    taille_vague: item?.taille_vague ?? '',
                    intervalle_vague_min: item?.intervalle_vague_min ?? '',
                    is_active: item?.is_active ?? true,
                }}
            >
                {({ data, setData, errors }) => (
                    <>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('planning.help')}</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('planning.medecin')} error={errors.medecin_id} htmlFor="medecin_id">
                                <Select id="medecin_id" value={data.medecin_id ?? ''} onChange={(e) => setData('medecin_id', e.target.value)}>
                                    <option value="">{t('common.none')}</option>
                                    {medecins.map((m) => <option key={m.id} value={m.id}>{m.user?.name}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('planning.box')} error={errors.box_id} htmlFor="box_id">
                                <Select id="box_id" value={data.box_id ?? ''} onChange={(e) => setData('box_id', e.target.value)}>
                                    <option value="">{t('common.none')}</option>
                                    {boxes.map((b) => <option key={b.id} value={b.id}>{b.nom_affichage ?? `Box ${b.numero_box}`}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('planning.jour')} error={errors.jour_semaine} htmlFor="jour">
                                <Select id="jour" value={data.jour_semaine} onChange={(e) => setData('jour_semaine', e.target.value)}>
                                    {days.map((d) => <option key={d} value={d}>{t(`weekday.${d}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('planning.mode')} error={errors.mode_planification} htmlFor="mode">
                                <Select id="mode" value={data.mode_planification ?? ''} onChange={(e) => setData('mode_planification', e.target.value)}>
                                    <option value="">{t('planning.inherit_service')}</option>
                                    {options.mode_planification.map((m) => <option key={m} value={m}>{t(`enum.mode_planification.${m}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('planning.debut')} error={errors.heure_debut} htmlFor="debut">
                                <TextInput id="debut" type="time" dir="ltr" className="w-full" value={data.heure_debut} onChange={(e) => setData('heure_debut', e.target.value)} />
                            </Field>
                            <Field label={t('planning.fin')} error={errors.heure_fin} htmlFor="fin">
                                <TextInput id="fin" type="time" dir="ltr" className="w-full" value={data.heure_fin} onChange={(e) => setData('heure_fin', e.target.value)} />
                            </Field>
                            {num(data, setData, errors, 'quota_rdv', t('planning.quota_rdv'))}
                            {num(data, setData, errors, 'quota_sans_rdv', t('planning.quota_sans_rdv'))}
                            {num(data, setData, errors, 'taille_vague', t('planning.taille_vague'))}
                            {num(data, setData, errors, 'intervalle_vague_min', t('planning.intervalle'))}
                        </div>
                        <CheckField id="planning_active" label={t('common.active')} checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    </>
                )}
            </ItemModal>
        </div>
    );
}
