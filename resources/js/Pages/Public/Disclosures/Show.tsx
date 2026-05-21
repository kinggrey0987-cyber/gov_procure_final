import { Head, Link } from '@inertiajs/react';

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
        document_kind?: string | null;
    }>;
    sources: Array<{
        id: number;
        source_role: string;
        source_label: string;
        source_type: string;
        source_id: number;
        snapshot?: Record<string, SnapshotValue>;
    }>;
    backPage?: number | null;
};

type SnapshotPrimitive = string | number | boolean | null;
type SnapshotItem = Record<string, SnapshotPrimitive>;
type SnapshotValue = SnapshotPrimitive | SnapshotItem[];

export default function PublicDisclosureShow({ disclosure, attachments, sources, backPage }: Props) {
    const hasFile = Boolean(disclosure.file_path);
    const fileName = disclosure.file_path ? disclosure.file_path.split('/').pop() ?? disclosure.file_path : null;
    const officialPackage = attachments.find((attachment) => attachment.document_kind === 'OFFICIAL_DISCLOSURE_PACKAGE') ?? null;
    const supportingAttachments = attachments.filter((attachment) => attachment.document_id !== officialPackage?.document_id);

    return (
        <>
            <Head title={disclosure.title} />
            <div className="min-h-screen bg-gradient-to-b from-red-50 via-white to-red-50 py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-red-200 bg-white px-6 py-6 shadow-[0_10px_24px_rgba(15,23,42,0.08)] sm:px-8">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Link
                                href={route('public.disclosures.index', {
                                    page: backPage || undefined,
                                })}
                                className="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                            >
                                Back to Public Disclosures
                            </Link>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge tone="red" label="Published" />
                                <Badge tone="soft" label={formatTypeLabel(disclosure.type)} />
                            </div>
                        </div>

                        <h1 className="mt-5 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                            {disclosure.title}
                        </h1>
                        <p className="mt-3 max-w-2xl text-sm text-slate-600">
                            Official public disclosure package from GovSupply Connect transparency portal.
                        </p>
                        <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-red-700">
                            Published {formatPublicDate(disclosure.published_at)}
                        </p>
                    </section>

                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Summary</h2>
                        <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-red-700">Public Disclosure Context</p>
                        <div className="mt-4 rounded-xl border border-red-100 bg-red-50/50 px-4 py-4">
                            <p className="whitespace-pre-line text-sm leading-7 text-slate-700">
                                {disclosure.description?.trim() || 'This disclosure has been published for public transparency. A full narrative summary has not yet been provided in this record.'}
                            </p>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">Official Published Copy</h2>
                                <p className="mt-1 text-sm text-slate-600">Download the frozen public-safe package generated from the selected source records.</p>
                            </div>
                            {officialPackage ? (
                                <a
                                    href={route('public.disclosures.documents.download', {
                                        public_disclosure: disclosure.disclosure_id,
                                        document: officialPackage.document_id,
                                    })}
                                    className="inline-flex rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                                >
                                    Download Official Package
                                </a>
                            ) : hasFile ? (
                                <a
                                    href={route('public.disclosures.download', disclosure.disclosure_id)}
                                    className="inline-flex rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                                >
                                    Download Disclosure File
                                </a>
                            ) : null}
                        </div>

                        <div className="mt-4 rounded-xl border border-red-100 bg-red-50/50 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Official Package File</p>
                            <p className="mt-1 break-words text-sm font-semibold text-slate-900">
                                {officialPackage?.file_name ?? fileName ?? 'Official package pending generation'}
                            </p>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Procurement Traceability</h2>
                        <p className="mt-1 text-sm text-slate-600">Source records included in this public disclosure package.</p>

                        {sources.length === 0 ? (
                            <div className="mt-4 rounded-xl border border-dashed border-red-200 bg-red-50/50 px-4 py-5 text-sm text-slate-600">
                                No source traceability records are available for this package.
                            </div>
                        ) : (
                            <ol className="mt-4 space-y-3">
                                {sources.map((source, index) => (
                                    <li key={source.id} className="rounded-xl border border-red-100 bg-red-50/40 px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-700 text-xs font-bold text-white">{index + 1}</span>
                                            <span className="rounded-full border border-red-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-red-700">{formatRoleLabel(source.source_role)}</span>
                                            <p className="text-sm font-semibold text-slate-900">{source.source_label}</p>
                                        </div>
                                        {source.snapshot && Object.keys(source.snapshot).length > 0 ? (
                                            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                {Object.entries(source.snapshot).map(([key, value]) => (
                                                    <DetailCell key={`${source.id}-${key}`} label={formatTypeLabel(key)} value={formatSnapshotValue(key, value)} />
                                                ))}
                                            </div>
                                        ) : null}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>

                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Report File</h2>

                        {hasFile ? (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 px-4 py-4">
                                <div className="min-w-0">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Official attached report</p>
                                    <p className="mt-1 break-words text-sm font-semibold text-slate-900">{fileName}</p>
                                    <p className="mt-1 text-xs text-slate-600">Use this file for official public reference.</p>
                                </div>
                                <a
                                    href={route('public.disclosures.download', disclosure.disclosure_id)}
                                    className="inline-flex items-center rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                                >
                                    Download Disclosure File
                                </a>
                            </div>
                        ) : (
                            <div className="mt-4 rounded-xl border border-dashed border-red-200 bg-red-50/50 px-4 py-5 text-sm text-slate-600">
                                No downloadable report is attached to this disclosure.
                            </div>
                        )}
                    </section>

                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Supporting Attachments</h2>

                        {supportingAttachments.length === 0 ? (
                            <div className="mt-4 rounded-xl border border-dashed border-red-200 bg-red-50/50 px-4 py-5 text-sm text-slate-600">
                                No public supporting attachments are available for this disclosure yet.
                            </div>
                        ) : (
                            <ul className="mt-4 space-y-3">
                                {supportingAttachments.map((attachment) => (
                                    <li
                                        key={attachment.document_id}
                                        className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-red-100 bg-red-50/40 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="break-words text-sm font-semibold text-slate-900">{attachment.title}</p>
                                            <p className="break-words text-xs text-slate-600">{attachment.file_name}</p>
                                        </div>
                                        <a
                                            href={route('public.disclosures.documents.download', {
                                                public_disclosure: disclosure.disclosure_id,
                                                document: attachment.document_id,
                                            })}
                                            className="inline-flex items-center rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                        >
                                            Download
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Details</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <DetailCell label="Type" value={formatTypeLabel(disclosure.type)} />
                            <DetailCell label="Published Date" value={formatPublicDate(disclosure.published_at)} />
                            <DetailCell label="Source Records" value={`${sources.length} linked`} />
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function Badge({ label, tone }: { label: string; tone: 'red' | 'soft' }) {
    const toneClass: Record<typeof tone, string> = {
        red: 'border-red-300 bg-red-100 text-red-800',
        soft: 'border-red-200 bg-white text-red-700',
    };

    return (
        <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${toneClass[tone]}`}>
            {label}
        </span>
    );
}

function DetailCell({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600">{label}</p>
            <p className="mt-1 whitespace-pre-line text-sm font-semibold text-slate-900">{value}</p>
        </div>
    );
}

function formatPublicDate(value?: string | null): string {
    if (!value) {
        return 'Not available';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatTypeLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatSnapshotValue(key: string, value: SnapshotValue): string {
    if (Array.isArray(value)) {
        return formatSnapshotItems(value);
    }

    if (value === null || value === undefined || value === '') {
        return 'N/A';
    }

    if (key === 'supplier' && String(value).trim().toLowerCase() === 'unknown supplier') {
        return 'N/A';
    }

    if (key === 'procurement_method' || key.endsWith('_status') || key === 'status') {
        return formatTypeLabel(String(value));
    }

    return String(value);
}

function formatSnapshotItems(items: SnapshotItem[]): string {
    if (items.length === 0) {
        return 'No items listed';
    }

    return items
        .map((item, index) => {
            const name = String(item.item ?? `Item ${index + 1}`);
            const details = [
                ['Quantity', item.quantity],
                ['Cost', item.cost],
            ]
                .filter(([, value]) => value !== null && value !== undefined && value !== '')
                .map(([label, value]) => `${label}: ${value}`)
                .join(', ');

            return details ? `${index + 1}. ${name} (${details})` : `${index + 1}. ${name}`;
        })
        .join('\n');
}

function formatRoleLabel(value: string): string {
    return value
        .replace(/^RELATED_/, '')
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}





