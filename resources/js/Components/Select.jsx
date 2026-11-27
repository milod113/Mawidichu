import { inputClass } from '@/Components/Field';

export default function Select({ children, className = '', ...props }) {
    return (
        <select {...props} className={`${inputClass} ${className}`}>
            {children}
        </select>
    );
}
