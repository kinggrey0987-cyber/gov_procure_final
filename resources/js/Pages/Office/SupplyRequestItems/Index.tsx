import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    supplyRequest: { supply_request_id: number };
    items: {
        data: Array<{
            supply_request_item_id: number;
            item_id: number;
            item_name_snapshot: string;
            quantity_requested: string;
        }>;
    };
};

export default function SupplyRequestItemsIndex({ supplyRequest, items }: Props) {
    const form = useForm({
        item_id: 0,
        ppmp_item_id: null as number | null,
        item_name_snapshot: '',
        quantity_requested: 1,
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Supply Request Items</h2>}>
            <Head title="Supply Request Items" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('office.supply-requests.items.store', supplyRequest.supply_request_id));
                        }}
                        className="grid grid-cols-1 gap-2 rounded-lg border bg-white p-4 sm:grid-cols-3"
                    >
                        <input type="number" className="rounded border px-2 py-1" placeholder="Item ID" onChange={(e) => form.setData('item_id', Number(e.target.value))} />
                        <input type="number" className="rounded border px-2 py-1" placeholder="PPMP Item ID" onChange={(e) => form.setData('ppmp_item_id', e.target.value ? Number(e.target.value) : null)} />
                        <input className="rounded border px-2 py-1" placeholder="Item Name Snapshot" onChange={(e) => form.setData('item_name_snapshot', e.target.value)} />
                        <input type="number" className="rounded border px-2 py-1" placeholder="Quantity" onChange={(e) => form.setData('quantity_requested', Number(e.target.value))} />
                        <button type="submit" className="rounded bg-red-700 px-3 py-2 font-semibold text-white hover:bg-red-800">Add Item</button>
                    </form>

                    <div className="responsive-table-wrap rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">ID</th>
                                    <th className="px-3 py-2 text-left">Item</th>
                                    <th className="px-3 py-2 text-left">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.map((item) => (
                                    <tr key={item.supply_request_item_id} className="border-t">
                                        <td className="px-3 py-2">{item.supply_request_item_id}</td>
                                        <td className="px-3 py-2">{item.item_name_snapshot}</td>
                                        <td className="px-3 py-2">{item.quantity_requested}</td>
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




