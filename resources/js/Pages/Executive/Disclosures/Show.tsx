import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import ProcurementTraceabilityMatrix from '@/Components/Executive/ProcurementTraceabilityMatrix';
import { Head, Link, useForm } from '@inertiajs/react';

type ProcurementDocument = {
    document_id: number;
    title: string;
    file_name: string;
    document_kind?: string | null;
    related_module: string;
    related_reference_id: number;
    uploaded_at?: string | null;
    uploader_name?: string | null;
};

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
        document_kind?: string | null;
    }>;
    availableProcurementDocuments: ProcurementDocument[];
    sources: Array<{
        id: number;
        source_role: string;
        source_label: string;
        source_type: string;
        source_id: number;
        snapshot?: Record<string, string | number | null>;
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

export default function DisclosureShow({ disclosure, attachments, availableProcurementDocuments, sources, attachmentPermissions, statusHistory, approvalHistory }: Props) {
    const publishForm = useForm({ remarks: '' });
    const attachProcurementForm = useForm<{ document_ids: number[] }>({ document_ids: [] });

    const onPublish = () => {
        publishForm.put(route('executive.disclosures.publish', disclosure.disclosure_id), {
            preserveScroll: true,
        });
    };

    const toggleProcurementDocument = (documentId: number) => {
        attachProcurementForm.setData(
            'document_ids',
            attachProcurementForm.data.document_ids.includes(documentId)
                ? attachProcurementForm.data.document_ids.filter((current) => current !== documentId)
                : [...attachProcurementForm.data.document_ids, documentId],
        );
    };

    const attachSelectedProcurementDocuments = () => {
        attachProcurementForm.post(route('executive.disclosures.attach-procurement-documents', disclosure.disclosure_id), {
            preserveScroll: true,
            onSuccess: () => attachProcurementForm.reset('document_ids'),
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
                        Executive governance access: prepare and publish public disclosures.
                    </div>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <ProcurementTraceabilityMatrix
                            sources={sources}
                            title="Disclosure Package Sources & Traceability"
                            description="Source records, allocated funds, and line-item deliverables frozen into this official disclosure package."
                        />
                    </section>

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
                        downloadRouteFor={(documentId: number) =>
                            disclosure.status === 'PUBLISHED'
                                ? route('public.disclosures.documents.download', {
                                    public_disclosure: disclosure.disclosure_id,
                                    document: documentId,
                                })
                                : route('documents.show', documentId)
                        }
                        deleteRouteFor={(documentId: number) => route('documents.destroy', documentId)}
                    />

                    {disclosure.status === 'DRAFT' && (
                        <section className="rounded-lg border bg-white p-6">
                            <h3 className="text-base font-semibold text-slate-900">Attach Procurement Unit Uploads</h3>
                            <p className="mt-1 text-sm text-slate-600">
                                Select internal PR/PO uploads that are cleared for inclusion in this public disclosure. The system copies selected files into this disclosure package.
                            </p>

                            {availableProcurementDocuments.length === 0 ? (
                                <p className="mt-4 rounded border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-500">
                                    No procurement uploads are available for this disclosure source yet.
                                </p>
                            ) : (
                                <div className="mt-4 space-y-3">
                                    {availableProcurementDocuments.map((document) => (
                                        <label key={document.document_id} className="flex gap-3 rounded border border-slate-200 p-3 text-sm hover:bg-slate-50">
                                            <input
                                                type="checkbox"
                                                className="mt-1 rounded border-slate-300 text-red-700 focus:ring-red-600"
                                                checked={attachProcurementForm.data.document_ids.includes(document.document_id)}
                                                onChange={() => toggleProcurementDocument(document.document_id)}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-semibold text-slate-900">{document.title}</span>
                                                <span className="mt-1 block text-xs text-slate-600">
                                                    {document.file_name} • {document.related_module.replaceAll('_', ' ')} #{document.related_reference_id}
                                                </span>
                                                <span className="mt-1 block text-xs text-slate-500">
                                                    Uploaded {document.uploaded_at ? new Date(document.uploaded_at).toLocaleString() : '-'} by {document.uploader_name ?? 'System'}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                    {attachProcurementForm.errors.document_ids && <p className="text-xs text-rose-700">{attachProcurementForm.errors.document_ids}</p>}
                                    <button
                                        type="button"
                                        onClick={attachSelectedProcurementDocuments}
                                        disabled={attachProcurementForm.processing || attachProcurementForm.data.document_ids.length === 0}
                                        className="rounded bg-red-700 px-4 py-2 text-xs font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Attach Selected to Disclosure
                                    </button>
                                </div>
                            )}
                        </section>
                    )}

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


