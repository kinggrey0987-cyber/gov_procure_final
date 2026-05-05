import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    disclosure: {
        disclosure_id: number;
        title: string;
        type: string;
        status: string;
        description?: string | null;
        file_path?: string | null;
    };
    attachments: Array<{
        document_id: number;
        title: string;
        file_name: string;
        uploaded_at?: string | null;
        uploader_name?: string | null;
    }>;
    attachmentPermissions: {
        upload: boolean;
        delete: boolean;
    };
    statusHistory: Array<{
        id: number;
        old_status?: string | null;
        new_status: string;
        remarks?: string | null;
        changed_at?: string | null;
        changed_by?: { id: number; name: string } | null;
    }>;
    approvalHistory: Array<{
        id: number;
        status: string;
        remarks?: string | null;
        approved_at?: string | null;
        approver?: { id: number; name: string } | null;
    }>;
};

export default function DisclosureShow({ disclosure, attachments, attachmentPermissions, statusHistory, approvalHistory }: Props) {
    const publishForm = useForm({ remarks: '' });

    const onPublish = () => {
        publishForm.put(route('executive.disclosures.publish', disclosure.disclosure_id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout header="Disclosure Detail">
            <Head title="Disclosure Detail" />
            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white px-6 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Disclosure Governance</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">Disclosure Record Detail</h3>
                    <p className="mt-1 text-sm text-slate-600">Executive review details and publication readiness context.</p>
                </section>

                <div className="space-y-5">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 className="text-xl font-semibold text-slate-900">{disclosure.title}</h4>
                                <p className="mt-1 text-sm text-slate-600">Disclosure ID #{disclosure.disclosure_id}</p>
                            </div>
                            <span className={disclosure.status === 'PUBLISHED' ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800' : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800'}>
                                {disclosure.status}
                            </span>
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <DetailCell label="Type" value={disclosure.type} />
                            <DetailCell label="File" value={disclosure.file_path ? disclosure.file_path.split('/').pop() ?? disclosure.file_path : 'No file attached'} />
                            <DetailCell label="Description" value={disclosure.description?.trim() || 'No description provided'} />
                        </div>
                    </section>

                    <div className="flex flex-wrap gap-2">
                        {disclosure.status === 'DRAFT' && (
                            <Link href={route('executive.disclosures.edit', disclosure.disclosure_id)} className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Edit Draft
                            </Link>
                        )}
                        <Link href={route('executive.transparency-monitoring')} className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Back to Transparency & Disclosures
                        </Link>
                    </div>

                    <div className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        Executive governance access: prepare, review, and publish public disclosures.
                    </div>

                    {disclosure.status === 'DRAFT' && (
                        <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
                            <h3 className="text-lg font-semibold text-slate-900">Publish Disclosure</h3>
                            <p className="mt-1 text-sm text-slate-600">Publishing makes this disclosure visible to public pages and public downloads.</p>
                            <textarea
                                className="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Publication remarks (optional)"
                                value={publishForm.data.remarks}
                                onChange={(event) => publishForm.setData('remarks', event.target.value)}
                            />
                            <button
                                type="button"
                                onClick={onPublish}
                                disabled={publishForm.processing}
                                className="mt-3 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60"
                            >
                                Publish Disclosure
                            </button>
                        </section>
                    )}

                    <AttachmentsPanel
                        title="Disclosure Attachments"
                        attachments={attachments}
                        uploadRoute={route('documents.store', { related_module: 'public_disclosures', related_reference_id: disclosure.disclosure_id })}
                        canUpload={attachmentPermissions.upload}
                        canDelete={attachmentPermissions.delete}
                        downloadRouteFor={(documentId: number) => route('documents.show', documentId)}
                        deleteRouteFor={(documentId: number) => route('documents.destroy', documentId)}
                    />

                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function DetailCell({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 text-sm font-semibold text-slate-900">{value}</p>
        </div>
    );
}





