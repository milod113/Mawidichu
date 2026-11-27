import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';

export default function Field({ label, error, hint, htmlFor, className = '', children }) {
    return (
        <div className={className}>
            {label && <InputLabel htmlFor={htmlFor} value={label} />}
            <div className="mt-1">{children}</div>
            {hint && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
            <InputError message={error} className="mt-1" />
        </div>
    );
}

export const inputClass =
    'block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
