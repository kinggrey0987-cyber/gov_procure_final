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

    return (
        <>
            <Head title="Public Disclosures" />
            <div className="min-h-screen bg-slate-50 py-12">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)] sm:p-8">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <Link
                                href={route('landing')}
                                className="inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Back to Homepage
                            </Link>
                        </div>

                        <h1 className="text-3xl font-semibold tracking-tight text-slate-900">Public Disclosures</h1>
                        <p className="mt-2 text-sm text-slate-600">Published procurement records available for public transparency.</p>

                        <div className="mt-6 overflow-hidden rounded-xl border border-slate-200">
                            {rows.length === 0 ? (
                                <div className="px-6 py-12 text-center">
                                    <p className="text-base font-medium text-slate-800">No public disclosures available yet.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Title</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Type</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Published</th>
                                                <th className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 bg-white">
                                            {rows.map((row) => (
                                                <tr key={row.disclosure_id} className="transition-colors hover:bg-slate-50/80">
                                                    <td className="px-5 py-3.5 text-sm font-medium text-slate-900">{row.title}</td>
                                                    <td className="px-5 py-3.5 text-slate-700">{formatTypeLabel(row.type)}</td>
                                                    <td className="px-5 py-3.5 text-slate-700">{formatPublishedDate(row.published_at)}</td>
                                                    <td className="px-5 py-3.5">
                                                        <Link
                                                            href={route('public.disclosures.show', {
                                                                public_disclosure: row.disclosure_id,
                                                                page: disclosures.current_page || undefined,
                                                            })}
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
