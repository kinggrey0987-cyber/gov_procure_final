import PaginationNav from '@/Components/PaginationNav';
import { Head, Link } from '@inertiajs/react';

type DisclosureRow = {
    disclosure_id: number;
    title: string;
    type: string;
    published_at: string;
};

type Props = {
    disclosures: {
        data: DisclosureRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page?: number;
    };
};

export default function PublicDisclosureIndex({ disclosures }: Props) {
    const rows = disclosures.data ?? [];
    const currentPage = disclosures.current_page || undefined;

    return (
        <>
            <Head title="Public Disclosures" />
            <div className="min-h-screen bg-gradient-to-b from-red-50 via-white to-red-50 py-12">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-red-200 bg-white p-6 shadow-[0_10px_24px_rgba(15,23,42,0.08)] sm:p-8">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <Link
                                href={route('landing')}
                                className="inline-flex rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                            >
                                Back to Homepage
                            </Link>
                            <span className="inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">
                                {rows.length} Published on this page
                            </span>
                        </div>

                        <h1 className="text-3xl font-semibold tracking-tight text-slate-900">Public Disclosures</h1>
                        <p className="mt-2 text-sm text-slate-600">Published procurement records available for public transparency.</p>

                        <div className="mt-6 overflow-hidden rounded-xl border border-red-200">
                            {rows.length === 0 ? (
                                <div className="px-6 py-12 text-center">
                                    <p className="text-base font-medium text-slate-800">No public disclosures available yet.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-red-100 text-sm">
                                        <thead className="bg-red-50/80">
                                            <tr>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Title</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Type</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Published</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-red-100 bg-white">
                                            {rows.map((row) => (
                                                <tr key={row.disclosure_id} className="transition-colors hover:bg-red-50/50">
                                                    <td className="px-5 py-3.5 text-sm font-medium text-slate-900">{row.title}</td>
                                                    <td className="px-5 py-3.5 text-slate-700">
                                                        <span className="inline-flex rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                            {formatTypeLabel(row.type)}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-3.5 text-slate-700">{formatPublishedDate(row.published_at)}</td>
                                                    <td className="px-5 py-3.5">
                                                        <Link
                                                            href={buildDisclosureHref(row.disclosure_id, currentPage)}
                                                            className="inline-flex rounded-lg bg-red-700 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-red-800"
                                                        >
                                                            View Disclosure
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        <div className="mt-5">
                            <PaginationNav links={disclosures.links} preserveState={false} />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function buildDisclosureHref(disclosureId: number, page?: number): string {
    const base = `/public/disclosures/${disclosureId}`;
    return page ? `${base}?page=${page}` : base;
}

function formatTypeLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatPublishedDate(value: string): string {
    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    const date = parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    const time = parsed.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });

    return `${date} • ${time}`;
}





