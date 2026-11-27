import Icon from '@/Components/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import BoxesTab from './Partials/BoxesTab';
import MedecinsTab from './Partials/MedecinsTab';
import PlanningsTab from './Partials/PlanningsTab';
import ServiceForm from './Partials/ServiceForm';
import TypesTab from './Partials/TypesTab';

const TABS = ['general', 'boxes', 'types', 'medecins', 'plannings'];

export default function Show({ service, boxes, typesConsultation, medecins, plannings, candidates, isAdmin, options }) {
    const { t, locale } = useTrans();
    const [tab, setTab] = useState('general');
    const name = locale === 'ar' && service.nom_ar ? service.nom_ar : service.nom;

    const removeService = () => {
        if (window.confirm(t('common.confirm_delete'))) {
            router.delete(route('parametrage.services.destroy', service.id));
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{name}</h2>}>
            <Head title={name} />

            <div className="mx-auto max-w-5xl space-y-4">
                <Link href={route('parametrage.services.index')} className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                    <Icon name="back" className="h-4 w-4 rtl:rotate-180" />
                    {t('common.back')}
                </Link>

                <div role="tablist" className="flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
                    {TABS.map((key) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={tab === key}
                            onClick={() => setTab(key)}
                            className={`-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition ${
                                tab === key
                                    ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-300'
                                    : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            {t(`svc.tab.${key}`)}
                        </button>
                    ))}
                </div>

                <section className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6 dark:bg-gray-900 dark:ring-gray-800">
                    {tab === 'general' && (
                        <div className="space-y-6">
                            <ServiceForm service={service} options={options} admin={isAdmin} submitLabel={t('common.save')} />
                            {isAdmin && (
                                <button type="button" onClick={removeService} className="text-sm text-red-600 hover:underline dark:text-red-400">
                                    {t('svc.deleted_hint')}
                                </button>
                            )}
                        </div>
                    )}
                    {tab === 'boxes' && <BoxesTab service={service} boxes={boxes} medecins={medecins} />}
                    {tab === 'types' && <TypesTab service={service} types={typesConsultation} options={options} />}
                    {tab === 'medecins' && <MedecinsTab service={service} medecins={medecins} candidates={candidates} options={options} />}
                    {tab === 'plannings' && <PlanningsTab service={service} plannings={plannings} medecins={medecins} boxes={boxes} options={options} />}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
