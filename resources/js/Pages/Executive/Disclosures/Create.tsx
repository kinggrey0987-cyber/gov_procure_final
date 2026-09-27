import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChangeEvent, FormEvent, ReactNode, useMemo, useState } from 'react';

type EligibleSource = {
    label: string;
    source_group?: string;
    source_code?: string;
    office?: string;
    release_date?: string | null;
    reference_type: string;
    reference_id: number;
    suggested_type: string;
    suggested_title: string;
};

type ProcurementDocument = {
    document_id: number;
    title: string;
    file_name: string;
    document_kind: string;
    related_module: string;
    related_reference_id: number;
    uploaded_at: string | null;
    uploader_name: string | null;
    view_url: string;
};

type Props = {
    eligibleSources: {
        approved_ppmps: EligibleSource[];
        completed_purchase_orders: EligibleSource[];
        recent_purchase_requests: EligibleSource[];
        distribution_summaries: EligibleSource[];
    };
    procurementDocumentsBySource?: Record<string, ProcurementDocument[]>;
};

type PublicationAction = 'DRAFT' | 'PUBLISHED';

const DOCUMENT_KIND_LABELS: Record<string, string> = {
    noa: 'Notice of Award (NOA)',
    canvass_sheet: 'Abstract of Canvass / Canvass Sheet',
    supplier_quotation: 'Winning / Bidder Quotation',
    signed_po: 'Signed PO with Conforme',
    procurement_record: 'BAC Resolution / Procurement Record',
    other_supporting_doc: 'Other Supporting Document',
    OFFICIAL_DISCLOSURE_PACKAGE: 'Official Disclosure Package',
    SOURCE_PROCUREMENT_DOCUMENT: 'Procurement Attachment Copy',
};

