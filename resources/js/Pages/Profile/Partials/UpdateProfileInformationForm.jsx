import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useTrans } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status, className = '' }) {
    const { t } = useTrans();
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
        telephone: user.telephone ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium">{t('profile.info.title')}</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{t('profile.info.intro')}</p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value={t('profile.name')} />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value={t('auth.email')} />
                    <TextInput
                        id="email"
                        type="email"
                        dir="ltr"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />
                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="telephone" value={t('profile.telephone')} />
                    <TextInput
                        id="telephone"
                        type="tel"
                        dir="ltr"
                        className="mt-1 block w-full"
                        value={data.telephone}
                        onChange={(e) => setData('telephone', e.target.value)}
                        autoComplete="tel"
                    />
                    <InputError className="mt-2" message={errors.telephone} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm">
                            {t('profile.unverified')}{' '}
                            <Link href={route('verification.send')} method="post" as="button" className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:text-gray-100">
                                {t('profile.resend')}
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                                {t('profile.verification_sent')}
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>{t('common.save')}</PrimaryButton>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600 dark:text-gray-400">{t('common.saved')}</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
