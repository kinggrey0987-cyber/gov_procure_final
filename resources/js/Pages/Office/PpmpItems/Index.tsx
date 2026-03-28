import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    ppmp: { ppmp_id: number };
    items: {
        data: Array<{
            ppmp_item_id: number;
            item_id: number;
            item_category_id: number;
            quantity: string;
            unit_cost: string;
            estimated_budget: string;
            procurement_schedule: string;
            fund_source: string;
            remarks: string | null;
        }>;
    };
};

export default function PpmpItemsIndex({ ppmp, items }: Props) {
    const form = useForm({
        item_id: 0,
        item_category_id: 0,
        quantity: 1,
        unit_cost: 0,
        procurement_schedule: '',
        fund_source: '',
        remarks: '',
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">PPMP Items</h2>}>
            <Head title="PPMP Items" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('office.ppmp.items.store', ppmp.ppmp_id));
                        }}
                        className="grid grid-cols-1 gap-2 rounded-lg border bg-white p-4 sm:grid-cols-3"
                    >
                        <input type="number" className="rounded border px-2 py-1" placeholder="Item ID" onChange={(e) => form.setData('item_id', Number(e.target.value))} />
                        <input type="number" className="rounded border px-2 py-1" placeholder="Category ID" onChange={(e) => form.setData('item_category_id', Number(e.target.value))} />
                        <input type="number" className="rounded border px-2 py-1" placeholder="Quantity" onChange={(e) => form.setData('quantity', Number(e.target.value))} />
                        <input type="number" className="rounded border px-2 py-1" placeholder="Unit Cost" onChange={(e) => form.setData('unit_cost', Number(e.target.value))} />
                        <input className="rounded border px-2 py-1" placeholder="Schedule" onChange={(e) => form.setData('procurement_schedule', e.target.value)} />
                        <input className="rounded border px-2 py-1" placeholder="Fund Source" onChange={(e) => form.setData('fund_source', e.target.value)} />
                        <input className="rounded border px-2 py-1 sm:col-span-2" placeholder="Remarks" onChange={(e) => form.setData('remarks', e.target.value)} />
                        <button type="submit" className="rounded bg-indigo-600 px-3 py-2 text-white">Add Item</button>
                    </form>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left">ID</th>
                                    <th className="px-3 py-2 text-left">Item</th>
                                    <th className="px-3 py-2 text-left">Qty</th>
                                    <th className="px-3 py-2 text-left">Budget</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.data.map((item) => (
                                    <tr key={item.ppmp_item_id} className="border-t">
                                        <td className="px-3 py-2">{item.ppmp_item_id}</td>
                                        <td className="px-3 py-2">{item.item_id}</td>
                                        <td className="px-3 py-2">{item.quantity}</td>
                                        <td className="px-3 py-2">{item.estimated_budget}</td>
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
