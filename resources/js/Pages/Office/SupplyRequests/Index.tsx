import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type RequestRow = {
    supply_request_id: number;
    request_code: string;
    status: string;
    request_date: string;
};

type Props = {
    supplyRequests: { data: RequestRow[] };
};

export default function SupplyRequestsIndex({ supplyRequests }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supply Requests</h2>}>
            <Head title="Supply Requests" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link
                            href={route('office.supply-requests.create')}
                            className="rounded bg-emerald-600 px-4 py-2 text-sm text-white"
                        >
                            Create Supply Request
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">Code</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-left">Request Date</th>
                                    <th className="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {supplyRequests.data.map((row) => (
                                    <tr key={row.supply_request_id} className="border-t">
                                        <td className="px-3 py-2">{row.request_code}</td>
                                        <td className="px-3 py-2">{row.status}</td>
                                        <td className="px-3 py-2">{row.request_date}</td>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={route('office.supply-requests.show', row.supply_request_id)}
                                                className="text-indigo-600 hover:underline"
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
