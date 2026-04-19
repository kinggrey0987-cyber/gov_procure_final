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
    const hasFile = Boolean(disclosure.file_path);
    const fileName = disclosure.file_path ? disclosure.file_path.split('/').pop() ?? disclosure.file_path : null;

    return (
        <>
            <Head title={disclosure.title} />
            <div className="min-h-screen bg-gradient-to-b from-slate-100 via-white to-slate-100 py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center shadow-[0_10px_24px_rgba(15,23,42,0.08)] sm:px-8">
                        <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-center gap-2">
                            <Badge tone="emerald" label="Published" />
                            <Badge tone="slate" label={formatTypeLabel(disclosure.type)} />
                            <Badge tone="sky" label={`Published ${formatPublicDate(disclosure.published_at)}`} />
                        </div>
                        <h1 className="mx-auto mt-4 max-w-3xl text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                            {disclosure.title}
                        </h1>
                        <p className="mx-auto mt-3 max-w-2xl text-sm text-slate-600">
                            Official public disclosure record from GovSupply Connect transparency portal.
                        </p>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Summary</h2>
                        <p className="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">
                            {disclosure.description?.trim() || 'No public description is available for this disclosure.'}
                        </p>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Report File</h2>

                        {hasFile ? (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Attached report</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-900">{fileName}</p>
                                </div>
                                <a
                                    href={route('public.disclosures.download', disclosure.disclosure_id)}
                                    className="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                                >
                                    Download Disclosure File
                                </a>
                            </div>
                        ) : (
                            <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                                No downloadable report is attached to this disclosure.
                            </div>
                        )}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Attachments</h2>

                        {attachments.length === 0 ? (
                            <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                                No public attachments available for this disclosure.
                            </div>
                        ) : (
                            <ul className="mt-4 space-y-3">
                                {attachments.map((attachment) => (
                                    <li
                                        key={attachment.document_id}
                                        className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-slate-900">{attachment.title}</p>
                                            <p className="truncate text-xs text-slate-600">{attachment.file_name}</p>
                                        </div>
                                        <a
                                            href={route('public.disclosures.documents.download', {
                                                public_disclosure: disclosure.disclosure_id,
                                                document: attachment.document_id,
                                            })}
                                            className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            Download
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <h2 className="text-lg font-semibold text-slate-900">Disclosure Details</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <DetailCell label="Type" value={formatTypeLabel(disclosure.type)} />
                            <DetailCell label="Published Date" value={formatPublicDate(disclosure.published_at)} />
                            <DetailCell label="Reference" value={`Record #${disclosure.disclosure_id}`} />
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function Badge({ label, tone }: { label: string; tone: 'emerald' | 'slate' | 'sky' }) {
    const toneClass: Record<typeof tone, string> = {
        emerald: 'border-emerald-300 bg-emerald-100 text-emerald-800',
        slate: 'border-slate-300 bg-slate-100 text-slate-800',
        sky: 'border-sky-300 bg-sky-100 text-sky-800',
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
            <p className="mt-1 text-sm font-semibold text-slate-900">{value}</p>
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
        .replace(/\b\w/g, (char) => char.toUpperCase());
}
