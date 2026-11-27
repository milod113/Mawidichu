import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { t } = useTrans();
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.verify.title')} />
            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">{t('auth.verify.intro')}</p>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">{t('auth.verify.sent')}</div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>{t('auth.verify.resend')}</PrimaryButton>
                    <Link href={route('logout')} method="post" as="button" className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:text-gray-100">
                        {t('nav.logout')}
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
