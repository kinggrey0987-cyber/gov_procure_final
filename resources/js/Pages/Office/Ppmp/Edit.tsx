import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type ItemOption = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    item_category_name: string;
};

type ExistingItem = {
    ppmp_item_id: number;
    item_id: number;
    item_category_id: number;
    quantity: number;
    unit_cost: number;
    procurement_schedule: string;
    fund_source: string;
    remarks?: string | null;
    item?: { item_name?: string | null } | null;
};

type Props = {
    ppmp: {
        ppmp_id: number;
        fiscal_year: number;
        date_prepared: string;
        remarks: string | null;
        items: ExistingItem[];
    };
    itemOptions: ItemOption[];
};

export default function PpmpEdit({ ppmp, itemOptions }: Props) {
    const form = useForm({
        fiscal_year: ppmp.fiscal_year,
        date_prepared: ppmp.date_prepared,
        remarks: ppmp.remarks ?? '',
    });

    const [newItem, setNewItem] = useState({
        item_id: '',
        item_category_id: '',
        quantity: '1',
        unit_cost: '0',
        procurement_schedule: '',
        fund_source: '',
        remarks: '',
    });

    const [editingRows, setEditingRows] = useState<Record<number, ExistingItem>>(
        Object.fromEntries(ppmp.items.map((item) => [item.ppmp_item_id, item])),
    );

    const submitHeader = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('office.ppmp.update', ppmp.ppmp_id));
    };

    const addItem = () => {
        router.post(route('office.ppmp.items.store', ppmp.ppmp_id), newItem);
    };

    const updateItem = (itemId: number) => {
        router.put(route('office.ppmp.items.update', [ppmp.ppmp_id, itemId]), editingRows[itemId]);
    };

    const removeItem = (itemId: number) => {
        router.delete(route('office.ppmp.items.destroy', [ppmp.ppmp_id, itemId]));
    };

    const onOptionChange = (value: string) => {
        const option = itemOptions.find((entry) => String(entry.item_id) === value);
        if (!option) {
            setNewItem({ ...newItem, item_id: '', item_category_id: '' });
            return;
        }

        setNewItem({ ...newItem, item_id: String(option.item_id), item_category_id: String(option.item_category_id) });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit PPMP</h2>}>
            <Head title="Edit PPMP" />

            <div className="space-y-6 py-4">
                <form onSubmit={submitHeader} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-2xl font-semibold text-slate-800">PPMP #{ppmp.ppmp_id}</h3>
                        <Link href={route('office.ppmp.show', ppmp.ppmp_id)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to Details</Link>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Fiscal Year</span>
                            <input type="number" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.fiscal_year} onChange={(event) => form.setData('fiscal_year', Number(event.target.value))} />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Date Prepared</span>
                            <input type="date" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.date_prepared} onChange={(event) => form.setData('date_prepared', event.target.value)} />
                        </label>
                    </div>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-slate-700">Remarks</span>
                        <textarea className="h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.remarks} onChange={(event) => form.setData('remarks', event.target.value)} />
                    </label>
                    <div className="flex justify-end">
                        <button type="submit" className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white">Update PPMP Details</button>
                    </div>
                </form>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Edit Existing Items</h4>
                    {ppmp.items.length === 0 ? (
                        <p className="text-sm text-slate-600">No items yet.</p>
                    ) : (
                        <div className="space-y-3">
                            {ppmp.items.map((item) => {
                                const row = editingRows[item.ppmp_item_id];
                                return (
                                    <div key={item.ppmp_item_id} className="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-8">
                                        <input
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                                            value={row.item?.item_name ?? `Item #${row.item_id}`}
                                            readOnly
                                        />
                                        <input type="number" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={row.quantity} onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, quantity: Number(event.target.value) } })} />
                                        <input type="number" step="0.01" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={row.unit_cost} onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, unit_cost: Number(event.target.value) } })} />
                                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={row.procurement_schedule} onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, procurement_schedule: event.target.value } })} />
                                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={row.fund_source} onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, fund_source: event.target.value } })} />
                                        <button type="button" onClick={() => updateItem(item.ppmp_item_id)} className="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700">Update</button>
                                        <button type="button" onClick={() => removeItem(item.ppmp_item_id)} className="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700">Remove</button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Add New Item</h4>
                    <div className="grid gap-3 md:grid-cols-7">
                        <select className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" value={newItem.item_id} onChange={(event) => onOptionChange(event.target.value)}>
                            <option value="">Select Item</option>
                            {itemOptions.map((option) => (
                                <option key={option.item_id} value={option.item_id}>{option.item_name}</option>
                            ))}
                        </select>
                        <input type="number" min="1" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.quantity} onChange={(event) => setNewItem({ ...newItem, quantity: event.target.value })} placeholder="Qty" />
                        <input type="number" min="0" step="0.01" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.unit_cost} onChange={(event) => setNewItem({ ...newItem, unit_cost: event.target.value })} placeholder="Unit Cost" />
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.procurement_schedule} onChange={(event) => setNewItem({ ...newItem, procurement_schedule: event.target.value })} placeholder="Schedule" />
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.fund_source} onChange={(event) => setNewItem({ ...newItem, fund_source: event.target.value })} placeholder="Fund Source" />
                        <button type="button" onClick={addItem} className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Add Item</button>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
