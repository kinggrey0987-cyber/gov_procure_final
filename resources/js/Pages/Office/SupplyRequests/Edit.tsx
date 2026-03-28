import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

type Option = {
    ppmp_item_id: number;
    item_id: number;
    item_name: string;
    suggested_unit_cost: number;
};

type ExistingItem = {
    supply_request_item_id: number;
    ppmp_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    quantity_requested: number;
    estimated_unit_cost: number;
};

type Props = {
    supplyRequest: {
        supply_request_id: number;
        request_code?: string;
        request_date: string;
        needed_by_date: string;
        request_category: string;
        purpose: string;
        priority_level: string;
        review_notes: string | null;
        items: ExistingItem[];
    };
    ppmpItemOptions: Option[];
};

export default function SupplyRequestEdit({ supplyRequest, ppmpItemOptions }: Props) {
    const form = useForm({
        request_date: supplyRequest.request_date,
        needed_by_date: supplyRequest.needed_by_date,
        request_category: supplyRequest.request_category,
        purpose: supplyRequest.purpose,
        priority_level: supplyRequest.priority_level,
        review_notes: supplyRequest.review_notes ?? '',
    });

    const [newItem, setNewItem] = useState({
        ppmp_item_id: '',
        item_id: '',
        item_name_snapshot: '',
        quantity_requested: '1',
        estimated_unit_cost: '0',
    });

    const [editingRows, setEditingRows] = useState<Record<number, ExistingItem>>(
        Object.fromEntries(supplyRequest.items.map((item) => [item.supply_request_item_id, item])),
    );

    const submitHeader = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('office.supply-requests.update', supplyRequest.supply_request_id));
    };

    const addItem = () => {
        router.post(route('office.supply-requests.items.store', supplyRequest.supply_request_id), newItem);
    };

    const updateItem = (itemId: number) => {
        const item = editingRows[itemId];
        router.put(route('office.supply-requests.items.update', [supplyRequest.supply_request_id, itemId]), item);
    };

    const removeItem = (itemId: number) => {
        router.delete(route('office.supply-requests.items.destroy', [supplyRequest.supply_request_id, itemId]));
    };

    const onNewItemOptionChange = (value: string) => {
        const option = ppmpItemOptions.find((entry) => String(entry.ppmp_item_id) === value);
        if (!option) {
            setNewItem({ ...newItem, ppmp_item_id: '', item_id: '', item_name_snapshot: '', estimated_unit_cost: '' });
            return;
        }

        setNewItem({
            ...newItem,
            ppmp_item_id: String(option.ppmp_item_id),
            item_id: String(option.item_id),
            item_name_snapshot: option.item_name,
            estimated_unit_cost: String(option.suggested_unit_cost),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Supply Request</h2>}>
            <Head title="Edit Supply Request" />

            <div className="space-y-6 py-4">
                <form onSubmit={submitHeader} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-2xl font-semibold text-slate-800">{supplyRequest.request_code ?? 'Supply Request'}</h3>
                        <Link
                            href={route('office.supply-requests.show', supplyRequest.supply_request_id)}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Back to Details
                        </Link>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Request Date">
                            <input type="date" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.request_date} onChange={(event) => form.setData('request_date', event.target.value)} />
                        </Field>
                        <Field label="Needed By Date">
                            <input type="date" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.needed_by_date} onChange={(event) => form.setData('needed_by_date', event.target.value)} />
                        </Field>
                        <Field label="Request Category">
                            <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.request_category} onChange={(event) => form.setData('request_category', event.target.value)} />
                        </Field>
                        <Field label="Priority Level">
                            <select className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.priority_level} onChange={(event) => form.setData('priority_level', event.target.value)}>
                                <option value="LOW">Low</option>
                                <option value="MEDIUM">Medium</option>
                                <option value="HIGH">High</option>
                                <option value="URGENT">Urgent</option>
                            </select>
                        </Field>
                    </div>

                    <Field label="Purpose">
                        <textarea className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.purpose} onChange={(event) => form.setData('purpose', event.target.value)} />
                    </Field>
                    <Field label="Notes">
                        <textarea className="h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.review_notes} onChange={(event) => form.setData('review_notes', event.target.value)} />
                    </Field>

                    <div className="flex justify-end">
                        <button type="submit" className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white">Update Request Details</button>
                    </div>
                </form>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Edit Existing Items</h4>
                    {supplyRequest.items.length === 0 ? (
                        <p className="text-sm text-slate-600">No items attached yet.</p>
                    ) : (
                        <div className="space-y-3">
                            {supplyRequest.items.map((item) => {
                                const row = editingRows[item.supply_request_item_id];
                                return (
                                    <div key={item.supply_request_item_id} className="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-6">
                                        <input
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                                            value={row.item_name_snapshot}
                                            onChange={(event) => setEditingRows({ ...editingRows, [item.supply_request_item_id]: { ...row, item_name_snapshot: event.target.value } })}
                                        />
                                        <input
                                            type="number"
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                            value={row.quantity_requested}
                                            onChange={(event) => setEditingRows({ ...editingRows, [item.supply_request_item_id]: { ...row, quantity_requested: Number(event.target.value) } })}
                                        />
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                            value={row.estimated_unit_cost}
                                            onChange={(event) => setEditingRows({ ...editingRows, [item.supply_request_item_id]: { ...row, estimated_unit_cost: Number(event.target.value) } })}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => updateItem(item.supply_request_item_id)}
                                            className="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700"
                                        >
                                            Update
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => removeItem(item.supply_request_item_id)}
                                            className="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Add New Item</h4>
                    <div className="grid gap-3 md:grid-cols-5">
                        <select
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                            value={newItem.ppmp_item_id}
                            onChange={(event) => onNewItemOptionChange(event.target.value)}
                        >
                            <option value="">Select PPMP Item</option>
                            {ppmpItemOptions.map((option) => (
                                <option key={option.ppmp_item_id} value={option.ppmp_item_id}>{option.item_name}</option>
                            ))}
                        </select>
                        <input
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            value={newItem.quantity_requested}
                            onChange={(event) => setNewItem({ ...newItem, quantity_requested: event.target.value })}
                            type="number"
                            min="1"
                        />
                        <input
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            value={newItem.estimated_unit_cost}
                            onChange={(event) => setNewItem({ ...newItem, estimated_unit_cost: event.target.value })}
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <button type="button" onClick={addItem} className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">
                            Add Item
                        </button>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            {children}
        </label>
    );
}
