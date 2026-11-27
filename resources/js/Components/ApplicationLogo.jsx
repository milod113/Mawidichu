export default function ApplicationLogo({ className = 'h-9 w-9' }) {
    return (
        <svg viewBox="0 0 40 40" className={className} aria-hidden="true">
            <rect width="40" height="40" rx="10" className="fill-primary-600" />
            <path d="M17 9h6v8h8v6h-8v8h-6v-8H9v-6h8z" fill="white" />
        </svg>
    );
}
