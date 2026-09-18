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

type StagedFile = {
    id: string;
    file: File;
    title: string;
    document_kind: string;
    isImage: boolean;
};

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

    // Multi-attachment staging state
    const [selectedPoId, setSelectedPoId] = useState<string>(defaultRelatedReferenceId);
    const [defaultKind, setDefaultKind] = useState<string>('noa');
    const [stagedFiles, setStagedFiles] = useState<StagedFile[]>([]);
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

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

    const resetFilters = () => {
        router.get(route('procurement.documents.index'));
    };

    const handleFilesSelected = (event: React.ChangeEvent<HTMLInputElement>) => {
        const fileList = event.target.files;
        if (!fileList || fileList.length === 0) return;

        const newStaged: StagedFile[] = Array.from(fileList).map((f) => {
            const isImg = f.type.startsWith('image/') || /\.(png|jpe?g|webp|gif|bmp)$/i.test(f.name);
            const baseName = f.name.replace(/\.[^/.]+$/, '');
            return {
                id: Math.random().toString(36).substring(2, 9),
                file: f,
                title: baseName,
                document_kind: defaultKind || 'noa',
                isImage: isImg,
            };
        });

        setStagedFiles((prev) => [...prev, ...newStaged]);
        setFormErrors({});
        event.target.value = '';
    };

    const removeStagedFile = (id: string) => {
        setStagedFiles((prev) => prev.filter((item) => item.id !== id));
    };

    const updateStagedFile = (id: string, field: 'title' | 'document_kind', value: string) => {
        setStagedFiles((prev) =>
            prev.map((item) => (item.id === id ? { ...item, [field]: value } : item))
        );
    };

    const submitUpload = (event: FormEvent) => {
        event.preventDefault();

        if (!uploadEnabled) {
            return;
        }

        const relatedReferenceId = Number(selectedPoId);
        if (!Number.isInteger(relatedReferenceId) || relatedReferenceId <= 0) {
            setFormErrors({ related_reference_id: 'Please select a placed or finished purchase order.' });
            return;
        }

        if (stagedFiles.length === 0) {
            setFormErrors({ files: 'Please attach at least one document file to upload.' });
            return;
        }

        setIsSubmitting(true);
        setFormErrors({});

        const formData = new FormData();
        formData.append('related_module', 'purchase_orders');
        formData.append('related_reference_id', String(relatedReferenceId));

        stagedFiles.forEach((item) => {
            formData.append('files[]', item.file);
            formData.append('titles[]', item.title || item.file.name);
            formData.append('document_kinds[]', item.document_kind || defaultKind || 'noa');
        });

        router.post(
            route('documents.store', {
                related_module: 'purchase_orders',
                related_reference_id: relatedReferenceId,
            }),
            formData,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setStagedFiles([]);
                    setShowUpload(false);
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    setFormErrors(errors);
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
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

    const imageCount = stagedFiles.filter((f) => f.isImage).length;

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
                                    <p className="text-lg font-semibold">Procurement Documents & PO Attachments Registry</p>
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
                                    {showUpload ? 'Close Upload Form' : 'Upload Attachments'}
                                </button>
                            </div>
                        </div>

                        <div className="space-y-4 p-6">
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <p className="font-semibold flex items-center gap-2">
                                    <span className="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                    Government Procurement Archival Standards & Multi-Attachment Flow
                                </p>
                                <p className="mt-1 text-amber-800">
                                    In real government procurement, a completed Purchase Order comprises multiple supporting attachments (Notice of Award, Abstract of Canvass, Winning Supplier Quotations, Conforme, and BAC Resolutions).
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-amber-800">
                                    <li><strong>Multiple Attachments:</strong> You can select and stage multiple files simultaneously for a single Purchase Order package.</li>
                                    <li><strong>Auto Image-to-PDF Conversion:</strong> Uploaded image scans (PNG, JPG, JPEG, WebP) are automatically converted into legal archival PDF documents with official registry metadata banners.</li>
                                    <li><strong>Accepted Formats:</strong> PDF, PNG, JPG, JPEG, WebP, Word (DOC/DOCX), and CSV (max 15 MB per file).</li>
                                    <li><strong>Public Transparency:</strong> Uploaded procurement documents remain internal until selected by Executive users for official Public Transparency Disclosures.</li>
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
                                <form onSubmit={submitUpload} className="space-y-5 rounded-xl border-2 border-dashed border-red-300 bg-red-50/40 p-6">
                                    <div className="grid gap-4 md:grid-cols-2">
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
                                                value={selectedPoId}
                                                onChange={(event) => setSelectedPoId(event.target.value)}
                                                required
                                            >
                                                <option value="">Select placed or finished purchase order...</option>
                                                {relatedRecordOptions.map((option) => (
                                                    <option key={`po-${option.related_reference_id}`} value={option.related_reference_id}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {formErrors.related_reference_id && (
                                                <p className="mt-1 text-xs text-rose-700">{formErrors.related_reference_id}</p>
                                            )}
                                        </div>
                                    </div>

                                    {relatedRecordOptions.length === 0 && (
                                        <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                            <strong>Notice:</strong> No placed or completed purchase orders found. Only purchase orders that have completed approval and placement are eligible for official document attachment.
                                        </div>
                                    )}

                                    {/* Default Kind Selector & File Picker */}
                                    <div className="rounded-xl border border-red-200 bg-white p-5 shadow-sm space-y-4">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <h4 className="text-sm font-bold text-slate-900">Select Attachments for this Purchase Order</h4>
                                                <p className="text-xs text-slate-500">Select one or multiple files. Images will be automatically transformed into legal archival PDFs.</p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <label className="text-xs font-semibold text-slate-600">Default Type:</label>
                                                <select
                                                    className="rounded-lg border-slate-300 text-xs py-1.5 focus:border-red-500 focus:ring-red-500"
                                                    value={defaultKind}
                                                    onChange={(event) => setDefaultKind(event.target.value)}
                                                >
                                                    {DOCUMENT_KIND_OPTIONS.map((option) => (
                                                        <option key={option.value} value={option.value}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>

                                        <div className="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-red-300 bg-red-50/30 p-6 text-center hover:bg-red-50/60 transition cursor-pointer">
                                            <input
                                                type="file"
                                                multiple
                                                accept=".pdf,.doc,.docx,.csv,.png,.jpg,.jpeg,.webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/csv,image/png,image/jpeg,image/webp"
                                                className="absolute inset-0 h-full w-full opacity-0 cursor-pointer"
                                                onChange={handleFilesSelected}
                                            />
                                            <div className="flex flex-col items-center pointer-events-none">
                                                <div className="rounded-full bg-red-100 p-3 text-red-600 mb-2">
                                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    </svg>
                                                </div>
                                                <p className="text-sm font-semibold text-slate-800">
                                                    <span className="text-red-700 underline font-bold">Click to browse</span> or drag and drop multiple files
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    Images (PNG, JPG, WebP) &bull; Documents (PDF, DOCX, CSV) &bull; Max 15 MB each
                                                </p>
                                            </div>
                                        </div>
                                        {formErrors.files && <p className="text-xs text-rose-700">{formErrors.files}</p>}
                                        {formErrors.file && <p className="text-xs text-rose-700">{formErrors.file}</p>}
                                    </div>

                                    {/* Staging Queue */}
                                    {stagedFiles.length > 0 && (
                                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                                            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="text-sm font-bold text-slate-900">
                                                        Staged Supporting Attachments ({stagedFiles.length})
                                                    </h4>
                                                    {imageCount > 0 && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                            &#10024; {imageCount} image{imageCount > 1 ? 's' : ''} will convert to PDF
                                                        </span>
                                                    )}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => setStagedFiles([])}
                                                    className="text-xs font-semibold text-rose-600 hover:text-rose-800"
                                                >
                                                    Clear All
                                                </button>
                                            </div>

                                            <div className="divide-y divide-slate-100 max-h-96 overflow-y-auto pr-1 space-y-3">
                                                {stagedFiles.map((item, index) => (
                                                    <div key={item.id} className="pt-3 first:pt-0 grid gap-3 sm:grid-cols-12 items-center">
                                                        <div className="sm:col-span-4 min-w-0">
                                                            <p className="text-xs font-semibold text-slate-900 truncate" title={item.file.name}>
                                                                {index + 1}. {item.file.name}
                                                            </p>
                                                            <div className="flex items-center gap-2 mt-1">
                                                                <span className="text-[11px] text-slate-500 font-mono">
                                                                    {formatFileSize(item.file.size)}
                                                                </span>
                                                                {item.isImage ? (
                                                                    <span className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                                                        Auto Image&rarr;PDF
                                                                    </span>
                                                                ) : item.file.name.toLowerCase().endsWith('.pdf') ? (
                                                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800">
                                                                        Standard PDF
                                                                    </span>
                                                                ) : (
                                                                    <span className="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-800">
                                                                        Document
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>

                                                        <div className="sm:col-span-4">
                                                            <label className="sr-only">Title</label>
                                                            <input
                                                                type="text"
                                                                value={item.title}
                                                                onChange={(e) => updateStagedFile(item.id, 'title', e.target.value)}
                                                                placeholder="Document Title"
                                                                className="w-full rounded-lg border-slate-300 text-xs py-1.5 focus:border-red-500 focus:ring-red-500"
                                                                maxLength={200}
                                                                required
                                                            />
                                                        </div>

                                                        <div className="sm:col-span-3">
                                                            <label className="sr-only">Type</label>
                                                            <select
                                                                value={item.document_kind}
                                                                onChange={(e) => updateStagedFile(item.id, 'document_kind', e.target.value)}
                                                                className="w-full rounded-lg border-slate-300 text-xs py-1.5 focus:border-red-500 focus:ring-red-500"
                                                                required
                                                            >
                                                                {DOCUMENT_KIND_OPTIONS.map((opt) => (
                                                                    <option key={opt.value} value={opt.value}>
                                                                        {opt.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </div>

                                                        <div className="sm:col-span-1 text-right">
                                                            <button
                                                                type="button"
                                                                onClick={() => removeStagedFile(item.id)}
                                                                className="rounded p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                                                                title="Remove file"
                                                            >
                                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex items-center gap-3 pt-2 border-t border-red-200/60">
                                        <button
                                            type="submit"
                                            disabled={isSubmitting || !uploadEnabled || relatedRecordOptions.length === 0 || stagedFiles.length === 0}
                                            className="rounded-lg bg-red-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 transition-colors inline-flex items-center gap-2"
                                        >
                                            {isSubmitting ? (
                                                <>
                                                    <svg className="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                    </svg>
                                                    Processing & Archiving...
                                                </>
                                            ) : (
                                                <>Upload {stagedFiles.length > 0 ? `${stagedFiles.length} ` : ''}Document{stagedFiles.length !== 1 ? 's' : ''}</>
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowUpload(false);
                                                setStagedFiles([]);
                                            }}
                                            className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
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
                                                    <p className="font-mono text-xs text-slate-800">{document.file_name}</p>
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
                                                            className="rounded border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 inline-flex items-center gap-1"
                                                            title="Preview document in browser"
                                                        >
                                                            View
                                                        </a>
                                                        <a
                                                            href={`${route('documents.show', document.document_id)}?download=1`}
                                                            className="rounded border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-1"
                                                            title="Download file directly"
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
                            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-4 py-2.5">
                                <span className="text-xs font-medium text-slate-500">
                                    Showing {documents.data.length} records
                                </span>
                                <PaginationNav links={documents.links} />
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function fileTypeLabel(fileName: string): string {
    const extension = fileName.split('.').pop()?.toUpperCase();
    if (!extension) {
        return 'Unknown file type';
    }

    return `${extension} file`;
}





