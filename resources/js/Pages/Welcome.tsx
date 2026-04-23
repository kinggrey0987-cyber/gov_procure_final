import PublicHero from '@/Components/Public/PublicHero';
import PublicInfoCards from '@/Components/Public/PublicInfoCards';
import PublicNavbar from '@/Components/Public/PublicNavbar';
import PublicStatCards from '@/Components/Public/PublicStatCards';
import { Head } from '@inertiajs/react';

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

export default function Welcome({ canLogin, summaryStats, latestDisclosures, monthlyDisclosures, typeBreakdown, publicReports }: WelcomeProps) {
    return (
        <>
            <Head title="GovSupply Connect" />

            <div className="min-h-screen bg-slate-100">
                <PublicNavbar canLogin={canLogin} />
                <PublicHero
                    title="Municipal Procurement Transparency Portal"
                    subtitle="Promoting transparency and accountability in municipal procurement and supply distribution."
                />

                <main className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                    <section id="disclosures" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-5">
                            <div className="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-red-700">
                                Public Disclosures
                            </div>
                            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-2xl font-bold text-red-700">Latest Published Disclosures</h2>
                                <a href={route('public.disclosures.index')} className="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                    View All Disclosures
                                </a>
                            </div>
                            <p className="mt-1 text-sm text-slate-600">
                                Public-facing procurement records currently available for transparency and review.
                            </p>
                        </div>
                        <div className="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                            {latestDisclosures.length === 0 && (
                                <article className="rounded-2xl border border-slate-200 bg-slate-50 p-5 md:col-span-2 xl:col-span-3">
                                    <h3 className="text-xl font-bold text-slate-800">No published disclosures yet</h3>
                                    <p className="mt-1 text-sm text-slate-600">
                                        The portal will list disclosure records as soon as they are approved and published.
                                    </p>
                                </article>
                            )}
                            {latestDisclosures.slice(0, 5).map((item) => (
                                <article key={item.disclosure_id} className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-green-700">
                                            Published
                                        </span>
                                        <span className="text-sm font-semibold text-slate-500">{formatDateTime(item.published_at)}</span>
                                    </div>
                                    <h3 className="mt-3 line-clamp-2 text-lg font-bold text-slate-800">{item.title}</h3>
                                    <p className="mt-1 text-sm text-slate-600">Type: {item.type}</p>
                                    <div className="mt-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-2">
                                        <p className="text-sm text-slate-500">Public Attachments</p>
                                        <p className="text-base font-bold text-red-700">{item.attachment_count}</p>
                                    </div>
                                    <a
                                        href={route('public.disclosures.show', item.disclosure_id)}
                                        className={`mt-3 ${primaryButtonClass}`}
                                    >
                                        View Disclosure
                                    </a>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section id="summary" className="space-y-4">
                        <PublicStatCards stats={summaryStats} />
                        <PublicInfoCards monthlyDisclosures={monthlyDisclosures} typeBreakdown={typeBreakdown} />
                    </section>

                    <section id="reports" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-5">
                            <h2 className="text-2xl font-bold text-red-700">Public Reports</h2>
                            <p className="mt-1 text-sm text-slate-600">Download files attached to approved and published public disclosures.</p>
                        </div>
                        <div className="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                            {publicReports.length === 0 && (
                                <article className="rounded-2xl border border-slate-200 p-5 md:col-span-2 xl:col-span-3">
                                    <h3 className="text-xl font-bold text-slate-800">No downloadable public files yet</h3>
                                    <p className="mt-1 text-sm text-slate-600">
                                        Once published disclosures include attachments, they will appear here for public download.
                                    </p>
                                </article>
                            )}
                            {publicReports.slice(0, 5).map((report) => (
                                <article key={report.document_id} className="rounded-2xl border border-slate-200 p-5">
                                    <h3 className="line-clamp-2 text-lg font-bold text-slate-800">{report.title}</h3>
                                    <p className="mt-2 text-sm text-slate-600">File: {report.file_name}</p>
                                    <p className="mt-1 text-sm text-slate-600">Disclosure: {report.disclosure_title}</p>
                                    <p className="mt-3 text-sm font-semibold text-red-600">Uploaded: {formatDateTime(report.uploaded_at)}</p>
                                    <a
                                        href={route('public.disclosures.documents.download', {
                                            public_disclosure: report.disclosure_id,
                                            document: report.document_id,
                                        })}
                                        className={`mt-5 w-full ${primaryButtonClass}`}
                                    >
                                        Download File
                                    </a>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section id="transparency" className="grid gap-3 lg:grid-cols-2">
                        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-xl font-bold text-red-700">How Public Procurement Transparency Works</h2>
                            <p className="mt-2 text-sm text-slate-600">
                                Read the process flow, publication checkpoints, and visibility rules in one page.
                            </p>
                            <a href={route('public.how-it-works')} className={`mt-4 ${primaryButtonClass}`}>
                                Open How It Works
                            </a>
                        </article>

                        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-xl font-bold text-red-700">About GovSupply Connect</h2>
                            <p className="mt-2 text-sm text-slate-600">
                                Learn the mandate, governance context, and contact channels for the municipal team.
                            </p>
                            <a href={route('public.about')} className={`mt-4 ${primaryButtonClass}`}>
                                Open About Page
                            </a>
                        </article>
                    </section>
                </main>
            </div>
        </>
    );
}
