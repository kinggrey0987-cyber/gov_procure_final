import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type PurchaseOrderRow = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    total_amount: string;
};

type Props = {
    purchaseOrders: {
        data: PurchaseOrderRow[];
    };
};

export default function PurchaseOrdersIndex({ purchaseOrders }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Orders</h2>}>
            <Head title="Purchase Orders" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('procurement.purchase-orders.create')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Create Purchase Order
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">PO Number</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-left">Total Amount</th>
                                    <th className="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {purchaseOrders.data.map((row) => (
                                    <tr key={row.purchase_order_id} className="border-t">
                                        <td className="px-3 py-2">{row.po_number}</td>
                                        <td className="px-3 py-2">{row.status}</td>
                                        <td className="px-3 py-2">{row.total_amount}</td>
                                        <td className="px-3 py-2">
                                            <Link href={route('procurement.purchase-orders.show', row.purchase_order_id)} className="text-indigo-600 hover:underline">
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
