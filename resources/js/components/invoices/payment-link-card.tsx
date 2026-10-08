import { Check, Copy, ExternalLink } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';

/**
 * Link halaman tagihan publik (signed URL) untuk dibagikan ke pelanggan. Hanya ditampilkan
 * untuk tagihan yang belum dibayar; pelanggan membayar QRIS dari halaman itu.
 */
export default function PaymentLinkCard({ link }: { link: string }) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === link;

    const copyLink = async (): Promise<void> => {
        if (await copy(link)) {
            toast.success('Link bayar disalin.');
        } else {
            toast.error('Gagal menyalin. Salin link secara manual.');
        }
    };

    return (
        <Card className="print:hidden">
            <CardHeader>
                <CardTitle>Link bayar</CardTitle>
                <CardDescription>
                    Bagikan ke pelanggan untuk membayar lewat QRIS. Link tetap
                    berlaku sampai tagihan lunas atau dibatalkan.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 sm:flex-row">
                <Input
                    readOnly
                    value={link}
                    aria-label="Link bayar"
                    className="h-10 min-w-0 flex-1 font-mono text-xs"
                    onFocus={(event) => event.currentTarget.select()}
                />
                <div className="grid grid-cols-2 gap-2 sm:flex">
                    <Button
                        type="button"
                        className="h-10"
                        onClick={() => void copyLink()}
                    >
                        {isCopied ? <Check /> : <Copy />}
                        {isCopied ? 'Disalin' : 'Salin'}
                    </Button>
                    <Button asChild variant="outline" className="h-10">
                        <a
                            href={link}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink />
                            Buka
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
