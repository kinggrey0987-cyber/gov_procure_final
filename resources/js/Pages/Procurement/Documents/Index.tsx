import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type DocumentRow = {
    document_id: number;
    title: string;
    file_name: string;
    document_kind?: string | null;
    related_module: string;
    related_reference_id: number;
    related_reference_label?: string | null;
    uploaded_at?: string | null;
    uploader_name?: string | null;
};

type RelatedRecordOption = {
    related_module: 'purchase_requests' | 'purchase_orders';
    related_reference_id: number;
    label: string;
};

type Props = {
    documents: {
        data: DocumentRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        module?: string;
        search?: string;
    };
    scopeModules: string[];
    relatedRecordOptions: RelatedRecordOption[];
    uploadEnabled: boolean;
    uploadDefaults?: {
        related_module?: '' | 'purchase_requests' | 'purchase_orders';
        related_reference_id?: number | null;
    };
};

const MODULE_LABELS: Record<string, string> = {
    purchase_requests: 'Purchase Request',
    purchase_orders: 'Purchase Order',
};

const DOCUMENT_KIND_LABELS: Record<string, string> = {
    noa: 'Notice of Award (NOA)',
    canvass_sheet: 'Abstract of Canvass / Canvass Sheet',
    supplier_quotation: 'Winning / Bidder Quotation',
    signed_po: 'Signed PO with Conforme',
    procurement_record: 'BAC Resolution / Procurement Record',
    other_supporting_doc: 'Other Supporting Document',
};

const DOCUMENT_KIND_OPTIONS = [
    { value: 'noa', label: 'Notice of Award (NOA)' },
    { value: 'canvass_sheet', label: 'Abstract of Canvass / Canvass Sheet' },
    { value: 'supplier_quotation', label: 'Winning / Bidder Quotation' },
    { value: 'signed_po', label: 'Signed PO with Conforme' },
    { value: 'procurement_record', label: 'BAC Resolution / Procurement Record' },
    { value: 'other_supporting_doc', label: 'Other Supporting Document' },
];

