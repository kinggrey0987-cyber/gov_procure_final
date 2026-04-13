import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';

type PpmpOption = {
    ppmp_id: number;
    label: string;
    status: string;
    date_prepared?: string | null;
};

type Option = {
    ppmp_item_id: number;
    ppmp_id: number;
    item_id: number;
    item_name: string;
    planned_quantity: number;
    requested_quantity: number;
    remaining_quantity: number;
    over_requested_quantity: number;
    suggested_unit_cost: number;
};

type ExistingItem = {
    supply_request_item_id: number;
    ppmp_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    quantity_requested: number;
};

type Props = {
    supplyRequest: {
        supply_request_id: number;
        request_code?: string;
        ppmp_id?: number | null;
        request_date: string;
        needed_by_date: string;
        request_category: string;
        purpose: string;
        priority_level: string;
        review_notes: string | null;
        items: ExistingItem[];
    };
    ppmpOptions: PpmpOption[];
    selectedPpmpId: number | null;
    ppmpItemOptions: Option[];
};

export default function SupplyRequestEdit({ supplyRequest, ppmpOptions, selectedPpmpId, ppmpItemOptions: initialOptions }: Props) {
    const [itemOptions, setItemOptions] = useState<Option[]>(initialOptions);

    const form = useForm({
        ppmp_id: selectedPpmpId ? String(selectedPpmpId) : '',
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
    });

    const [editingRows, setEditingRows] = useState<Record<number, ExistingItem>>(
        Object.fromEntries(supplyRequest.items.map((item) => [item.supply_request_item_id, item])),
    );

    const hasPpmp = form.data.ppmp_id !== '';

    const optionById = useMemo(() => {
        return new Map(itemOptions.map((option) => [String(option.ppmp_item_id), option]));
    }, [itemOptions]);

    const submitHeader = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('office.supply-requests.update', supplyRequest.supply_request_id));
    };

    const addItem = () => {
        if (!hasPpmp) {
            return;
        }

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
        const option = itemOptions.find((entry) => String(entry.ppmp_item_id) === value);
        if (!option) {
            setNewItem({ ...newItem, ppmp_item_id: '', item_id: '', item_name_snapshot: '' });
            return;
        }

        setNewItem({
            ...newItem,
            ppmp_item_id: String(option.ppmp_item_id),
            item_id: String(option.item_id),
            item_name_snapshot: option.item_name,
        });
    };

    const onHeaderPpmpChange = async (value: string) => {
        form.setData('ppmp_id', value);

        if (!value) {
            setItemOptions([]);
            return;
        }

        const response = await fetch(route('office.supply-requests.ppmp-items', value), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            setItemOptions([]);
            return;
        }

        const payload = (await response.json()) as { ppmp_item_options?: Option[] };
        setItemOptions(payload.ppmp_item_options ?? []);
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

                    <Field label="Source PPMP">
                        <select
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            value={form.data.ppmp_id}
                            onChange={(event) => void onHeaderPpmpChange(event.target.value)}
                        >
                            <option value="">Select PPMP</option>
                            {ppmpOptions.map((option) => (
                                <option key={option.ppmp_id} value={option.ppmp_id}>
                                    {option.label} ({option.status})
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">Only approved PPMPs can be used as the basis for a supply request.</p>
                    </Field>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Request Date" hint="Request date is automatically recorded by the system.">
                            <input type="text" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" value={supplyRequest.request_date} readOnly aria-readonly="true" />
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
                                const context = optionById.get(String(row.ppmp_item_id));
                                const exceeds = context ? Number(row.quantity_requested) > (context.remaining_quantity + Number(item.quantity_requested)) : false;

                                return (
                                    <div key={item.supply_request_item_id} className="rounded-xl border border-slate-200 p-4">
                                        <div className="grid gap-3 md:grid-cols-5">
                                            <label className="md:col-span-2">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Item Snapshot</span>
                                                <input
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                    value={row.item_name_snapshot}
                                                    readOnly
                                                    aria-readonly="true"
                                                />
                                                <span className="mt-1 block text-xs text-slate-500">Auto-filled from the selected PPMP item.</span>
                                            </label>
                                            <input
                                                type="number"
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                min="1"
                                                value={row.quantity_requested}
                                                onChange={(event) => setEditingRows({ ...editingRows, [item.supply_request_item_id]: { ...row, quantity_requested: Number(event.target.value) } })}
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

                                        {context ? (
                                            <div className="mt-2 grid gap-2 text-xs text-slate-600 md:grid-cols-4">
                                                <div>Planned: <strong>{context.planned_quantity}</strong></div>
                                                <div>Already requested: <strong>{context.requested_quantity}</strong></div>
                                                <div>Remaining before this line: <strong>{context.remaining_quantity}</strong></div>
                                                <div>PPMP reference unit cost: <strong>{context.suggested_unit_cost}</strong></div>
                                            </div>
                                        ) : null}

                                        {exceeds ? <p className="mt-2 text-xs font-semibold text-rose-700">Requested quantity is above remaining PPMP allowance.</p> : null}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Add New Item</h4>
                    {!hasPpmp ? <p className="text-sm font-medium text-amber-700">Select a PPMP on the header first.</p> : null}
                    <div className="grid gap-3 md:grid-cols-4">
                        <select
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                            value={newItem.ppmp_item_id}
                            onChange={(event) => onNewItemOptionChange(event.target.value)}
                            disabled={!hasPpmp}
                        >
                            <option value="">{hasPpmp ? 'Select PPMP Item' : 'Select PPMP first'}</option>
                            {itemOptions.map((option) => (
                                <option key={option.ppmp_item_id} value={option.ppmp_item_id}>{option.item_name} (remaining: {option.remaining_quantity})</option>
                            ))}
                        </select>
                        <input
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            value={newItem.quantity_requested}
                            onChange={(event) => setNewItem({ ...newItem, quantity_requested: event.target.value })}
                            type="number"
                            min="1"
                        />
                        <button type="button" onClick={addItem} disabled={!hasPpmp} className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            Add Item
                        </button>
                    </div>

                    {newItem.ppmp_item_id ? (
                        <div className="text-xs text-slate-600">
                            Remaining quantity: <strong>{optionById.get(newItem.ppmp_item_id)?.remaining_quantity ?? 0}</strong>
                        </div>
                    ) : null}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            {children}
            {hint ? <span className="mt-1 block text-xs text-slate-500">{hint}</span> : null}
        </label>
    );
}
