import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseRequest = {
    purchase_request_id: number;
    supply_request_id: number;
    office_id: number;
    status: string;
    remarks: string | null;
};

type Props = {
    purchaseRequest: PurchaseRequest;
};

export default function PurchaseRequestsShow({ purchaseRequest }: Props) {
    const processForm = useForm({ remarks: '' });

    const processRequest = (event: FormEvent) => {
        event.preventDefault();
        processForm.post(route('procurement.purchase-requests.process', purchaseRequest.purchase_request_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Request</h2>}>
            <Head title="Purchase Request" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-6 text-sm">
                        <p><strong>ID:</strong> {purchaseRequest.purchase_request_id}</p>
                        <p><strong>Supply Request:</strong> {purchaseRequest.supply_request_id}</p>
                        <p><strong>Office:</strong> {purchaseRequest.office_id}</p>
                        <p><strong>Status:</strong> {purchaseRequest.status}</p>
                        <p><strong>Remarks:</strong> {purchaseRequest.remarks ?? '-'}</p>
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={route('procurement.purchase-requests.edit', purchaseRequest.purchase_request_id)}
                            className="rounded bg-indigo-600 px-4 py-2 text-sm text-white"
                        >
                            Edit
                        </Link>
                        <Link
                            href={route('procurement.purchase-requests.items.index', purchaseRequest.purchase_request_id)}
                            className="rounded bg-emerald-600 px-4 py-2 text-sm text-white"
                        >
                            Manage Items
                        </Link>
                    </div>

                    {purchaseRequest.status === 'OPEN' && (
                        <form onSubmit={processRequest} className="space-y-3 rounded-lg border bg-white p-6">
                            <label className="mb-1 block text-sm text-gray-700">Processing Remarks</label>
                            <textarea
                                value={processForm.data.remarks}
                                onChange={(event) => processForm.setData('remarks', event.target.value)}
                                className="w-full rounded border-gray-300 text-sm"
                                rows={3}
                            />
                            <button type="submit" className="rounded bg-amber-600 px-4 py-2 text-sm text-white">
                                Move To Processing
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
