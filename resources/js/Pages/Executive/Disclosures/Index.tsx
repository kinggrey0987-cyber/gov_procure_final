import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type DisclosureRow = {
    disclosure_id: number;
    title: string;
    type: string;
    status: string;
};

type Props = {
    disclosures: { data: DisclosureRow[] };
};

export default function DisclosureIndex({ disclosures }: Props) {
    return (
        <AuthenticatedLayout header="Disclosure Review">
            <Head title="Disclosure Review" />
            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white px-6 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Disclosure Governance</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">Executive Disclosure Review Queue</h3>
                    <p className="mt-1 text-sm text-slate-600">Read-only monitoring access for published and draft disclosure records.</p>
                    <div className="mt-4">
                        <Link href={route('executive.disclosures.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Create Disclosure
                        </Link>
                    </div>
                </section>

                <section className="rounded-2xl border border-red-200 bg-red-50/60 px-5 py-4 text-sm text-red-800">
                    Executive access is restricted to disclosure monitoring and review.
                </section>

                <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-xl font-semibold text-slate-900">Disclosure Records</h3>
                        <p className="mt-1 text-sm text-slate-600">Low-visual list view prioritized for traceable record reading.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Title</th>
                                    <th className="px-4 py-2 text-left">Type</th>
                                    <th className="px-4 py-2 text-left">Status</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {disclosures.data.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-sm text-slate-500">No disclosure records yet.</td>
                                    </tr>
                                )}
                                {disclosures.data.map((row) => (
                                    <tr key={row.disclosure_id}>
                                        <td className="px-4 py-2">{row.title}</td>
                                        <td className="px-4 py-2">{row.type}</td>
                                        <td className="px-4 py-2">
                                            <span className={row.status === 'PUBLISHED' ? 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800' : 'rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800'}>
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2">
                                            <Link href={route('executive.disclosures.show', row.disclosure_id)} className="rounded bg-red-700 px-3 py-1 text-xs font-semibold text-white hover:bg-red-800">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
