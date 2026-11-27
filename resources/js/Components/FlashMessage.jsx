import { useTrans } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Message de confirmation (clé de traduction posée par le serveur dans la
 * session : flash.success), masqué automatiquement.
 */
export default function FlashMessage() {
    const { t } = useTrans();
    const flash = usePage().props.flash?.success;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!flash) return undefined;
        setVisible(true);
        const timer = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(timer);
    }, [flash]);

    if (!flash || !visible) return null;

    return (
        <div
            role="status"
            className="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30"
        >
            {t(`flash.${flash}`)}
        </div>
    );
}
