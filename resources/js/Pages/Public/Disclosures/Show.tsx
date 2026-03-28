import { Head } from '@inertiajs/react';

type Props = {
    disclosure: {
        disclosure_id: number;
        title: string;
        type: string;
        description?: string | null;
        published_at?: string | null;
        file_path?: string | null;
    };
    attachments: Array<{
        document_id: number;
        title: string;
        file_name: string;
        uploaded_at?: string | null;
    }>;
};

export default function PublicDisclosureShow({ disclosure, attachments }: Props) {
    return (
        <>
            <Head title={disclosure.title} />
            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <h1 className="text-2xl font-bold text-slate-900">{disclosure.title}</h1>
                        <p><strong>ID:</strong> {disclosure.disclosure_id}</p>
                        <p><strong>Type:</strong> {disclosure.type}</p>
                        <p><strong>Published:</strong> {disclosure.published_at ?? '-'}</p>
                        <p><strong>Description:</strong> {disclosure.description ?? '-'}</p>
                        <p><strong>File:</strong> {disclosure.file_path ?? '-'}</p>
                    </div>

                    <div className="mt-4 rounded border bg-white p-6 text-sm">
                        <h2 className="text-base font-semibold text-slate-900">Disclosure Attachments</h2>
                        <ul className="mt-3 space-y-2">
                            {attachments.length === 0 && <li className="text-slate-500">No public attachments available.</li>}
                            {attachments.map((attachment) => (
                                <li key={attachment.document_id} className="flex flex-wrap items-center justify-between gap-3 border-b pb-2">
                                    <div>
                                        <p className="font-medium text-slate-900">{attachment.title}</p>
                                        <p className="text-xs text-slate-600">{attachment.file_name}</p>
                                    </div>
                                    <a
                                        href={route('public.disclosures.documents.download', {
                                            public_disclosure: disclosure.disclosure_id,
                                            document: attachment.document_id,
                                        })}
                                        className="rounded border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700"
                                    >
                                        Download
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </>
    );
}
