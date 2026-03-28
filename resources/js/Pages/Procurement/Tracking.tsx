import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    summary: {
        open_purchase_request_count: number;
        processing_purchase_request_count: number;
        draft_purchase_order_count: number;
        placed_purchase_order_count: number;
    };
};

export default function ProcurementTracking({ summary }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Procurement Status Tracking</h2>}
        >
            <Head title="Procurement Tracking" />

            <div className="py-10">
                <div className="mx-auto grid max-w-6xl gap-4 px-4 sm:grid-cols-2 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Open Purchase Requests</p>
                        <p className="text-2xl font-semibold">{summary.open_purchase_request_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Processing Purchase Requests</p>
                        <p className="text-2xl font-semibold">{summary.processing_purchase_request_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Draft Purchase Orders</p>
                        <p className="text-2xl font-semibold">{summary.draft_purchase_order_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Placed Purchase Orders</p>
                        <p className="text-2xl font-semibold">{summary.placed_purchase_order_count}</p>
                    </div>
                </div>

                <div className="mx-auto mt-6 flex max-w-6xl gap-3 px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('procurement.purchase-requests.index')}
                        className="rounded bg-indigo-600 px-4 py-2 text-sm text-white"
                    >
                        Manage Purchase Requests
                    </Link>
                    <Link
                        href={route('procurement.suppliers.index')}
                        className="rounded bg-emerald-600 px-4 py-2 text-sm text-white"
                    >
                        Manage Suppliers
                    </Link>
                    <Link
                        href={route('procurement.purchase-orders.index')}
                        className="rounded bg-sky-600 px-4 py-2 text-sm text-white"
                    >
                        Manage Purchase Orders
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
