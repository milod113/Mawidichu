import Checkbox from '@/Components/Checkbox';

export default function CheckField({ label, checked, onChange, id }) {
    return (
        <label htmlFor={id} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <Checkbox id={id} checked={!!checked} onChange={(e) => onChange(e.target.checked)} />
            {label}
        </label>
    );
}
