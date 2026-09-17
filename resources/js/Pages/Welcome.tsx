import PublicHero from '@/Components/Public/PublicHero';
import PublicInfoCards from '@/Components/Public/PublicInfoCards';
import PublicNavbar from '@/Components/Public/PublicNavbar';
import PublicStatCards from '@/Components/Public/PublicStatCards';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type WelcomeProps = {
    canLogin: boolean;
    canRegister: boolean;
    laravelVersion: string;
    phpVersion: string;
    summaryStats: Array<{
        label: string;
        value: string;
        accent: 'red' | 'green' | 'blue' | 'amber';
    }>;
    latestDisclosures: Array<{
        disclosure_id: number;
        title: string;
        type: string;
        published_at: string | null;
        attachment_count: number;
    }>;
    monthlyDisclosures: Array<{
        label: string;
        count: number;
    }>;
    typeBreakdown: Array<{
        label: string;
        count: number;
    }>;
    publicReports: Array<{
        document_id: number;
        title: string;
        file_name: string;
        uploaded_at: string | null;
        disclosure_id: number;
        disclosure_title: string;
    }>;
};

const primaryButtonClass =
    'inline-flex h-10 items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2';

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Not available';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Not available';
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDisclosureType(value: string): string {
    return value
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export default function Welcome({ canLogin, summaryStats, latestDisclosures, monthlyDisclosures, typeBreakdown, publicReports }: WelcomeProps) {
    const [activeTab, setActiveTab] = useState<'disclosures' | 'reports'>('disclosures');

    // Create a lookup of downloadable reports by disclosure_id
    const reportsByDisclosure = useMemo(() => {
        const map = new Map<number, typeof publicReports>();
        publicReports.forEach((report) => {
            const list = map.get(report.disclosure_id) || [];
            list.push(report);
            map.set(report.disclosure_id, list);
        });
        return map;
    }, [publicReports]);

    return (
        <>
            <Head title="GovSupply Connect" />

            <div className="min-h-screen bg-slate-100">
                <PublicNavbar canLogin={canLogin} />
                <PublicHero
                    title="Municipal Procurement Transparency Portal"
                    subtitle="Promoting transparency and accountability in municipal procurement and supply distribution."
                />

                <main className="mx-auto max-w-7xl space-y-5 px-4 py-5 sm:px-6 lg:px-8">
                    {/* Unified Transparency & Disclosures Section */}
                    <section id="disclosures" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-5 sm:p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-red-700">
                                        Public Transparency & Procurement
                                    </div>
                                    <h2 className="mt-2 text-2xl font-bold text-red-700 sm:text-3xl">
                                        Published Disclosures & Official Reports
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-600">
                                        Public-facing procurement records, official award summaries, and downloadable verification packages.
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <a href="/public/disclosures" className="rounded-xl border border-red-300 px-3.5 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 transition">
                                        View All Disclosures &rarr;
                                    </a>
                                </div>
                            </div>

                            {/* Segmented Filter Bar to prevent duplicate sections */}
                            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                <div className="inline-flex rounded-xl bg-slate-100 p-1">
                                    <button
                                        type="button"
                                        onClick={() => setActiveTab('disclosures')}
                                        className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition ${
                                            activeTab === 'disclosures'
                                                ? 'bg-white text-red-700 shadow-sm'
                                                : 'text-slate-600 hover:text-slate-900'
                                        }`}
                                    >
                                        Procurement Disclosures ({latestDisclosures.length})
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setActiveTab('reports')}
                                        className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition ${
                                            activeTab === 'reports'
                                                ? 'bg-white text-red-700 shadow-sm'
                                                : 'text-slate-600 hover:text-slate-900'
                                        }`}
                                    >
                                        Official Download Packages ({publicReports.length})
                                    </button>
                                </div>
                                <span id="reports" className="text-xs text-slate-400">
                                    Showing latest published records
                                </span>
                            </div>
                        </div>

                        {activeTab === 'disclosures' ? (
                            <div className="grid gap-4 p-5 sm:p-6 md:grid-cols-2 xl:grid-cols-3">
                                {latestDisclosures.length === 0 ? (
                                    <article className="rounded-2xl border border-slate-200 bg-slate-50 p-6 md:col-span-2 xl:col-span-3 text-center">
                                        <h3 className="text-lg font-bold text-slate-800">No published disclosures yet</h3>
                                        <p className="mt-1 text-sm text-slate-600">
                                            The portal will list disclosure records as soon as they are approved and published.
                                        </p>
                                    </article>
                                ) : (
                                    latestDisclosures.slice(0, 6).map((item) => {
                                        const matchingReports = reportsByDisclosure.get(item.disclosure_id) || [];
                                        return (
                                            <article key={item.disclosure_id} className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-5 transition hover:shadow-md hover:border-red-200">
                                                <div>
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                            Published
                                                        </span>
                                                        <span className="text-xs font-medium text-slate-500">{formatDateTime(item.published_at)}</span>
                                                    </div>
                                                    <h3 className="mt-3 line-clamp-2 text-base font-bold text-slate-900">{item.title}</h3>
                                                    <p className="mt-1 text-xs text-slate-600">Type: <span className="font-semibold text-slate-700">{formatDisclosureType(item.type)}</span></p>
                                                    
                                                    {/* Attached Files Quick Info */}
                                                    <div className="mt-3 rounded-xl border border-slate-200 bg-white p-2.5 text-xs">
                                                        <div className="flex items-center justify-between text-slate-600">
                                                            <span>Attachments</span>
                                                            <span className="font-bold text-red-700">{item.attachment_count} File(s)</span>
                                                        </div>
                                                        {matchingReports.length > 0 && (
                                                            <div className="mt-2 space-y-1 border-t border-slate-100 pt-2">
                                                                {matchingReports.slice(0, 2).map((file) => (
                                                                    <a
                                                                        key={file.document_id}
                                                                        href={route('public.disclosures.documents.download', {
                                                                            public_disclosure: file.disclosure_id,
                                                                            document: file.document_id,
                                                                        })}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="flex items-center gap-1.5 text-xs font-medium text-red-700 hover:underline truncate"
                                                                        title={file.file_name}
                                                                    >
                                                                        <svg className="h-3.5 w-3.5 shrink-0 text-red-600" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                                                                        </svg>
                                                                        <span className="truncate">{file.file_name}</span>
                                                                    </a>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="mt-4 flex items-center gap-2 pt-2">
                                                    <a
                                                        href={`/public/disclosures/${item.disclosure_id}`}
                                                        className={`flex-1 ${primaryButtonClass}`}
                                                    >
                                                        View Disclosure
                                                    </a>
                                                    {matchingReports.length > 0 && (
                                                        <a
                                                            href={route('public.disclosures.documents.download', {
                                                                public_disclosure: matchingReports[0].disclosure_id,
                                                                document: matchingReports[0].document_id,
                                                            })}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex h-10 items-center justify-center rounded-xl border border-red-300 bg-white px-3 text-xs font-semibold text-red-700 hover:bg-red-50 transition"
                                                            title="Download Official File"
                                                        >
                                                            <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                                                            </svg>
                                                        </a>
                                                    )}
                                                </div>
                                            </article>
                                        );
                                    })
                                )}
                            </div>
                        ) : (
                            <div className="grid gap-4 p-5 sm:p-6 md:grid-cols-2 xl:grid-cols-3">
                                {publicReports.length === 0 ? (
                                    <article className="rounded-2xl border border-slate-200 bg-slate-50 p-6 md:col-span-2 xl:col-span-3 text-center">
                                        <h3 className="text-lg font-bold text-slate-800">No downloadable public packages yet</h3>
                                        <p className="mt-1 text-sm text-slate-600">
                                            Official files attached to published disclosures will appear here for public download.
                                        </p>
                                    </article>
                                ) : (
                                    publicReports.map((report) => (
                                        <article key={report.document_id} className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-5 transition hover:shadow-md hover:border-red-200">
                                            <div>
                                                <div className="flex items-center justify-between">
                                                    <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                                                        Official File
                                                    </span>
                                                    <span className="text-xs font-medium text-slate-500">{formatDateTime(report.uploaded_at)}</span>
                                                </div>
                                                <h3 className="mt-3 line-clamp-2 text-base font-bold text-slate-900">{report.title}</h3>
                                                <p className="mt-1 text-xs text-slate-600 truncate">File: <span className="font-mono text-slate-700">{report.file_name}</span></p>
                                                <p className="mt-1 text-xs text-slate-500 line-clamp-1">Disclosure: {report.disclosure_title}</p>
                                            </div>
                                            <a
                                                href={route('public.disclosures.documents.download', {
                                                    public_disclosure: report.disclosure_id,
                                                    document: report.document_id,
                                                })}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={`mt-4 w-full ${primaryButtonClass}`}
                                            >
                                                Download Official File
                                            </a>
                                        </article>
                                    ))
                                )}
                            </div>
                        )}
                    </section>

                    {/* Summary & Statistics Section */}
                    <section id="summary" className="space-y-4">
                        <PublicStatCards stats={summaryStats} />
                        <PublicInfoCards monthlyDisclosures={monthlyDisclosures} typeBreakdown={typeBreakdown} />
                    </section>
                </main>
            </div>
        </>
    );
}





