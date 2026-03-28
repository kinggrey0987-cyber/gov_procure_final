import { FormEvent } from 'react';
import { Link, router, useForm } from '@inertiajs/react';

type Attachment = {
    document_id: number;
    title: string;
    file_name: string;
    uploaded_at?: string | null;
    uploader_name?: string | null;
};

type Props = {
    title?: string;
    attachments: Attachment[];
    uploadRoute: string;
    canUpload: boolean;
    canDelete: boolean;
    downloadRouteFor: (documentId: number) => string;
    deleteRouteFor: (documentId: number) => string;
};

export default function AttachmentsPanel({
    title = 'Attachments',
    attachments,
    uploadRoute,
    canUpload,
    canDelete,
    downloadRouteFor,
    deleteRouteFor,
}: Props) {
    const form = useForm<{ title: string; file: File | null }>({
        title: '',
        file: null,
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();

        form.post(uploadRoute, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('title', 'file');
            },
        });
    };

    const onDelete = (documentId: number) => {
        if (!window.confirm('Delete this attachment?')) {
            return;
        }

        router.delete(deleteRouteFor(documentId), {
            preserveScroll: true,
        });
    };

    return (
        <section className="rounded-lg border bg-white p-6">
            <h3 className="text-base font-semibold text-slate-900">{title}</h3>

            {canUpload && (
                <form onSubmit={onSubmit} className="mt-4 grid gap-3 rounded border border-dashed p-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <label className="mb-1 block text-xs font-medium text-slate-700">Title</label>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            className="w-full rounded border-slate-300 text-sm"
                            maxLength={200}
                        />
                        {form.errors.title && <p className="mt-1 text-xs text-rose-700">{form.errors.title}</p>}
                    </div>
                    <div className="sm:col-span-2">
                        <label className="mb-1 block text-xs font-medium text-slate-700">File</label>
                        <input
                            type="file"
                            onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            className="w-full rounded border-slate-300 text-sm"
                        />
                        {form.errors.file && <p className="mt-1 text-xs text-rose-700">{form.errors.file}</p>}
                    </div>
                    <div className="sm:col-span-2">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded bg-slate-900 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                        >
                            Upload Attachment
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-4 overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                    <thead>
                        <tr className="border-b text-xs uppercase tracking-wide text-slate-500">
                            <th className="py-2">Title</th>
                            <th className="py-2">File</th>
                            <th className="py-2">Uploaded</th>
                            <th className="py-2">By</th>
                            <th className="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {attachments.length === 0 && (
                            <tr>
                                <td className="py-3 text-slate-500" colSpan={5}>No attachments yet.</td>
                            </tr>
                        )}
                        {attachments.map((attachment) => (
                            <tr key={attachment.document_id} className="border-b align-top">
                                <td className="py-2 pr-3">{attachment.title}</td>
                                <td className="py-2 pr-3">{attachment.file_name}</td>
                                <td className="py-2 pr-3">{attachment.uploaded_at ? new Date(attachment.uploaded_at).toLocaleString() : '-'}</td>
                                <td className="py-2 pr-3">{attachment.uploader_name ?? '-'}</td>
                                <td className="py-2 text-right">
                                    <div className="inline-flex gap-2">
                                        <Link
                                            href={downloadRouteFor(attachment.document_id)}
                                            className="rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700"
                                        >
                                            Download
                                        </Link>
                                        {canDelete && (
                                            <button
                                                type="button"
                                                onClick={() => onDelete(attachment.document_id)}
                                                className="rounded border border-rose-300 px-2 py-1 text-xs font-medium text-rose-700"
                                            >
                                                Delete
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
