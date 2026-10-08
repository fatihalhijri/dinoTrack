import { AlertCircleIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * Key error dari Action yang bukan field form (docs/08, konvensi Validasi).
 */
export const NON_FIELD_ERROR_KEYS = [
    'status',
    'customer',
    'package',
    'router',
    'invoice',
    'user',
    'role',
] as const;

/**
 * Menampilkan penolakan aturan bisnis sebagai pesan umum. Secara bawaan hanya key
 * non-field; berikan `keys` untuk memilih sendiri.
 */
export default function FormErrorAlert({
    errors,
    keys = NON_FIELD_ERROR_KEYS,
    title = 'Tidak dapat diproses',
}: {
    errors: Partial<Record<string, string>>;
    keys?: readonly string[];
    title?: string;
}) {
    const messages = keys
        .map((key) => errors[key])
        .filter((message): message is string => Boolean(message));

    if (messages.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive">
            <AlertCircleIcon />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription>
                {messages.length === 1 ? (
                    <p>{messages[0]}</p>
                ) : (
                    <ul className="list-inside list-disc">
                        {Array.from(new Set(messages)).map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}
            </AlertDescription>
        </Alert>
    );
}
