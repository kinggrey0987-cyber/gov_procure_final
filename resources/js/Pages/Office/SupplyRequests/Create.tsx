import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';

type Option = {
    ppmp_item_id: number;
    item_id: number;
    item_name: string;
    suggested_unit_cost: number;
};

type RequestItemDraft = {
    ppmp_item_id: string;
    item_id: string;
    item_name_snapshot: string;
    quantity_requested: string;
    estimated_unit_cost: string;
};

type Props = {
    ppmpItemOptions: Option[];
};

export default function SupplyRequestCreate({ ppmpItemOptions }: Props) {
    const form = useForm({
        request_date: new Date().toISOString().slice(0, 10),
        needed_by_date: new Date().toISOString().slice(0, 10),
        request_category: 'GENERAL',
        purpose: '',
        priority_level: 'MEDIUM',
        review_notes: '',
        items: [emptyItem()],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('office.supply-requests.store'));
    };

    const addItem = () => {
        form.setData('items', [...form.data.items, emptyItem()]);
    };

    const removeItem = (index: number) => {
        if (form.data.items.length === 1) {
            return;
        }

        form.setData(
            'items',
            form.data.items.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const updateItem = (index: number, patch: Partial<RequestItemDraft>) => {
        form.setData(
            'items',
            form.data.items.map((item, currentIndex) => (currentIndex === index ? { ...item, ...patch } : item)),
        );
    };

    const handleOptionChange = (index: number, value: string) => {
        const option = ppmpItemOptions.find((current) => String(current.ppmp_item_id) === value);
        if (!option) {
            updateItem(index, {
                ppmp_item_id: '',
                item_id: '',
                item_name_snapshot: '',
                estimated_unit_cost: '',
            });
            return;
        }

        updateItem(index, {
            ppmp_item_id: String(option.ppmp_item_id),
            item_id: String(option.item_id),
            item_name_snapshot: option.item_name,
            estimated_unit_cost: String(option.suggested_unit_cost),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Supply Request</h2>}>
            <Head title="Create Supply Request" />

            <form onSubmit={submit} className="space-y-6 py-4">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">New Supply Request</h3>
                            <p className="text-sm text-slate-600">Prepare your request for review and procurement processing.</p>
                        </div>
                        <Link
                            href={route('office.supply-requests.index')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Back to Tracking
                        </Link>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Request Date">
                            <input
                                type="date"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.request_date}
                                onChange={(event) => form.setData('request_date', event.target.value)}
                            />
                        </Field>

                        <Field label="Needed By Date">
                            <input
                                type="date"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.needed_by_date}
                                onChange={(event) => form.setData('needed_by_date', event.target.value)}
                            />
                        </Field>

                        <Field label="Request Category">
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.request_category}
                                onChange={(event) => form.setData('request_category', event.target.value)}
                            >
                                <option value="GENERAL">General</option>
                                <option value="OFFICE_SUPPLIES">Office Supplies</option>
                                <option value="IT_EQUIPMENT">IT Equipment</option>
                                <option value="FACILITY">Facility</option>
                            </select>
                        </Field>

                        <Field label="Priority Level">
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.priority_level}
                                onChange={(event) => form.setData('priority_level', event.target.value)}
                            >
                                <option value="LOW">Low</option>
                                <option value="MEDIUM">Medium</option>
                                <option value="HIGH">High</option>
                                <option value="URGENT">Urgent</option>
                            </select>
                        </Field>
                    </div>

                    <div className="mt-4 grid gap-4">
                        <Field label="Purpose">
                            <textarea
                                className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.purpose}
                                onChange={(event) => form.setData('purpose', event.target.value)}
                            />
                        </Field>
                        <Field label="Notes (Optional)">
                            <textarea
                                className="h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.review_notes}
                                onChange={(event) => form.setData('review_notes', event.target.value)}
                            />
                        </Field>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <h4 className="text-xl font-semibold text-slate-800">Request Items</h4>
                        <button
                            type="button"
                            onClick={addItem}
                            className="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                        >
                            Add Item Row
                        </button>
                    </div>

                    <div className="space-y-4">
                        {form.data.items.map((item, index) => (
                            <div key={index} className="rounded-xl border border-slate-200 p-4">
                                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                                    <Field label="PPMP Item">
                                        <select
                                            value={item.ppmp_item_id}
                                            onChange={(event) => handleOptionChange(index, event.target.value)}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        >
                                            <option value="">Select PPMP item</option>
                                            {ppmpItemOptions.map((option) => (
                                                <option key={option.ppmp_item_id} value={option.ppmp_item_id}>
                                                    {option.item_name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Item Name Snapshot">
                                        <input
                                            value={item.item_name_snapshot}
                                            onChange={(event) => updateItem(index, { item_name_snapshot: event.target.value })}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </Field>

                                    <Field label="Quantity">
                                        <input
                                            type="number"
                                            min="1"
                                            value={item.quantity_requested}
                                            onChange={(event) => updateItem(index, { quantity_requested: event.target.value })}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </Field>

                                    <Field label="Estimated Unit Cost">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.estimated_unit_cost}
                                            onChange={(event) => updateItem(index, { estimated_unit_cost: event.target.value })}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </Field>
                                </div>

                                <input type="hidden" value={item.item_id} readOnly />

                                <div className="mt-3 flex justify-end">
                                    <button
                                        type="button"
                                        onClick={() => removeItem(index)}
                                        className="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    >
                                        Remove Row
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex justify-end gap-2">
                        <Link
                            href={route('office.supply-requests.index')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                        >
                            Save Request
                        </button>
                    </div>
                </section>
            </form>
        </AuthenticatedLayout>
    );
}

function emptyItem(): RequestItemDraft {
    return {
        ppmp_item_id: '',
        item_id: '',
        item_name_snapshot: '',
        quantity_requested: '1',
        estimated_unit_cost: '0',
    };
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            {children}
        </label>
    );
}
