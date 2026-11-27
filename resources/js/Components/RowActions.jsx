import Icon from '@/Components/Icon';
import { useTrans } from '@/lib/i18n';
import { router } from '@inertiajs/react';

export default function RowActions({ onEdit, deleteUrl }) {
    const { t } = useTrans();

    const remove = () => {
        if (window.confirm(t('common.confirm_delete'))) {
            router.delete(deleteUrl, { preserveScroll: true });
        }
    };

    return (
        <div className="flex justify-end gap-1">
            <button type="button" onClick={onEdit} title={t('common.edit')} className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-primary-700 dark:text-gray-400 dark:hover:bg-gray-800">
                <Icon name="pencil" className="h-4 w-4" />
                <span className="sr-only">{t('common.edit')}</span>
            </button>
            <button type="button" onClick={remove} title={t('common.delete')} className="rounded-md p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10">
                <Icon name="trash" className="h-4 w-4" />
                <span className="sr-only">{t('common.delete')}</span>
            </button>
        </div>
    );
}