function getKindBadge(kind?: string) {
    const rawKind = (kind || 'other_supporting_doc').toLowerCase();
    const label = DOCUMENT_KIND_LABELS[kind || ''] || DOCUMENT_KIND_LABELS[rawKind] || (kind ? kind.replaceAll('_', ' ') : 'Supporting Document');

    if (rawKind.includes('noa') || rawKind.includes('award')) {
        return { label, className: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    if (rawKind.includes('canvass')) {
        return { label, className: 'bg-blue-50 text-blue-700 border-blue-200' };
    }
    if (rawKind.includes('quotation') || rawKind.includes('bid')) {
        return { label, className: 'bg-purple-50 text-purple-700 border-purple-200' };
    }
    if (rawKind.includes('po') || rawKind.includes('conforme')) {
        return { label, className: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    return { label, className: 'bg-slate-100 text-slate-700 border-slate-200' };
}

export default function DisclosureCreate({ eligibleSources, procurementDocumentsBySource = {} }: Props) {
    const form = useForm({
        title: '',
        type: '',
        reference_type: '',
        reference_id: '',
        description: '',
        file_path: '',
        main_file: null as File | null,
        procurement_document_ids: [] as number[],
        supporting_files: [] as File[],
        status: 'DRAFT',
        remarks: '',
    });

    const [publicationAction, setPublicationAction] = useState<PublicationAction>('DRAFT');
    const [selectedSourceKey, setSelectedSourceKey] = useState<string>('');
    const [sourceSearch, setSourceSearch] = useState<string>('');
    const [showCustomMainUpload, setShowCustomMainUpload] = useState<boolean>(false);

    const sourceOptions = useMemo(
        () => [
            ...eligibleSources.approved_ppmps,
            ...eligibleSources.completed_purchase_orders,
            ...eligibleSources.recent_purchase_requests,
            ...eligibleSources.distribution_summaries,
        ],
        [eligibleSources],
    );

    const selectedSource = useMemo(
        () => sourceOptions.find((item) => `${item.reference_type}:${item.reference_id}` === selectedSourceKey) ?? null,
        [selectedSourceKey, sourceOptions],
    );

    // Strictly resolve documents belonging only to the selected source
    const availableProcurementDocs = useMemo(() => {
        if (!selectedSourceKey || !procurementDocumentsBySource) {
            return [];
        }
        return procurementDocumentsBySource[selectedSourceKey] ?? [];
    }, [selectedSourceKey, procurementDocumentsBySource]);

    const filteredSourceOptions = useMemo(() => {
        const query = sourceSearch.trim().toLowerCase();

        if (!query) {
            return sourceOptions;
        }

        return sourceOptions.filter((source) => {
            const searchIndex = [
                source.source_code,
                source.office,
                source.source_group,
                source.release_date,
                source.label,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return searchIndex.includes(query);
        });
    }, [sourceOptions, sourceSearch]);

    const onSelectSource = (source: EligibleSource) => {
        const key = `${source.reference_type}:${source.reference_id}`;
        setSelectedSourceKey(key);

        form.setData('reference_type', source.reference_type);
        form.setData('reference_id', String(source.reference_id));
        form.setData('title', source.suggested_title);
        form.setData('type', source.suggested_type);

        // Pre-select all procurement registry documents that belong strictly to this referenced record
        const sourceDocs = procurementDocumentsBySource[key] ?? [];
        form.setData('procurement_document_ids', sourceDocs.map((doc) => doc.document_id));
    };

    const toggleProcurementDoc = (id: number) => {
        if (form.data.procurement_document_ids.includes(id)) {
            form.setData(
                'procurement_document_ids',
                form.data.procurement_document_ids.filter((currentId) => currentId !== id),
            );
        } else {
            form.setData('procurement_document_ids', [...form.data.procurement_document_ids, id]);
        }
    };

    const selectAllProcurementDocs = () => {
        form.setData(
            'procurement_document_ids',
            availableProcurementDocs.map((doc) => doc.document_id),
        );
    };

    const clearAllProcurementDocs = () => {
        form.setData('procurement_document_ids', []);
    };

    const onMainFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        if (file && file.size > 10 * 1024 * 1024) {
            form.setError('main_file', `Selected file exceeds the 10 MB limit (${(file.size / (1024 * 1024)).toFixed(1)} MB).`);
            event.target.value = '';
            form.setData('main_file', null);
            return;
        }
        form.clearErrors('main_file');
        form.setData('main_file', file);
    };

    const onSupportingFilesChange = (event: ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = Array.from(event.target.files ?? []);
        const oversized = selectedFiles.filter((f) => f.size > 10 * 1024 * 1024);
        const valid = selectedFiles.filter((f) => f.size <= 10 * 1024 * 1024);

        if (oversized.length > 0) {
            const names = oversized.map((f) => `"${f.name}" (${(f.size / (1024 * 1024)).toFixed(1)} MB)`).join(', ');
            form.setError('supporting_files', `File(s) exceed 10 MB limit and were skipped: ${names}`);
        } else {
            form.clearErrors('supporting_files');
        }

        const merged = [...form.data.supporting_files, ...valid];
        form.setData('supporting_files', merged);
        event.target.value = '';
    };

    const removeSupportingFile = (index: number) => {
        form.setData(
            'supporting_files',
            form.data.supporting_files.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const priorStatus = form.data.status;
        const priorRemarks = form.data.remarks;

        const status = publicationAction === 'PUBLISHED' ? 'PUBLISHED' : 'DRAFT';
        const remarks = form.data.remarks;

        form.transform((data) => {
            const transformed: Record<string, any> = {
                title: data.title,
                type: data.type,
                status,
                remarks,
            };

            if (data.reference_type) {
                transformed.reference_type = data.reference_type;
            }
            if (data.reference_id) {
                transformed.reference_id = Number(data.reference_id);
            }
            if (data.description) {
                transformed.description = data.description;
            }
            if (data.procurement_document_ids && data.procurement_document_ids.length > 0) {
                transformed.procurement_document_ids = data.procurement_document_ids;
            }
            if (data.main_file instanceof File) {
                transformed.main_file = data.main_file;
            }
            if (data.supporting_files && data.supporting_files.length > 0) {
                transformed.supporting_files = data.supporting_files;
            }

            return transformed;
        });

        form.post(route('executive.disclosures.store'), {
            forceFormData: true,
            onError: (errors) => {
                console.error('Disclosure creation errors:', errors);
            },
            onFinish: () => {
                form.transform((data) => data);
                form.setData('status', priorStatus);
                form.setData('remarks', priorRemarks);
            },
        });
    };

    return (
        <AuthenticatedLayout header="Create Disclosure Draft">
            <Head title="Create Disclosure Draft" />
            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white px-6 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Executive Publication Workflow</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">Create Public Disclosure</h3>
                    <p className="mt-1 text-sm text-slate-600">Prepare, review, and publish public disclosures from approved source records.</p>
                </section>

                <form className="space-y-5" onSubmit={onSubmit}>
                    {Object.keys(form.errors).length > 0 && (
                        <div className="rounded-2xl border border-red-300 bg-red-50 p-5 shadow-sm">
                            <div className="flex items-center gap-2.5 text-red-800 font-semibold text-sm">
                                <svg className="h-5 w-5 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Please resolve the following before proceeding:</span>
                            </div>
                            <ul className="mt-2.5 list-disc list-inside text-xs text-red-700 space-y-1">
                                {Object.entries(form.errors).map(([key, msg]) => (
                                    <li key={key}>
                                        <strong className="capitalize">{key.replaceAll('_', ' ')}:</strong> {msg}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <StepCard step="1" title="Select Source" subtitle="Choose an approved record to publish from">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                            <section className="rounded-xl border border-red-200 bg-red-50/60 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Search Eligible Sources</p>
                                <p className="mt-1 text-sm text-slate-600">Search by code, office, source group, or release date.</p>

                                <input
                                    type="text"
                                    value={sourceSearch}
                                    onChange={(event) => setSourceSearch(event.target.value)}
                                    placeholder="Search source code, office, or type"
                                    className="mt-3 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                />

                                <div className="mt-3 max-h-64 overflow-auto rounded-lg border border-slate-200 bg-white">
                                    {filteredSourceOptions.length === 0 ? (
                                        <p className="px-3 py-3 text-sm text-slate-600">No matching eligible records found.</p>
                                    ) : (
                                        <ul className="divide-y divide-slate-100">
                                            {filteredSourceOptions.map((source) => {
                                                const key = `${source.reference_type}:${source.reference_id}`;
                                                const isActive = key === selectedSourceKey;

                                                return (
                                                    <li key={key}>
                                                        <button
                                                            type="button"
                                                            onClick={() => onSelectSource(source)}
                                                            className={`w-full px-3 py-2 text-left ${isActive ? 'bg-red-100' : 'hover:bg-red-50'}`}
                                                        >
                                                            <p className="text-sm font-semibold text-slate-900">{source.source_code ?? source.label}</p>
                                                            <p className="text-xs text-slate-600">{source.office ?? 'N/A'} • {source.source_group ?? 'Eligible Source'} • {formatDate(source.release_date)}</p>
                                                        </button>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                </div>
                            </section>

                            <section className="rounded-xl border border-slate-200 bg-white p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">Selected Source Preview</p>
                                {selectedSource ? (
                                    <div className="mt-3 rounded-lg border border-red-200 bg-red-50/50 p-3">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-red-700">{selectedSource.source_group ?? 'Eligible Source'}</p>
                                        <p className="mt-1 text-base font-semibold text-slate-900">{selectedSource.source_code ?? selectedSource.label}</p>
                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            <PreviewField label="Office" value={selectedSource.office ?? 'N/A'} />
                                            <PreviewField label="Release Date" value={formatDate(selectedSource.release_date)} />
                                            <PreviewField label="Suggested Title" value={selectedSource.suggested_title} />
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mt-3 text-sm text-slate-600">Select an eligible source to preview publication context.</p>
                                )}
                            </section>
                        </div>
                        {form.errors.reference_id && (
                            <p className="mt-3 rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs font-semibold text-red-700">
                                Source error: {form.errors.reference_id}
                            </p>
                        )}
                        {sourceOptions.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-red-200 bg-red-50 px-4 py-4 text-sm text-slate-600">
                                No eligible source records are currently available.
                            </div>
                        ) : null}
                    </StepCard>

                    <StepCard step="2" title="Disclosure Details" subtitle="Define the public-facing content">
                        <div className="grid gap-3 md:grid-cols-2">
                            <Field label="Title" required error={form.errors.title}>
                                <input
                                    className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    placeholder="Disclosure title"
                                    value={form.data.title}
                                    onChange={(event) => form.setData('title', event.target.value)}
                                />
                            </Field>
                            <Field label="Disclosure Type" required error={form.errors.type}>
                                <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p className="text-sm font-semibold text-slate-800">
                                        {form.data.type ? formatTypeLabel(form.data.type) : 'Select a source to assign a disclosure type'}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Disclosure type is automatically assigned based on the selected source.
                                    </p>
                                </div>
                            </Field>
                        </div>

                        <Field label="Description">
                            <textarea
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                rows={6}
                                placeholder="Public-facing summary of this disclosure"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                            />
                        </Field>
                    </StepCard>

                    <StepCard
                        step="3"
                        title="Files & Attachments"
                        subtitle="Configure official publication report and supporting public attachments"
                    >
                        <div className="space-y-6">
                            {/* Card 1: Official Main Disclosure Report */}
                            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-bold uppercase tracking-wider text-slate-900">
                                                Official Disclosure Report
                                            </h4>
                                            <p className="text-xs text-slate-500">
                                                Primary downloadable transparency document for public release
                                            </p>
                                        </div>
                                    </div>
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                        Automatic PDF Generation Active
                                    </span>
                                </div>

                                <div className="mt-4 rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                                    <div className="flex items-start gap-3">
                                        <div className="mt-0.5 text-emerald-600">
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div className="text-xs leading-relaxed text-slate-700">
                                            <p className="font-semibold text-slate-900">
                                                The system automatically compiles and certifies this report upon saving or publishing.
                                            </p>
                                            <p className="mt-1 text-slate-600">
                                                In accordance with RA 9184 and DILG Full Disclosure Policy guidelines, the generated document (<code className="font-mono text-emerald-800 bg-emerald-100/70 px-1 py-0.5 rounded">disclosure-package.pdf</code>) bundles:
                                            </p>
                                            <ul className="mt-2 grid gap-1.5 sm:grid-cols-2 text-slate-600">
                                                <li className="flex items-center gap-1.5">
                                                    <svg className="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Official LGU Letterhead & Tracking Barcode</span>
                                                </li>
                                                <li className="flex items-center gap-1.5">
                                                    <svg className="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Itemized Procurement Schedule & Costs</span>
                                                </li>
                                                <li className="flex items-center gap-1.5">
                                                    <svg className="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Winning Supplier Awards & Conforme</span>
                                                </li>
                                                <li className="flex items-center gap-1.5">
                                                    <svg className="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Executive Digital Seal & Authenticity Block</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-4">
                                    {!showCustomMainUpload && !form.data.main_file ? (
                                        <button
                                            type="button"
                                            onClick={() => setShowCustomMainUpload(true)}
                                            className="text-xs font-semibold text-red-700 hover:text-red-800 underline underline-offset-2 flex items-center gap-1"
                                        >
                                            <span>+ Attach external pre-signed ordinance or scanned municipal order instead (Optional)</span>
                                        </button>
                                    ) : (
                                        <div className="rounded-xl border border-amber-200 bg-amber-50/50 p-4 space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs font-bold uppercase tracking-wide text-amber-800">
                                                    Custom Replacement Document
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        form.setData('main_file', null);
                                                        setShowCustomMainUpload(false);
                                                    }}
                                                    className="text-xs font-semibold text-slate-500 hover:text-red-700"
                                                >
                                                    Cancel & Revert to Auto-Generated PDF
                                                </button>
                                            </div>
                                            <p className="text-xs text-slate-600">
                                                Choose an external PDF file to use in place of the system-generated disclosure package:
                                            </p>
                                            <input type="file" accept=".pdf,.doc,.docx" className="block w-full text-xs text-slate-700" onChange={onMainFileChange} />
                                            {form.data.main_file && (
                                                <p className="text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
                                                    <span>Selected replacement: {form.data.main_file.name}</span>
                                                </p>
                                            )}
                                            {form.errors.main_file && (
                                                <p className="text-xs font-semibold text-red-600">
                                                    {form.errors.main_file}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Card 2: Procurement Unit Document Registry */}
                            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-red-700">
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-bold uppercase tracking-wider text-slate-900">
                                                Procurement Unit Document Registry
                                            </h4>
                                            <p className="text-xs text-slate-500">
                                                Verified attachments uploaded by the Procurement Unit strictly for this referenced record
                                            </p>
                                        </div>
                                    </div>

                                    {availableProcurementDocs.length > 0 && (
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={selectAllProcurementDocs}
                                                className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                            >
                                                Select All
                                            </button>
                                            <button
                                                type="button"
                                                onClick={clearAllProcurementDocs}
                                                className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                            >
                                                Clear All
                                            </button>
                                            <span className="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800">
                                                {form.data.procurement_document_ids.length} of {availableProcurementDocs.length} selected
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <div className="mt-4">
                                    {(form.errors.procurement_document_ids || (form.errors as any).document_ids) && (
                                        <div className="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-700">
                                            {form.errors.procurement_document_ids || (form.errors as any).document_ids}
                                        </div>
                                    )}
                                    {!selectedSource ? (
                                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-6 text-center">
                                            <svg className="mx-auto h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                                            </svg>
                                            <p className="mt-2 text-sm font-medium text-slate-700">Please select an eligible source in Step 1 first</p>
                                            <p className="mt-0.5 text-xs text-slate-500">
                                                Once you pick a Purchase Order or Request, its registered procurement files (NOA, Canvass Sheet, Quotations) will be displayed here.
                                            </p>
                                        </div>
                                    ) : availableProcurementDocs.length === 0 ? (
                                        <div className="rounded-xl border border-dashed border-amber-200 bg-amber-50/50 p-5 text-center">
                                            <p className="text-sm font-medium text-amber-800">
                                                No procurement files registered for {selectedSource.source_code ?? selectedSource.label}
                                            </p>
                                            <p className="mt-1 text-xs text-amber-700">
                                                The Procurement Unit has not archived supporting uploads (NOA, Quotations, Canvass) for this record yet. You can still upload additional municipal files below.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-2.5">
                                            {availableProcurementDocs.map((doc) => {
                                                const isChecked = form.data.procurement_document_ids.includes(doc.document_id);
                                                const badge = getKindBadge(doc.document_kind);

                                                return (
                                                    <div
                                                        key={doc.document_id}
                                                        className={`flex items-start gap-3 rounded-xl border p-3.5 transition ${
                                                            isChecked
                                                                ? 'border-red-300 bg-red-50/40 shadow-[0_2px_8px_rgba(185,28,28,0.06)]'
                                                                : 'border-slate-200 bg-white hover:bg-slate-50'
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            id={`proc-doc-${doc.document_id}`}
                                                            checked={isChecked}
                                                            onChange={() => toggleProcurementDoc(doc.document_id)}
                                                            className="mt-1 h-4 w-4 rounded border-slate-300 text-red-700 focus:ring-red-600"
                                                        />
                                                        <label
                                                            htmlFor={`proc-doc-${doc.document_id}`}
                                                            className="min-w-0 flex-1 cursor-pointer"
                                                        >
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-semibold text-slate-900 text-sm">{doc.title}</span>
                                                                <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold ${badge.className}`}>
                                                                    {badge.label}
                                                                </span>
                                                            </div>
                                                            <p className="mt-1 text-xs text-slate-500 font-mono break-all">
                                                                {doc.file_name} • {doc.related_module.replaceAll('_', ' ')} #{doc.related_reference_id}
                                                            </p>
                                                            <p className="mt-1 text-[11px] text-slate-400">
                                                                Uploaded {doc.uploaded_at ? new Date(doc.uploaded_at).toLocaleString() : 'N/A'} by {doc.uploader_name || 'Procurement Unit'}
                                                            </p>
                                                        </label>

                                                        <a
                                                            href={doc.view_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="shrink-0 inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-red-300 hover:text-red-700 hover:bg-red-50 transition"
                                                        >
                                                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            <span>View</span>
                                                        </a>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Card 3: Additional Supplementary Municipal Attachments */}
                            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-bold uppercase tracking-wider text-slate-900">
                                            Additional Municipal Attachments (Optional)
                                        </h4>
                                        <p className="text-xs text-slate-500">
                                            Upload extra municipal files not present in the Procurement Unit registry (e.g. resolutions, executive memos)
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-4">
                                    <input type="file" multiple className="block w-full text-xs text-slate-700" onChange={onSupportingFilesChange} />
                                    <ul className="mt-3 space-y-2">
                                        {form.data.supporting_files.length === 0 ? (
                                            <li className="text-xs text-slate-500 italic">No additional municipal attachments selected.</li>
                                        ) : (
                                            form.data.supporting_files.map((file, index) => (
                                                <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                                                    <span className="min-w-0 break-words font-medium text-slate-800">{file.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeSupportingFile(index)}
                                                        className="shrink-0 rounded border border-red-200 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50"
                                                    >
                                                        Remove
                                                    </button>
                                                </li>
                                            ))
                                        )}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </StepCard>

                    <StepCard step="4" title="Publication" subtitle="Choose whether to keep the disclosure as a draft or publish it now">
                        <div className="grid gap-3 md:grid-cols-2">
                            <ActionChoice
                                title="Save as Draft"
                                description="Keep this disclosure in draft state."
                                active={publicationAction === 'DRAFT'}
                                onClick={() => setPublicationAction('DRAFT')}
                            />
                            <ActionChoice
                                title="Publish Immediately"
                                description="Publish now to public disclosure pages."
                                active={publicationAction === 'PUBLISHED'}
                                onClick={() => setPublicationAction('PUBLISHED')}
                            />
                        </div>

                        <Field label="Remarks">
                            <textarea
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                rows={3}
                                placeholder="Optional publication notes"
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                            />
                        </Field>
                    </StepCard>

                    <StepCard step="5" title="Public Preview" subtitle="Preview how this disclosure will appear publicly">
                        <div className="rounded-xl border border-red-200 bg-red-50/50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Preview Title</p>
                            <p className="mt-1 text-xl font-semibold text-slate-900">{form.data.title || 'Untitled Disclosure'}</p>

                            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span className="rounded-full border border-red-300 bg-red-100 px-2 py-0.5 font-semibold text-red-800">{form.data.type ? formatTypeLabel(form.data.type) : 'Type Not Selected'}</span>
                                <span className="rounded-full border border-slate-300 bg-white px-2 py-0.5 font-semibold text-slate-700">{publicationLabel(publicationAction)}</span>
                                {selectedSource ? (
                                    <span className="rounded-full border border-slate-300 bg-white px-2 py-0.5 font-semibold text-slate-700">Source {selectedSource.source_code ?? selectedSource.label}</span>
                                ) : null}
                            </div>

                            <p className="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">
                                {form.data.description?.trim() || 'No summary entered yet.'}
                            </p>

                            <div className="mt-4 grid gap-3 md:grid-cols-2">
                                <PreviewList
                                    title="Primary Disclosure Report"
                                    items={[
                                        form.data.main_file
                                            ? `Custom Replacement: ${form.data.main_file.name}`
                                            : '⚡ Auto-Generated Official Disclosure Package (PDF with LGU Letterhead & Digital Seal)'
                                    ]}
                                />
                                <PreviewList
                                    title={`Procurement Unit Registry Attachments (${form.data.procurement_document_ids.length})`}
                                    items={
                                        form.data.procurement_document_ids.length > 0
                                            ? availableProcurementDocs
                                                .filter((doc) => form.data.procurement_document_ids.includes(doc.document_id))
                                                .map((doc) => `${doc.title} (${doc.file_name})`)
                                            : ['No procurement registry attachments selected']
                                    }
                                />
                                {form.data.supporting_files.length > 0 && (
                                    <div className="md:col-span-2">
                                        <PreviewList
                                            title={`Additional Municipal Attachments (${form.data.supporting_files.length})`}
                                            items={form.data.supporting_files.map((file) => file.name)}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    </StepCard>

                    <div className="flex flex-wrap items-center gap-3 pt-2">
                        <button
                            type="submit"
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 transition"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <>
                                    <svg className="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                    </svg>
                                    <span>{publicationAction === 'PUBLISHED' ? 'Publishing Disclosure...' : 'Saving Draft...'}</span>
                                </>
                            ) : (
                                <span>{publicationAction === 'PUBLISHED' ? 'Publish Disclosure' : 'Save Draft'}</span>
                            )}
                        </button>
                        <Link
                            href={route('executive.transparency-monitoring')}
                            className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition"
                        >
                            Back to Transparency & Disclosures
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

function StepCard({ step, title, subtitle, children }: { step: string; title: string; subtitle: string; children: ReactNode }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-700 text-xs font-bold text-white">{step}</span>
                <div>
                    <h4 className="text-lg font-semibold text-slate-900">{title}</h4>
                    <p className="text-sm text-slate-600">{subtitle}</p>
                </div>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

function Field({ label, children, required, error }: { label: string; children: ReactNode; required?: boolean; error?: string }) {
    return (
        <label className="block space-y-1">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                {label}
                {required ? ' *' : ''}
            </span>
            {children}
            {error ? <p className="text-xs font-semibold text-red-600">{error}</p> : null}
        </label>
    );
}

function ActionChoice({ title, description, active, onClick }: { title: string; description: string; active: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-xl border px-4 py-3 text-left transition ${active ? 'border-red-400 bg-red-50 shadow-[0_6px_16px_rgba(185,28,28,0.12)]' : 'border-slate-200 bg-white hover:border-red-300 hover:bg-red-50/50'}`}
        >
            <p className="text-sm font-semibold text-slate-900">{title}</p>
            <p className="mt-1 text-xs text-slate-600">{description}</p>
        </button>
    );
}

function PreviewList({ title, items }: { title: string; items: string[] }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{title}</p>
            <ul className="mt-2 space-y-1">
                {items.map((item, index) => (
                    <li key={`${title}-${item}-${index}`} className="text-sm text-slate-800 break-words">{item}</li>
                ))}
            </ul>
        </div>
    );
}

function PreviewField({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-slate-200 bg-white px-2 py-1.5">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-0.5 break-words text-xs text-slate-800">{value}</p>
        </div>
    );
}

function publicationLabel(action: PublicationAction): string {
    if (action === 'PUBLISHED') {
        return 'Publish Immediately';
    }

    return 'Save as Draft';
}

function formatDate(value?: string | null): string {
    if (!value) {
        return 'N/A';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatTypeLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}
