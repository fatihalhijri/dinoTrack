import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

/**
 * Switch berlabel dengan penjelasan singkat dan error validasi; tinggi minimal 40 px agar
 * mudah disentuh di HP.
 */
export default function SwitchField({
    id,
    label,
    description,
    checked,
    onCheckedChange,
    error,
}: {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-1">
            <div className="flex min-h-10 items-center justify-between gap-4">
                <div className="grid gap-0.5">
                    <Label htmlFor={id}>{label}</Label>
                    {description ? (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
                <Switch
                    id={id}
                    checked={checked}
                    onCheckedChange={onCheckedChange}
                />
            </div>
            <InputError message={error} />
        </div>
    );
}
