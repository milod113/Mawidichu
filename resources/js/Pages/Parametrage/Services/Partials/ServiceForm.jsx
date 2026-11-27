import CheckField from '@/Components/CheckField';
import Field, { inputClass } from '@/Components/Field';
import PrimaryButton from '@/Components/PrimaryButton';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

/**
 * Formulaire de service : création (super admin) ou onglet « Général ».
 * `admin` donne accès au code, aux noms et à l'activation ; un chef de
 * service ne règle que le fonctionnement de son service.
 */
export default function ServiceForm({ service, options, admin, submitLabel }) {
    const { t } = useTrans();
    const form = useForm({
        nom: service?.nom ?? '',
        nom_ar: service?.nom_ar ?? '',
        code: service?.code ?? '',
        mode_planification: service?.mode_planification ?? options.mode_planification[0],
        mode_attribution_rdv: service?.mode_attribution_rdv ?? 'hybride',
        preconsultation_active: service?.preconsultation_active ?? false,
        affichage_tv_mode: service?.affichage_tv_mode ?? 'ticket',
        nb_box: service?.nb_box ?? 1,
        documents_defaut_json: service?.documents_defaut_json ?? [],
        is_active: service?.is_active ?? true,
    });
    const { data, setData, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => {
            if (admin) return d;
            const { nom, nom_ar, code, is_active, ...rest } = d;
            return rest;
        });
        if (service) form.put(route('parametrage.services.update', service.id), { preserveScroll: true });
        else form.post(route('parametrage.services.store'));
    };

    const select = (name, values, labelKey) => (
        <Field label={t(`svc.${name}`)} error={errors[name]} htmlFor={name}>
            <Select id={name} value={data[name]} onChange={(e) => setData(name, e.target.value)}>
                {values.map((v) => (
                    <option key={v} value={v}>{t(`enum.${labelKey ?? name}.${v}`)}</option>
                ))}
            </Select>
        </Field>
    );

    return (
        <form onSubmit={submit} className="space-y-5">
            {admin && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('svc.nom')} error={errors.nom} htmlFor="nom">
                        <TextInput id="nom" className="w-full" value={data.nom} onChange={(e) => setData('nom', e.target.value)} />
                    </Field>
                    <Field label={t('svc.nom_ar')} error={errors.nom_ar} htmlFor="nom_ar">
                        <TextInput id="nom_ar" dir="rtl" className="w-full" value={data.nom_ar ?? ''} onChange={(e) => setData('nom_ar', e.target.value)} />
                    </Field>
                    <Field label={t('svc.code')} error={errors.code} htmlFor="code">
                        <TextInput id="code" dir="ltr" className="w-full uppercase" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} />
                    </Field>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                {select('mode_planification', options.mode_planification)}
                {select('mode_attribution_rdv', options.mode_attribution_rdv)}
                {select('affichage_tv_mode', options.affichage_tv_mode)}
                <Field label={t('svc.nb_box')} error={errors.nb_box} htmlFor="nb_box">
                    <TextInput id="nb_box" type="number" min="1" className="w-full" value={data.nb_box} onChange={(e) => setData('nb_box', e.target.value)} />
                </Field>
            </div>

            <Field label={t('svc.documents_defaut')} error={errors.documents_defaut_json} htmlFor="documents">
                <textarea
                    id="documents"
                    rows={4}
                    className={inputClass}
                    value={(data.documents_defaut_json ?? []).join('\n')}
                    onChange={(e) => setData('documents_defaut_json', e.target.value.split('\n'))}
                />
            </Field>

            <div className="flex flex-col gap-2">
                <CheckField id="preconsultation" label={t('svc.preconsultation_active')} checked={data.preconsultation_active} onChange={(v) => setData('preconsultation_active', v)} />
                {admin && <CheckField id="is_active" label={t('svc.is_active')} checked={data.is_active} onChange={(v) => setData('is_active', v)} />}
            </div>

            {!admin && <p className="text-xs text-gray-500 dark:text-gray-400">{t('svc.scope_hint')}</p>}

            <PrimaryButton disabled={form.processing}>{submitLabel}</PrimaryButton>
        </form>
    );
}
