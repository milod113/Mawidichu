import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import Icon from '@/Components/Icon';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeSwitcher from '@/Components/ThemeSwitcher';
import { useTrans } from '@/lib/i18n';
import FlashMessage from '@/Components/FlashMessage';
import { useDocumentDirection } from '@/lib/useDocumentDirection';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

function Sidebar({ onNavigate }) {
    const { t, locale } = useTrans();
    const { navigation = [], app } = usePage().props;
    const etab = app?.etablissement ?? {};

    return (
        <div className="flex h-full flex-col border-e border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div className="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 px-4 dark:border-gray-800">
                <ApplicationLogo />
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">{app?.name}</p>
                    <p className="truncate text-xs text-gray-500 dark:text-gray-400">
                        {locale === 'ar' ? etab.nom_ar : etab.nom}
                    </p>
                </div>
            </div>

            <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                {navigation.map((section) => (
                    <div key={section.section}>
                        <p className="px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {t(section.section)}
                        </p>
                        <ul className="space-y-1">
                            {section.items.map((item) => {
                                const active = route().current(item.route.replace(/\.index$/, '') + '*') || route().current(item.route);

                                return (
                                    <li key={item.route}>
                                        <Link
                                            href={route(item.route)}
                                            onClick={onNavigate}
                                            aria-current={active ? 'page' : undefined}
                                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${
                                                active
                                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                                            }`}
                                        >
                                            <Icon name={item.icon} />
                                            {t(item.label)}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-gray-200 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {locale === 'ar' ? etab.rattachement_ar : etab.rattachement}
            </div>
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    useDocumentDirection();
    const { t } = useTrans();
    const user = usePage().props.auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="min-h-screen">
            {/* Sidebar fixe (écran large) */}
            <aside className="fixed inset-y-0 start-0 z-30 hidden w-64 lg:block">
                <Sidebar />
            </aside>

            {/* Sidebar mobile (tiroir) */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div
                        className="absolute inset-0 bg-gray-900/50"
                        onClick={() => setSidebarOpen(false)}
                    />
                    <aside className="absolute inset-y-0 start-0 w-64 shadow-xl">
                        <Sidebar onNavigate={() => setSidebarOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:ps-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-gray-800 dark:bg-gray-900/90">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                    >
                        <Icon name="menu" className="h-6 w-6" />
                        <span className="sr-only">{t('nav.open_menu')}</span>
                    </button>

                    <div className="min-w-0 flex-1">{header}</div>

                    <div className="hidden items-center gap-2 sm:flex">
                        <LocaleSwitcher />
                        <ThemeSwitcher />
                    </div>

                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                                    {user.name.charAt(0)}
                                </span>
                                <span className="hidden max-w-[10rem] truncate md:inline">{user.name}</span>
                                <Icon name="chevron" className="h-4 w-4" />
                            </button>
                        </Dropdown.Trigger>

                        <Dropdown.Content>
                            <div className="space-y-2 border-b border-gray-100 px-4 py-2 sm:hidden dark:border-gray-700">
                                <LocaleSwitcher />
                                <ThemeSwitcher />
                            </div>
                            <Dropdown.Link href={route('profile.edit')}>
                                {t('nav.profile')}
                            </Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                {t('nav.logout')}
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                <main className="p-4 sm:p-6 lg:p-8">
                    <FlashMessage />
                    {children}
                </main>
            </div>
        </div>
    );
}
