const tones = {
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    teal: 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
};

export default function Badge({ tone = 'gray', children }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${tones[tone]}`}>
            {children}
        </span>
    );
}
