import { Head, Link } from '@inertiajs/react';

type DisclosureRow = {
    disclosure_id: number;
    title: string;
    type: string;
    published_at: string;
};

type Props = {
    disclosures: { data: DisclosureRow[] };
};

export default function PublicDisclosureIndex({ disclosures }: Props) {
    return (
        <>
            <Head title="Public Disclosures" />
            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-bold text-slate-900">Public Disclosures</h1>
                    <p className="mt-1 text-sm text-slate-600">Approved and published records only.</p>

                    <div className="mt-6 overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Title</th>
                                    <th className="px-4 py-2 text-left">Type</th>
                                    <th className="px-4 py-2 text-left">Published</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {disclosures.data.map((row) => (
                                    <tr key={row.disclosure_id}>
                                        <td className="px-4 py-2">{row.title}</td>
                                        <td className="px-4 py-2">{row.type}</td>
                                        <td className="px-4 py-2">{row.published_at}</td>
                                        <td className="px-4 py-2">
                                            <Link href={route('public.disclosures.show', row.disclosure_id)} className="rounded bg-indigo-600 px-3 py-1 text-xs text-white">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
