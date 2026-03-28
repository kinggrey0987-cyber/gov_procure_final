import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type PurchaseRequestRow = {
    purchase_request_id: number;
    supply_request_id: number;
    office_id: number;
    status: string;
};

type Props = {
    purchaseRequests: {
        data: PurchaseRequestRow[];
    };
};

export default function PurchaseRequestsIndex({ purchaseRequests }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Requests</h2>}>
            <Head title="Purchase Requests" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link
                            href={route('procurement.purchase-requests.create')}
                            className="rounded bg-indigo-600 px-4 py-2 text-sm text-white"
                        >
                            Create Purchase Request
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">PR ID</th>
                                    <th className="px-3 py-2 text-left">Supply Request</th>
                                    <th className="px-3 py-2 text-left">Office</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {purchaseRequests.data.map((row) => (
                                    <tr key={row.purchase_request_id} className="border-t">
                                        <td className="px-3 py-2">{row.purchase_request_id}</td>
                                        <td className="px-3 py-2">{row.supply_request_id}</td>
                                        <td className="px-3 py-2">{row.office_id}</td>
                                        <td className="px-3 py-2">{row.status}</td>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={route('procurement.purchase-requests.show', row.purchase_request_id)}
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
