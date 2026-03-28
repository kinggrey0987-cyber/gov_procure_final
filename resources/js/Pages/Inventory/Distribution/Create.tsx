import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Option = {
    supply_request_id?: number;
    delivery_id?: number;
    office_id?: number;
};

type Props = {
    supplyRequests: Option[];
    deliveries: Option[];
};

export default function DistributionCreate({ supplyRequests, deliveries }: Props) {
    const form = useForm({
        supply_request_id: supplyRequests[0]?.supply_request_id ?? '',
        delivery_id: deliveries[0]?.delivery_id ?? '',
        office_id: '',
        release_date: '',
        storage_location: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Distribution</h2>}>
            <Head title="Create Distribution" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form
                        className="space-y-3 rounded border bg-white p-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('inventory.distribution-records.store'));
                        }}
                    >
                        <input
                            className="w-full rounded border px-3 py-2"
                            placeholder="Supply Request ID"
                            value={form.data.supply_request_id}
                            onChange={(e) => form.setData('supply_request_id', Number(e.target.value))}
                        />
                        <input
                            className="w-full rounded border px-3 py-2"
                            placeholder="Delivery ID"
                            value={form.data.delivery_id}
                            onChange={(e) => form.setData('delivery_id', Number(e.target.value))}
                        />
                        <input
                            className="w-full rounded border px-3 py-2"
                            placeholder="Office ID"
                            value={form.data.office_id}
                            onChange={(e) => form.setData('office_id', e.target.value)}
                        />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Save
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
