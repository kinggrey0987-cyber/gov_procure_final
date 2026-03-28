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

const processSteps = [
    {
        step: '1',
        title: 'Planning and Request Submission',
        description: 'Offices submit procurement requirements for validation and proper budget alignment.',
    },
    {
        step: '2',
        title: 'Review and Compliance Checking',
        description: 'Requests are reviewed against procurement policy and required supporting documents.',
    },
    {
        step: '3',
        title: 'Approval and Procurement Execution',
        description: 'Approved requests proceed through procurement actions and supplier engagement.',
    },
    {
        step: '4',
        title: 'Publication of Disclosures',
        description: 'Approved public disclosure records are published for citizen access and oversight.',
    },
];

const publicAccessItems = [
    'Approved and published procurement disclosures',
    'Public procurement totals and visual summaries',
    'Downloadable transparency reports',
    'Procurement process overview and accountability principles',
];

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

                <main className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                    <PublicStatCards stats={summaryStats} />

                    <PublicInfoCards monthlyDisclosures={monthlyDisclosures} typeBreakdown={typeBreakdown} />

                    <section id="disclosures" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-6">
                            <div className="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-red-700">
                                Public Disclosures
                            </div>
                            <h2 className="mt-3 text-4xl font-bold text-red-700">Latest Published Disclosures</h2>
                            <p className="mt-2 text-xl text-slate-600">
                                Public-facing procurement records currently available for transparency and review.
                            </p>
                        </div>
                        <div className="grid gap-4 p-6 md:grid-cols-2">
                            {latestDisclosures.length === 0 && (
                                <article className="rounded-2xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                                    <h3 className="text-2xl font-bold text-slate-800">No published disclosures yet</h3>
                                    <p className="mt-2 text-lg text-slate-600">
                                        The portal will list disclosure records as soon as they are approved and published.
                                    </p>
                                </article>
                            )}
                            {latestDisclosures.map((item) => (
                                <article key={item.disclosure_id} className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-green-700">
                                            Published
                                        </span>
                                        <span className="text-sm font-semibold text-slate-500">{formatDateTime(item.published_at)}</span>
                                    </div>
                                    <h3 className="mt-4 text-2xl font-bold text-slate-800">{item.title}</h3>
                                    <p className="mt-2 text-lg text-slate-600">Type: {item.type}</p>
                                    <div className="mt-4 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3">
                                        <p className="text-sm text-slate-500">Public Attachments</p>
                                        <p className="text-lg font-bold text-red-700">{item.attachment_count}</p>
                                    </div>
                                    <a
                                        href={route('public.disclosures.show', item.disclosure_id)}
                                        className={`mt-4 ${primaryButtonClass}`}
                                    >
                                        View Disclosure
                                    </a>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section id="reports" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-6">
                            <h2 className="text-4xl font-bold text-red-700">Public Reports</h2>
                            <p className="mt-2 text-xl text-slate-600">Download files attached to approved and published public disclosures.</p>
                        </div>
                        <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                            {publicReports.length === 0 && (
                                <article className="rounded-2xl border border-slate-200 p-5 md:col-span-2 xl:col-span-3">
                                    <h3 className="text-2xl font-bold text-slate-800">No downloadable public files yet</h3>
                                    <p className="mt-2 text-lg text-slate-600">
                                        Once published disclosures include attachments, they will appear here for public download.
                                    </p>
                                </article>
                            )}
                            {publicReports.map((report) => (
                                <article key={report.document_id} className="rounded-2xl border border-slate-200 p-5">
                                    <h3 className="text-2xl font-bold text-slate-800">{report.title}</h3>
                                    <p className="mt-2 text-base text-slate-600">File: {report.file_name}</p>
                                    <p className="mt-1 text-base text-slate-600">Disclosure: {report.disclosure_title}</p>
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

                    <section id="transparency" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 p-6">
                            <h2 className="text-4xl font-bold text-red-700">How Public Procurement Transparency Works</h2>
                            <p className="mt-2 text-xl text-slate-600">A concise guide to what gets published and what citizens can access.</p>
                        </div>
                        <div className="space-y-6 p-6">
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                {processSteps.map((item) => (
                                    <article key={item.step} className="rounded-2xl border border-slate-200 p-5">
                                        <p className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-red-700 text-sm font-bold text-white">
                                            {item.step}
                                        </p>
                                        <h3 className="mt-4 text-xl font-bold text-slate-800">{item.title}</h3>
                                        <p className="mt-2 text-base text-slate-600">{item.description}</p>
                                    </article>
                                ))}
                            </div>

                            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <h3 className="text-2xl font-bold text-slate-800">What The Public Can Access</h3>
                                <ul className="mt-4 grid gap-3 text-lg text-slate-700 md:grid-cols-2">
                                    {publicAccessItems.map((item) => (
                                        <li key={item} className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section id="about" className="grid gap-4 lg:grid-cols-2">
                        <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 p-6">
                                <h2 className="text-4xl font-bold text-red-700">About GovSupply Connect</h2>
                            </div>
                            <div className="space-y-4 p-6 text-xl text-slate-700">
                                <p>
                                    GovSupply Connect is the official procurement and supply monitoring system for
                                    the Municipality of E.B. Magalona, Negros Occidental.
                                </p>
                                <p>
                                    Developed in compliance with Philippine government procurement regulations,
                                    the system promotes transparency, accountability, and efficient resource management.
                                </p>
                                <p>
                                    The public dashboard shows approved and published disclosure records,
                                    transparency summaries, and downloadable public documents.
                                </p>
                            </div>
                        </article>

                        <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 p-6">
                                <h2 className="text-3xl font-bold text-slate-800">Contact Information</h2>
                            </div>
                            <div className="space-y-4 p-6 text-xl text-slate-700">
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">General Services Office</h3>
                                    <p>Municipal Hall, E.B. Magalona</p>
                                    <p>Negros Occidental, Philippines</p>
                                </div>
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">Office Hours</h3>
                                    <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
                                    <p>Saturday - Sunday: Closed</p>
                                </div>
                                <div>
                                    <h3 className="text-2xl font-bold text-slate-800">Contact Details</h3>
                                    <p>Email: gso@ebmagalona.gov.ph</p>
                                    <p>Phone: (034) XXX-XXXX</p>
                                    <p>Procurement: procurement@ebmagalona.gov.ph</p>
                                </div>
                            </div>
                        </article>
                    </section>
                </main>
            </div>
        </>
    );
}
