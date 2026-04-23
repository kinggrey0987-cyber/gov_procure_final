import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type DocumentRow = {
    document_id: number;
    title: string;
    file_name: string;
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
};

const MODULE_LABELS: Record<string, string> = {
    purchase_requests: 'Purchase Request',
    purchase_orders: 'Purchase Order',
};

export default function ProcurementDocumentsIndex({
    documents,
    filters,
    scopeModules,
    relatedRecordOptions,
    uploadEnabled,
}: Props) {
    const [showUpload, setShowUpload] = useState(false);

    const filterForm = useForm({
        module: filters.module ?? '',
        search: filters.search ?? '',
    });

    const uploadForm = useForm<{
        related_module: '' | 'purchase_requests' | 'purchase_orders';
        related_reference_id: string;
        title: string;
        file: File | null;
    }>({
        related_module: '',
        related_reference_id: '',
        title: '',
        file: null,
    });

    const optionsByModule = useMemo(() => {
        return relatedRecordOptions.reduce<Record<string, RelatedRecordOption[]>>((accumulator, option) => {
            const key = option.related_module;
            if (!accumulator[key]) {
                accumulator[key] = [];
            }
            accumulator[key].push(option);
            return accumulator;
        }, {});
    }, [relatedRecordOptions]);

    const uploadContextOptions = uploadForm.data.related_module
        ? (optionsByModule[uploadForm.data.related_module] ?? [])
        : [];

    const submitFilters = (event: FormEvent) => {
        event.preventDefault();

        router.get(route('procurement.documents.index'), filterForm.data, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        router.get(route('procurement.documents.index'));
    };

    const submitUpload = (event: FormEvent) => {
        event.preventDefault();

        if (!uploadEnabled) {
            return;
        }

        const relatedModule = uploadForm.data.related_module;
        const relatedReferenceId = Number(uploadForm.data.related_reference_id);

        if (!relatedModule || !Number.isInteger(relatedReferenceId) || relatedReferenceId <= 0) {
            return;
        }

        uploadForm.post(
            route('documents.store', {
                related_module: relatedModule,
                related_reference_id: relatedReferenceId,
            }),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    uploadForm.reset('title', 'file', 'related_reference_id');
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
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-100">BAC Repository</p>
                                    <p className="text-lg font-semibold">Procurement Documents</p>
                                    <p className="text-sm text-red-100">
                                        Upload and manage internal procurement files for purchase requests and purchase orders.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowUpload((current) => !current)}
                                    disabled={!uploadEnabled}
                                    className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Upload Document
                                </button>
                            </div>
                        </div>

                        <div className="space-y-4 p-6">
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <p className="font-semibold">Document scope and publishing flow</p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-amber-800">
                                    <li>Accepted files: PDF, DOC, DOCX, XLS, XLSX, CSV, PNG, JPG, JPEG.</li>
                                    <li>Maximum file size: 10 MB per upload.</li>
                                    <li>This BAC repository is internal. Uploaded files do not automatically become public disclosures.</li>
                                    <li>Executive users can later prepare and publish public disclosures through the Executive module, then attach selected disclosure documents for public release.</li>
                                </ul>
                            </div>

                            <form onSubmit={submitFilters} className="grid gap-3 md:grid-cols-5">
                                <select
                                    className="rounded-lg border-red-200 text-sm"
                                    value={filterForm.data.module}
                                    onChange={(event) => filterForm.setData('module', event.target.value)}
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
                                    value={filterForm.data.search}
                                    onChange={(event) => filterForm.setData('search', event.target.value)}
                                />

                                <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                    Apply
                                </button>
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                                >
                                    Reset
                                </button>
                            </form>

                            {showUpload && (
                                <form onSubmit={submitUpload} className="grid gap-3 rounded-xl border border-dashed border-red-300 bg-red-50/30 p-4 md:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">Related Module</label>
                                        <select
                                            className="w-full rounded-lg border-red-200 text-sm"
                                            value={uploadForm.data.related_module}
                                            onChange={(event) => {
                                                uploadForm.setData('related_module', event.target.value as '' | 'purchase_requests' | 'purchase_orders');
                                                uploadForm.setData('related_reference_id', '');
                                            }}
                                        >
                                            <option value="">Select module</option>
                                            <option value="purchase_requests">Purchase Requests</option>
                                            <option value="purchase_orders">Purchase Orders</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">Related Record</label>
                                        <select
                                            className="w-full rounded-lg border-red-200 text-sm"
                                            value={uploadForm.data.related_reference_id}
                                            onChange={(event) => uploadForm.setData('related_reference_id', event.target.value)}
                                            disabled={!uploadForm.data.related_module}
                                        >
                                            <option value="">Select procurement record</option>
                                            {uploadContextOptions.map((option) => (
                                                <option key={`${option.related_module}-${option.related_reference_id}`} value={option.related_reference_id}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">Document Title</label>
                                        <input
                                            type="text"
                                            className="w-full rounded-lg border-red-200 text-sm"
                                            value={uploadForm.data.title}
                                            onChange={(event) => uploadForm.setData('title', event.target.value)}
                                            maxLength={200}
                                        />
                                        {uploadForm.errors.title && <p className="mt-1 text-xs text-rose-700">{uploadForm.errors.title}</p>}
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">File</label>
                                        <input
                                            type="file"
                                            className="w-full rounded-lg border-red-200 text-sm"
                                            onChange={(event) => uploadForm.setData('file', event.target.files?.[0] ?? null)}
                                        />
                                        {uploadForm.errors.file && <p className="mt-1 text-xs text-rose-700">{uploadForm.errors.file}</p>}
                                    </div>

                                    <div className="md:col-span-2 flex items-center gap-2">
                                        <button
                                            type="submit"
                                            disabled={uploadForm.processing || !uploadEnabled}
                                            className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {uploadForm.processing ? 'Uploading...' : 'Upload Document'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowUpload(false)}
                                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
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
