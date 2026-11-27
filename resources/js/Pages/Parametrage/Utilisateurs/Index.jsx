import AddButton from '@/Components/AddButton';
import Badge from '@/Components/Badge';
import CheckField from '@/Components/CheckField';
import DataTable from '@/Components/DataTable';
import Field from '@/Components/Field';
import ItemModal from '@/Components/ItemModal';
import Pagination from '@/Components/Pagination';
import RowActions from '@/Components/RowActions';
import Select from '@/Components/Select';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTrans } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ users, filters, roles, services, locales }) {
    const { t, te } = useTrans();
    const { errors: page } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [f, setF] = useState({ q: filters.q ?? '', role: filters.role ?? '', statut: filters.statut ?? '' });
    const item = editing?.id ? editing : null;

    const search = (next) => {
        setF(next);
        router.get(route('parametrage.utilisateurs.index'), Object.fromEntries(Object.entries(next).filter(([, v]) => v)), { preserveState: true, replace: true });
    };

    const columns = [
        { key: 'name', label: t('common.name'), render: (u) => <span className="font-medium">{u.name}{u.moi && <span className="ms-1 text-xs text-gray-500">({t('user.moi')})</span>}</span> },
        { key: 'email', label: t('auth.email'), render: (u) => <span dir="ltr">{u.email}</span> },
        { key: 'roles', label: t('user.roles'), render: (u) => <div className="flex flex-wrap gap-1">{u.roles.map((r) => <Badge key={r} tone="teal">{t(`roles.${r}`)}</Badge>)}</div> },
        { key: 'aff', label: t('user.affectation'), render: (u) => u.affectation ?? t('user.guichet_central') },
        { key: 'actif', label: t('jour.statut'), render: (u) => <Badge tone={u.is_active ? 'green' : 'gray'}>{u.is_active ? t('common.active') : t('common.inactive')}</Badge> },
        { key: 'a', label: '', render: (u) => <RowActions onEdit={() => setEditing(u)} deleteUrl={route('parametrage.utilisateurs.destroy', u.id)} /> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="truncate text-lg font-semibold">{t('user.title')}</h2>}>
            <Head title={t('user.title')} />
            <div className="mx-auto max-w-6xl space-y-4">
                <p className="rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:bg-primary-500/10 dark:text-primary-200">{t('user.hint')}</p>
                {page.roles && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{te(page.roles)}</p>}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        <TextInput placeholder={t('common.search')} value={f.q} onChange={(e) => search({ ...f, q: e.target.value })} className="w-56" />
                        <Select value={f.role} onChange={(e) => search({ ...f, role: e.target.value })} className="w-52"><option value="">{t('user.tous_roles')}</option>{roles.map((r) => <option key={r} value={r}>{t(`roles.${r}`)}</option>)}</Select>
                        <Select value={f.statut} onChange={(e) => search({ ...f, statut: e.target.value })} className="w-36"><option value="">{t('stats.tous')}</option><option value="actif">{t('common.active')}</option><option value="inactif">{t('common.inactive')}</option></Select>
                    </div>
                    <AddButton onClick={() => setEditing({})}>{t('user.add')}</AddButton>
                </div>
                <DataTable columns={columns} rows={users.data} empty={t('user.empty')} />
                <Pagination links={users.links} />
            </div>

            <ItemModal
                show={editing !== null} onClose={() => setEditing(null)} title={item ? t('user.edit') : t('user.add')} item={item}
                storeUrl={route('parametrage.utilisateurs.store')} updateUrl={item ? route('parametrage.utilisateurs.update', item.id) : ''}
                initial={{ name: item?.name ?? '', email: item?.email ?? '', telephone: item?.telephone ?? '', roles: item?.roles ?? [], affectation_service_id: item?.affectation_service_id ?? '', is_active: item?.is_active ?? true, locale: item?.locale ?? locales[0], password: '' }}
            >
                {({ data, setData, errors }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('common.name')} error={errors.name} htmlFor="name"><TextInput id="name" className="w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                            <Field label={t('auth.email')} error={errors.email} htmlFor="email"><TextInput id="email" type="email" dir="ltr" className="w-full" value={data.email} onChange={(e) => setData('email', e.target.value)} /></Field>
                            <Field label={t('patient.telephone')} error={errors.telephone} htmlFor="tel"><TextInput id="tel" dir="ltr" className="w-full" value={data.telephone ?? ''} onChange={(e) => setData('telephone', e.target.value)} /></Field>
                            <Field label={item ? t('user.password_new') : t('auth.password')} error={errors.password} htmlFor="pw"><TextInput id="pw" type="password" autoComplete="new-password" dir="ltr" className="w-full" value={data.password} onChange={(e) => setData('password', e.target.value)} /></Field>
                        </div>
                        <Field label={t('user.roles')} error={errors.roles || errors['roles.0']} htmlFor="roles">
                            <div className="grid gap-1 sm:grid-cols-2">
                                {roles.map((r) => <CheckField key={r} id={`r-${r}`} label={t(`roles.${r}`)} checked={data.roles.includes(r)} onChange={(v) => setData('roles', v ? [...data.roles, r] : data.roles.filter((x) => x !== r))} />)}
                            </div>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('user.affectation')} hint={t('user.affectation_hint')} error={errors.affectation_service_id} htmlFor="aff">
                                <Select id="aff" value={data.affectation_service_id ?? ''} onChange={(e) => setData('affectation_service_id', e.target.value)}><option value="">{t('user.guichet_central')}</option>{services.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}</Select>
                            </Field>
                            <Field label={t('user.locale')} htmlFor="loc"><Select id="loc" value={data.locale} onChange={(e) => setData('locale', e.target.value)}>{locales.map((l) => <option key={l} value={l}>{l.toUpperCase()}</option>)}</Select></Field>
                        </div>
                        <CheckField id="actif" label={t('user.actif')} checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    </>
                )}
            </ItemModal>
        </AuthenticatedLayout>
    );
}
