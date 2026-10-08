import EmptyState from '@/components/empty-state';
import StatusBadge from '@/components/status-badge';
import { formatDateTime } from '@/lib/format';
import type { MessageLog } from '@/types';

/**
 * Daftar pesan WhatsApp (template, waktu, status, isi yang bisa dibuka, galat) untuk detail
 * pelanggan dan detail tagihan.
 */
export default function MessageLogList({
    messages,
}: {
    messages: MessageLog[];
}) {
    if (messages.length === 0) {
        return (
            <div className="rounded-xl border bg-card">
                <EmptyState title="Belum ada pesan WhatsApp" />
            </div>
        );
    }

    return (
        <ul className="flex flex-col gap-3">
            {messages.map((message) => (
                <li
                    key={message.id}
                    className="grid grid-cols-1 gap-2 rounded-xl border bg-card p-4 text-sm"
                >
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="font-medium">
                                {message.template_label ?? 'Pesan'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatDateTime(
                                    message.sent_at ?? message.created_at,
                                )}
                            </p>
                        </div>
                        <StatusBadge
                            kind="message"
                            value={message.status}
                            label={message.status_label}
                        />
                    </div>
                    <details className="group">
                        <summary className="line-clamp-2 cursor-pointer list-none wrap-anywhere whitespace-pre-line text-muted-foreground group-open:line-clamp-none">
                            {message.body}
                        </summary>
                    </details>
                    {message.error ? (
                        <p className="text-xs text-destructive">
                            {message.error}
                        </p>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
