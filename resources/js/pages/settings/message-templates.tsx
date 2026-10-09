import { Head } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import MessageTemplateCard from '@/components/settings/message-template-card';
import { index } from '@/routes/settings/message-templates';
import type { MessageTemplate } from '@/types';

type MessageTemplatesProps = {
    templates: MessageTemplate[];
    placeholders: Record<string, string>;
    placeholder_examples: Record<string, string>;
    max_length: number;
};

export default function MessageTemplates({
    templates,
    placeholders,
    placeholder_examples: placeholderExamples,
    max_length: maxLength,
}: MessageTemplatesProps) {
    return (
        <>
            <Head title="Template WhatsApp" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Template WhatsApp"
                    description="Pesan otomatis untuk pelanggan. Klik placeholder untuk menyisipkannya di posisi kursor."
                />

                {templates.length === 0 ? (
                    <EmptyState
                        icon={MessageSquare}
                        title="Belum ada template"
                        description="Jalankan MessageTemplateSeeder untuk membuat template bawaan."
                    />
                ) : (
                    templates.map((template) => (
                        <MessageTemplateCard
                            key={template.id}
                            template={template}
                            placeholders={placeholders}
                            examples={placeholderExamples}
                            maxLength={maxLength}
                        />
                    ))
                )}
            </div>
        </>
    );
}

MessageTemplates.layout = {
    breadcrumbs: [
        {
            title: 'Template WhatsApp',
            href: index(),
        },
    ],
};
