import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type RecordRow = {
    distribution_record_id: number;
    distribution_code: string;
    status: string;
};

type Props = {
    records: {
        data: RecordRow[];
    };
};

export default function DistributionIndex({ records }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Distribution Records</h2>}>
            <Head title="Distribution Records" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('inventory.distribution-records.create')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            New Distribution Record
                        </Link>
                    </div>
                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Code</th>
                                    <th className="px-4 py-2 text-left">Status</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.data.map((row) => (
                                    <tr key={row.distribution_record_id}>
                                        <td className="px-4 py-2">{row.distribution_code}</td>
                                        <td className="px-4 py-2">{row.status}</td>
                                        <td className="px-4 py-2">
                                            <Link
                                                href={route('inventory.distribution-records.show', row.distribution_record_id)}
                                                className="rounded bg-indigo-600 px-3 py-1 text-xs text-white"
                                            >
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
