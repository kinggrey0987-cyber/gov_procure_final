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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Disclosure Review</h2>}>
            <Head title="Disclosure Review" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        Executive access is restricted to disclosure monitoring and review.
                    </div>
                    <div className="overflow-hidden rounded border bg-white">
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
                                {disclosures.data.map((row) => (
                                    <tr key={row.disclosure_id}>
                                        <td className="px-4 py-2">{row.title}</td>
                                        <td className="px-4 py-2">{row.type}</td>
                                        <td className="px-4 py-2">{row.status}</td>
                                        <td className="px-4 py-2">
                                            <Link href={route('executive.disclosures.show', row.disclosure_id)} className="rounded bg-indigo-600 px-3 py-1 text-xs text-white">
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
        </AuthenticatedLayout>
    );
}
