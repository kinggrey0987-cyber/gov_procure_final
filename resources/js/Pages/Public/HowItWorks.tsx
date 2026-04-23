import PublicNavbar from '@/Components/Public/PublicNavbar';
import { Head } from '@inertiajs/react';

type Props = {
    canLogin?: boolean;
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

export default function HowItWorks({ canLogin = true }: Props) {
    return (
        <>
            <Head title="How Public Procurement Transparency Works" />

            <div className="min-h-screen bg-slate-100">
                <PublicNavbar canLogin={canLogin} />

                <main className="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h1 className="text-2xl font-bold text-red-700">How Public Procurement Transparency Works</h1>
                        <p className="mt-2 text-sm text-slate-600">A concise guide to what gets published and what citizens can access.</p>
                    </section>

                    <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        {processSteps.map((item) => (
                            <article key={item.step} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-red-700 text-sm font-bold text-white">
                                    {item.step}
                                </p>
                                <h2 className="mt-3 text-base font-bold text-slate-800">{item.title}</h2>
                                <p className="mt-2 text-sm text-slate-600">{item.description}</p>
                            </article>
                        ))}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-lg font-bold text-slate-800">What The Public Can Access</h2>
                        <ul className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                            {publicAccessItems.map((item) => (
                                <li key={item} className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </section>
                </main>
            </div>
        </>
    );
}
