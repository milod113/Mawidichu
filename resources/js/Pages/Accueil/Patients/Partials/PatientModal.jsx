import CheckField from '@/Components/CheckField';
import Field from '@/Components/Field';
import ItemModal from '@/Components/ItemModal';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import { useTrans } from '@/lib/i18n';

export default function PatientModal({ show, onClose, patient }) {
    const { t } = useTrans();

    const text = (data, setData, errors, name, label, extra = {}) => (
        <Field label={label} error={errors[name]} htmlFor={name}>
            <TextInput id={name} className="w-full" value={data[name] ?? ''} onChange={(e) => setData(name, e.target.value)} {...extra} />
        </Field>
    );

    return (
        <ItemModal
            show={show}
            onClose={onClose}
            title={patient ? t('patient.edit') : t('patient.new')}
            item={patient}
            storeUrl={route('accueil.patients.store')}
            updateUrl={patient ? route('accueil.patients.update', patient.id) : ''}
            initial={{
                nom: patient?.nom ?? '', prenom: patient?.prenom ?? '', nom_ar: patient?.nom_ar ?? '', prenom_ar: patient?.prenom_ar ?? '',
                date_naissance: patient?.date_naissance ? String(patient.date_naissance).slice(0, 10) : '', sexe: patient?.sexe ?? '',
                telephone: patient?.telephone ?? '', nin: patient?.nin ?? '', numero_chifa: patient?.numero_chifa ?? '',
                wilaya: patient?.wilaya ?? '', commune: patient?.commune ?? '', adresse: patient?.adresse ?? '', confirmer_doublon: false,
            }}
        >
            {({ data, setData, errors }) => (
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text(data, setData, errors, 'nom', t('patient.nom'))}
                        {text(data, setData, errors, 'prenom', t('patient.prenom'))}
                        {text(data, setData, errors, 'nom_ar', t('patient.nom_ar'), { dir: 'rtl' })}
                        {text(data, setData, errors, 'prenom_ar', t('patient.prenom_ar'), { dir: 'rtl' })}
                        <Field label={t('patient.naissance')} error={errors.date_naissance} htmlFor="date_naissance">
                            <TextInput id="date_naissance" type="date" dir="ltr" className="w-full" value={data.date_naissance} onChange={(e) => setData('date_naissance', e.target.value)} />
                        </Field>
                        <Field label={t('patient.sexe')} error={errors.sexe} htmlFor="sexe">
                            <Select id="sexe" value={data.sexe ?? ''} onChange={(e) => setData('sexe', e.target.value)}>
                                <option value="">{t('common.none')}</option>
                                <option value="M">{t('enum.sexe.M')}</option>
                                <option value="F">{t('enum.sexe.F')}</option>
                            </Select>
                        </Field>
                        {text(data, setData, errors, 'telephone', t('patient.telephone'), { dir: 'ltr', type: 'tel' })}
                        {text(data, setData, errors, 'nin', t('patient.nin'), { dir: 'ltr' })}
                        {text(data, setData, errors, 'numero_chifa', t('patient.chifa'), { dir: 'ltr' })}
                        {text(data, setData, errors, 'wilaya', t('patient.wilaya'))}
                        {text(data, setData, errors, 'commune', t('patient.commune'))}
                        {text(data, setData, errors, 'adresse', t('patient.adresse'))}
                    </div>
                    {!patient && errors.nom?.startsWith('patient.doublon') && (
                        <CheckField id="doublon" label={t('patient.doublon_confirm')} checked={data.confirmer_doublon} onChange={(v) => setData('confirmer_doublon', v)} />
                    )}
                </div>
            )}
        </ItemModal>
    );
}
