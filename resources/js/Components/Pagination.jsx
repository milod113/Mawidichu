import { Link } from '@inertiajs/react';

/**
 * Pagination Laravel : précédent / suivant (les libellés génériques « &laquo; » restent
 * lisibles dans les deux sens d'écriture).
 */
export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    const prev = links[0];
    const next = links[links.length - 1];
    const cls = (active) => `rounded-md border px-3 py-1.5 text-sm ${active ? 'border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800' : 'cursor-not-allowed border-gray-200 text-gray-400 dark:border-gray-800'}`;

    return (
        <nav className="flex items-center justify-between pt-2">
            {prev.url ? <Link href={prev.url} preserveScroll className={cls(true)}>‹</Link> : <span className={cls(false)}>‹</span>}
            {next.url ? <Link href={next.url} preserveScroll className={cls(true)}>›</Link> : <span className={cls(false)}>›</span>}
        </nav>
    );
}
