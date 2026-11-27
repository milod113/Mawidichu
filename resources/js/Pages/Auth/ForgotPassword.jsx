import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.forgot.title')} />

            <h2 className="mb-2 text-lg font-semibold">{t('auth.forgot.title')}</h2>
            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">{t('auth.forgot.intro')}</p>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">{status}</div>
            )}

            <form onSubmit={submit}>
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    dir="ltr"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    aria-label={t('auth.email')}
                    onChange={(e) => setData('email', e.target.value)}
                />
                <InputError message={errors.email} className="mt-2" />

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton disabled={processing}>{t('auth.forgot.submit')}</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
