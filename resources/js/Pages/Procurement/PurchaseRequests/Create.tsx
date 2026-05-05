import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type EligibleSupplyRequest = {
    supply_request_id: number;
    request_code: string;
    office_id: number;
    status: string;
};

type Props = {
    eligibleSupplyRequests: EligibleSupplyRequest[];
};

export default function PurchaseRequestsCreate({ eligibleSupplyRequests }: Props) {
    const form = useForm({
        supply_request_id: eligibleSupplyRequests[0]?.supply_request_id ?? 0,
        remarks: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('procurement.purchase-requests.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Purchase Request</h2>}>
            <Head title="Create Purchase Request" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-6">
                        <div>
                            <label className="mb-1 block text-sm text-gray-700">Submitted Supply Request</label>
                            <select
                                value={form.data.supply_request_id}
                                onChange={(event) => form.setData('supply_request_id', Number(event.target.value))}
                                className="w-full rounded border-gray-300 text-sm"
                            >
                                {eligibleSupplyRequests.map((row) => (
                                    <option key={row.supply_request_id} value={row.supply_request_id}>
                                        {row.request_code} - Office {row.office_id}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-gray-700">Remarks</label>
                            <textarea
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                                className="w-full rounded border-gray-300 text-sm"
                                rows={3}
                            />
                        </div>

                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Create Purchase Request
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





