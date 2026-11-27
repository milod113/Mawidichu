import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.confirm'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.confirm.title')} />
            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">{t('auth.confirm.intro')}</p>

            <form onSubmit={submit}>
                <InputLabel htmlFor="password" value={t('auth.password')} />
                <TextInput
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('password', e.target.value)}
                />
                <InputError message={errors.password} className="mt-2" />

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton disabled={processing}>{t('auth.confirm.submit')}</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
