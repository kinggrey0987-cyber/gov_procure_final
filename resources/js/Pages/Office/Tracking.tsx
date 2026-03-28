import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    summary: {
        draft_ppmp_count: number;
        submitted_ppmp_count: number;
        draft_supply_request_count: number;
        submitted_supply_request_count: number;
        open_purchase_request_count: number;
        processing_purchase_request_count: number;
        placed_purchase_order_count: number;
    };
};

export default function OfficeTracking({ summary }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Office Transaction Tracking</h2>}
        >
            <Head title="Office Tracking" />

            <div className="py-10">
                <div className="mx-auto grid max-w-6xl gap-4 px-4 sm:grid-cols-2 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">PPMP Draft</p>
                        <p className="text-2xl font-semibold">{summary.draft_ppmp_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">PPMP Submitted</p>
                        <p className="text-2xl font-semibold">{summary.submitted_ppmp_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Supply Request Draft</p>
                        <p className="text-2xl font-semibold">{summary.draft_supply_request_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Supply Request Submitted</p>
                        <p className="text-2xl font-semibold">{summary.submitted_supply_request_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Open Purchase Requests</p>
                        <p className="text-2xl font-semibold">{summary.open_purchase_request_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Processing Purchase Requests</p>
                        <p className="text-2xl font-semibold">{summary.processing_purchase_request_count}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4">
                        <p className="text-sm text-gray-500">Placed Purchase Orders</p>
                        <p className="text-2xl font-semibold">{summary.placed_purchase_order_count}</p>
                    </div>
                </div>

                <div className="mx-auto mt-6 flex max-w-6xl gap-3 px-4 sm:px-6 lg:px-8">
                    <Link href={route('office.ppmp.index')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                        Manage PPMP
                    </Link>
                    <Link
                        href={route('office.supply-requests.index')}
                        className="rounded bg-emerald-600 px-4 py-2 text-sm text-white"
                    >
                        Manage Supply Requests
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
