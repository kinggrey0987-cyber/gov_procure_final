import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type DistributionItem = {
    distribution_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    quantity_released: string;
    unit: string;
};

type Props = {
    record: {
        distribution_record_id: number;
        items: DistributionItem[];
    };
};

export default function DistributionItems({ record }: Props) {
    const form = useForm({
        item_id: '',
        item_name_snapshot: '',
        quantity_released: '',
        unit: 'PCS',
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Manage Distribution Items</h2>}>
            <Head title="Distribution Items" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <form
                        className="grid gap-2 rounded border bg-white p-4 sm:grid-cols-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('inventory.distribution-records.items.store', record.distribution_record_id));
                        }}
                    >
                        <input className="rounded border px-3 py-2" placeholder="Item ID" value={form.data.item_id} onChange={(e) => form.setData('item_id', e.target.value)} />
                        <input className="rounded border px-3 py-2" placeholder="Name" value={form.data.item_name_snapshot} onChange={(e) => form.setData('item_name_snapshot', e.target.value)} />
                        <input className="rounded border px-3 py-2" placeholder="Quantity" value={form.data.quantity_released} onChange={(e) => form.setData('quantity_released', e.target.value)} />
                        <input className="rounded border px-3 py-2" placeholder="Unit" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">Add</button>
                    </form>

                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Item ID</th>
                                    <th className="px-4 py-2 text-left">Name</th>
                                    <th className="px-4 py-2 text-left">Quantity</th>
                                    <th className="px-4 py-2 text-left">Unit</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {record.items.map((item) => (
                                    <tr key={item.distribution_item_id}>
                                        <td className="px-4 py-2">{item.item_id}</td>
                                        <td className="px-4 py-2">{item.item_name_snapshot}</td>
                                        <td className="px-4 py-2">{item.quantity_released}</td>
                                        <td className="px-4 py-2">{item.unit}</td>
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
