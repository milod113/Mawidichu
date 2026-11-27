import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.login.title')} />

            <h2 className="mb-4 text-lg font-semibold">{t('auth.login.title')}</h2>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">{status}</div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="email" value={t('auth.email')} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        dir="ltr"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value={t('auth.password')} />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <label className="flex items-center">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">{t('auth.remember')}</span>
                </label>

                <div className="flex items-center justify-end gap-4">
                    {canResetPassword && (
                        <Link href={route('password.request')} className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:text-gray-100">
                            {t('auth.forgot')}
                        </Link>
                    )}
                    <PrimaryButton disabled={processing}>{t('auth.submit')}</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
