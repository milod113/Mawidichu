import { useTrans } from '@/lib/i18n';

export default function InputError({ message, className = '', ...props }) {
    const { te } = useTrans();

    return message ? (
        <p {...props} className={'text-sm text-red-600 dark:text-red-400 ' + className}>
            {te(message)}
        </p>
    ) : null;
}
