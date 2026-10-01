import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function FlashMessage() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);
    const message = flash?.success || flash?.error || flash?.status;

    useEffect(() => {
        if (message) {
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [message]);

    if (!visible || !message) return null;

    const isError = !!flash?.error;

    return (
        <div role="status" className={`fixed right-4 top-20 z-[60] max-w-sm rounded-xl border p-4 text-sm shadow-xl sm:right-6 ${
                isError
                    ? 'bg-red-50 border-red-200 text-red-800'
                    : 'bg-green-50 border-green-200 text-green-800'
            }`}
        >
            {message}
        </div>
    );
}
