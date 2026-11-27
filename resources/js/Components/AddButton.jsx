import Icon from '@/Components/Icon';

export default function AddButton({ onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
        >
            <Icon name="plus" className="h-4 w-4" />
            {children}
        </button>
    );
}
