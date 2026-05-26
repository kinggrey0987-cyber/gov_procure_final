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

type Props = {
    eligibleSources: {
        approved_ppmps: EligibleSource[];
        completed_purchase_orders: EligibleSource[];
        recent_purchase_requests: EligibleSource[];
        distribution_summaries: EligibleSource[];
    };
};

type PublicationAction = 'DRAFT' | 'PUBLISHED';

export default function DisclosureCreate({ eligibleSources }: Props) {
    const form = useForm({
        title: '',
        type: '',
        reference_type: '',
        reference_id: '',
        description: '',
        file_path: '',
        main_file: null as File | null,
        supporting_files: [] as File[],
        status: 'DRAFT',
        remarks: '',
    });

    const [publicationAction, setPublicationAction] = useState<PublicationAction>('DRAFT');
    const [selectedSourceKey, setSelectedSourceKey] = useState<string>('');
    const [sourceSearch, setSourceSearch] = useState<string>('');

    const sourceOptions = [
        ...eligibleSources.approved_ppmps,
        ...eligibleSources.completed_purchase_orders,
        ...eligibleSources.recent_purchase_requests,
        ...eligibleSources.distribution_summaries,
    ];

    const selectedSource = useMemo(
        () => sourceOptions.find((item) => `${item.reference_type}:${item.reference_id}` === selectedSourceKey) ?? null,
        [selectedSourceKey, sourceOptions],
    );

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
    };

    const onMainFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('main_file', file);
    };

    const onSupportingFilesChange = (event: ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = Array.from(event.target.files ?? []);
        const merged = [...form.data.supporting_files, ...selectedFiles];
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

        form.transform((data) => ({
            ...data,
            status,
            remarks,
        }));

        form.post(route('executive.disclosures.store'), {
            forceFormData: true,
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
                        {sourceOptions.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-red-200 bg-red-50 px-4 py-4 text-sm text-slate-600">
                                No eligible source records are currently available.
                            </div>
                        ) : null}
                    </StepCard>

                    <StepCard step="2" title="Disclosure Details" subtitle="Define the public-facing content">
                        <div className="grid gap-3 md:grid-cols-2">
                            <Field label="Title" required>
                                <input
                                    className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    placeholder="Disclosure title"
                                    value={form.data.title}
                                    onChange={(event) => form.setData('title', event.target.value)}
                                />
                            </Field>
                            <Field label="Disclosure Type" required>
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

                    <StepCard step="3" title="Files & Attachments" subtitle="Upload the report and supporting files">
                        <div className="grid gap-4 lg:grid-cols-2">
                            <div className="rounded-xl border border-red-200 bg-red-50/50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Main Disclosure File</p>
                                <p className="mt-1 text-sm text-slate-600">This file becomes the primary downloadable disclosure report.</p>
                                <input type="file" className="mt-3 block w-full text-sm" onChange={onMainFileChange} />
                                <p className="mt-2 text-sm font-semibold text-slate-800 break-words">
                                    {form.data.main_file ? form.data.main_file.name : 'No main file selected'}
                                </p>
                            </div>

                            <div className="rounded-xl border border-red-200 bg-red-50/50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Supporting Attachments</p>
                                <p className="mt-1 text-sm text-slate-600">Upload additional public supporting files (multiple allowed).</p>
                                <input type="file" multiple className="mt-3 block w-full text-sm" onChange={onSupportingFilesChange} />
                                <ul className="mt-3 space-y-2">
                                    {form.data.supporting_files.length === 0 ? (
                                        <li className="text-sm text-slate-600">No supporting attachments selected.</li>
                                    ) : (
                                        form.data.supporting_files.map((file, index) => (
                                            <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                <span className="min-w-0 break-words text-slate-800">{file.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeSupportingFile(index)}
                                                    className="shrink-0 rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
                                                >
                                                    Remove
                                                </button>
                                            </li>
                                        ))
                                    )}
                                </ul>
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
                                    title="Main File"
                                    items={form.data.main_file ? [form.data.main_file.name] : ['No main file selected']}
                                />
                                <PreviewList
                                    title="Supporting Attachments"
                                    items={form.data.supporting_files.length > 0 ? form.data.supporting_files.map((file) => file.name) : ['No attachments selected']}
                                />
                            </div>
                        </div>
                    </StepCard>

                    <div className="flex flex-wrap items-center gap-2">
                        <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800" disabled={form.processing}>
                            {publicationAction === 'PUBLISHED' ? 'Publish Disclosure' : 'Save Draft'}
                        </button>
                        <Link href={route('executive.transparency-monitoring')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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

function Field({ label, children, required }: { label: string; children: ReactNode; required?: boolean }) {
    return (
        <label className="block space-y-1">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                {label}
                {required ? ' *' : ''}
            </span>
            {children}
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