export default function ProcurementDocumentsIndex({
    documents,
    filters,
    scopeModules,
    relatedRecordOptions,
    uploadEnabled,
    uploadDefaults,
}: Props) {
    const defaultRelatedReferenceId = uploadDefaults?.related_reference_id ? String(uploadDefaults.related_reference_id) : '';
    const [showUpload, setShowUpload] = useState(defaultRelatedReferenceId !== '');

    const [moduleFilter, setModuleFilter] = useState(filters.module ?? '');
    const [searchFilter, setSearchFilter] = useState(filters.search ?? '');
    const debouncedSearch = useDebouncedValue(searchFilter, 450);

    useEffect(() => {
        setModuleFilter(filters.module ?? '');
        setSearchFilter(filters.search ?? '');
    }, [filters.module, filters.search]);

    const activeFilters = useMemo(
        () => ({
            module: moduleFilter,
            search: debouncedSearch,
        }),
        [debouncedSearch, moduleFilter],
    );

    useEffect(() => {
        router.get(route('procurement.documents.index'), activeFilters, {
            preserveState: true,
            replace: true,
        });
    }, [activeFilters]);

    const uploadForm = useForm<{
        related_module: 'purchase_orders';
        related_reference_id: string;
        document_kind: string;
        title: string;
        file: File | null;
    }>({
        related_module: 'purchase_orders',
        related_reference_id: defaultRelatedReferenceId,
        document_kind: '',
        title: '',
        file: null,
    });

    const resetFilters = () => {
        router.get(route('procurement.documents.index'));
    };

    const submitUpload = (event: FormEvent) => {
        event.preventDefault();

        if (!uploadEnabled) {
            return;
        }

        const relatedReferenceId = Number(uploadForm.data.related_reference_id);
        const documentKind = uploadForm.data.document_kind;

        if (!Number.isInteger(relatedReferenceId) || relatedReferenceId <= 0 || !documentKind) {
            return;
        }

        uploadForm.post(
            route('documents.store', {
                related_module: 'purchase_orders',
                related_reference_id: relatedReferenceId,
                document_kind: documentKind,
            }),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    uploadForm.reset('title', 'file', 'related_reference_id', 'document_kind');
                    setShowUpload(false);
                },
            },
        );
    };

    const deleteDocument = (documentId: number) => {
        if (!window.confirm('Delete this procurement document?')) {
            return;
        }

        router.delete(route('documents.destroy', documentId), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Procurement Documents</h2>}>
            <Head title="Procurement Documents" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-red-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-4 text-white">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">Procurement Unit Repository</p>
                                    <p className="text-lg font-semibold">Procurement Documents</p>
                                    <p className="text-sm text-red-100">
                                        Upload and manage official supporting documents (NOA, Canvass Sheets, Signed PO, Supplier Quotations) for finished purchase orders.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowUpload((current) => !current)}
                                    disabled={!uploadEnabled}
                                    className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 transition-colors shadow-sm"
                                >
                                    {showUpload ? 'Close Upload Form' : 'Upload Document'}
                                </button>
                            </div>
                        </div>

                        <div className="space-y-4 p-6">
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <p className="font-semibold">Document scope and publishing flow</p>
                                <p className="mt-1 text-amber-800">
                                    This repository stores official supporting attachments for placed and finished Purchase Orders. These documents serve as verified procurement records and can be attached to Executive Public Disclosures.
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-amber-800">
                                    <li>Accepted files: PDF, DOC, DOCX, and CSV only (max 10 MB per file).</li>
                                    <li>Applicable attachments: Notice of Award (NOA), Canvass Sheets / Abstract of Quotations, Winning Supplier Quotations, Signed PO with Conforme, and BAC Resolutions.</li>
                                    <li>Do not store delivery receipts, inspection reports, or distribution records here (those belong in the Delivery & Inventory workflows).</li>
                                    <li>This repository is internal. Executive users can select these verified documents when publishing official transparency disclosures on the Public Portal.</li>
                                </ul>
                            </div>

                            <div className="grid gap-3 md:grid-cols-4">
                                <select
                                    className="rounded-lg border-red-200 text-sm"
                                    value={moduleFilter}
                                    onChange={(event) => setModuleFilter(event.target.value)}
                                >
                                    <option value="">All Procurement Modules</option>
                                    {scopeModules.map((moduleCode) => (
                                        <option key={moduleCode} value={moduleCode}>
                                            {MODULE_LABELS[moduleCode] ?? moduleCode}
                                        </option>
                                    ))}
                                </select>

                                <input
                                    type="text"
                                    placeholder="Search title or file name"
                                    className="md:col-span-2 rounded-lg border-red-200 text-sm"
                                    value={searchFilter}
                                    onChange={(event) => setSearchFilter(event.target.value)}
                                />
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                                >
                                    Reset
                                </button>
                            </div>

                            {showUpload && (
                                <form onSubmit={submitUpload} className="grid gap-4 rounded-xl border border-dashed border-red-300 bg-red-50/40 p-5 md:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">Target Workflow</label>
                                        <div className="flex items-center h-[38px] px-3 rounded-lg border border-red-200 bg-white text-sm font-medium text-red-900 shadow-sm">
                                            <span className="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 mr-2 shrink-0"></span>
                                            <span className="truncate">Finished Purchase Orders (Official Award / Execution)</span>
                                        </div>
                                        <p className="mt-1 text-[11px] text-slate-500">Supporting attachments are restricted to placed and completed purchase orders.</p>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            Purchase Order <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            className="w-full rounded-lg border-red-200 text-sm focus:border-red-500 focus:ring-red-500"
                                            value={uploadForm.data.related_reference_id}
                                            onChange={(event) => uploadForm.setData('related_reference_id', event.target.value)}
                                            required
                                        >
                                            <option value="">Select placed or finished purchase order...</option>
                                            {relatedRecordOptions.map((option) => (
                                                <option key={`po-${option.related_reference_id}`} value={option.related_reference_id}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        {uploadForm.errors.related_reference_id && (
                                            <p className="mt-1 text-xs text-rose-700">{uploadForm.errors.related_reference_id}</p>
                                        )}
                                    </div>

                                    {relatedRecordOptions.length === 0 && (
                                        <div className="md:col-span-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                            <strong>Notice:</strong> No placed or completed purchase orders found. Only purchase orders that have completed approval and placement are eligible for official document attachment.
                                        </div>
                                    )}

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            Document Type <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            className="w-full rounded-lg border-red-200 text-sm focus:border-red-500 focus:ring-red-500"
                                            value={uploadForm.data.document_kind}
                                            onChange={(event) => uploadForm.setData('document_kind', event.target.value)}
                                            required
                                        >
                                            <option value="">Select document type...</option>
                                            {DOCUMENT_KIND_OPTIONS.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        {uploadForm.errors.document_kind && <p className="mt-1 text-xs text-rose-700">{uploadForm.errors.document_kind}</p>}
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            Document Title <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            placeholder="e.g., Notice of Award - National Store Trading"
                                            className="w-full rounded-lg border-red-200 text-sm focus:border-red-500 focus:ring-red-500"
                                            value={uploadForm.data.title}
                                            onChange={(event) => uploadForm.setData('title', event.target.value)}
                                            maxLength={200}
                                            required
                                        />
                                        {uploadForm.errors.title && <p className="mt-1 text-xs text-rose-700">{uploadForm.errors.title}</p>}
                                    </div>

                                    <div className="md:col-span-2">
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            File Attachment <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="file"
                                            accept=".pdf,.doc,.docx,.csv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/csv"
                                            className="w-full rounded-lg border border-red-200 bg-white p-2 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-red-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-red-700 hover:file:bg-red-100"
                                            onChange={(event) => uploadForm.setData('file', event.target.files?.[0] ?? null)}
                                            required
                                        />
                                        <p className="mt-1 text-xs text-slate-500">PDF, DOC, DOCX, or CSV (max 10 MB). Excludes delivery receipts and inspection reports.</p>
                                        {uploadForm.errors.file && <p className="mt-1 text-xs text-rose-700">{uploadForm.errors.file}</p>}
                                    </div>

                                    <div className="md:col-span-2 flex items-center gap-2 pt-2 border-t border-red-200/60">
                                        <button
                                            type="submit"
                                            disabled={uploadForm.processing || !uploadEnabled || relatedRecordOptions.length === 0}
                                            className="rounded-lg bg-red-700 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 transition-colors"
                                        >
                                            {uploadForm.processing ? 'Uploading...' : 'Upload Document'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowUpload(false)}
                                            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h3 className="text-xl font-semibold text-slate-900">Document Registry</h3>
                            <p className="mt-1 text-sm text-slate-600">Internal procurement files only. Public visibility remains disclosure-based.</p>
                        </div>

                        {documents.data.length === 0 ? (
                            <div className="px-6 py-10 text-sm text-slate-600">
                                <p className="font-semibold text-slate-800">No procurement documents uploaded yet.</p>
                                <p className="mt-1">Upload a document to attach files to procurement records.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Title</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Type</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">File</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Reference</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Uploaded</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Uploaded By</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {documents.data.map((document) => (
                                            <tr key={document.document_id} className="align-top">
                                                <td className="px-4 py-3">
                                                    <p className="font-semibold text-slate-900">{document.title}</p>
                                                    <p className="mt-1 text-xs text-slate-500">{MODULE_LABELS[document.related_module] ?? document.related_module}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-700">
                                                    {DOCUMENT_KIND_LABELS[document.document_kind ?? ''] ?? document.document_kind ?? 'General'}
                                                </td>
                                                <td className="px-4 py-3 text-slate-700">
                                                    <p>{document.file_name}</p>
                                                    <p className="mt-1 text-xs text-slate-500">{fileTypeLabel(document.file_name)}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-700">
                                                    {document.related_reference_label ?? `#${document.related_reference_id}`}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {document.uploaded_at ? new Date(document.uploaded_at).toLocaleString() : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{document.uploader_name ?? 'System'}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="inline-flex flex-wrap justify-end gap-2">
                                                        <a
                                                            href={route('documents.show', document.document_id)}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="rounded border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                        >
                                                            View
                                                        </a>
                                                        <a
                                                            href={route('documents.show', document.document_id)}
                                                            className="rounded border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                        >
                                                            Download
                                                        </a>
                                                        <button
                                                            type="button"
                                                            onClick={() => deleteDocument(document.document_id)}
                                                            className="rounded border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {documents.links?.length > 3 && (
                            <div className="border-t border-slate-200 px-6 py-4">
                                <PaginationNav links={documents.links} />
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function fileTypeLabel(fileName: string): string {
    const extension = fileName.split('.').pop()?.toUpperCase();
    if (!extension) {
        return 'Unknown file type';
    }

    return `${extension} file`;
}





