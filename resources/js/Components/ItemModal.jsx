import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Fenêtre de création / modification : `children(form)` rend les champs.
 * `item` null = création (POST storeUrl), sinon modification (PUT updateUrl).
 */
export default function ItemModal({ show, onClose, title, initial, item, storeUrl, updateUrl, children }) {
    const { t } = useTrans();
    const form = useForm(initial);

    useEffect(() => {
        if (show) {
            form.setData(initial);
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, item?.id]);

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (item) form.put(updateUrl, options);
        else form.post(storeUrl, options);
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                {children(form)}
                <div className="flex justify-end gap-3 pt-2">
                    <SecondaryButton onClick={onClose}>{t('common.cancel')}</SecondaryButton>
                    <PrimaryButton disabled={form.processing}>{t('common.save')}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
