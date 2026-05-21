import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { FUND_SOURCES } from '@/constants/fundSources';

type ItemOption = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    item_category_name: string;
    default_unit?: string | null;
};

type ExistingItem = {
    ppmp_item_id: number;
    item_id: number;
    item_category_id: number;
    quantity: number;
    unit_cost: number;
    estimated_budget?: number;
    procurement_schedule: string;
    remarks?: string | null;
    item?: { item_name?: string | null; default_unit?: string | null } | null;
};

type Props = {
    ppmp: {
        ppmp_id: number;
        fiscal_year: number;
        date_prepared: string;
        fund_source: string;
        remarks: string | null;
        status: string;
        items: ExistingItem[];
    };
    itemOptions: ItemOption[];
};

type PageFlash = {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
};

const QUARTER_OPTIONS = ['Q1', 'Q2', 'Q3', 'Q4'] as const;

export default function PpmpEdit({ ppmp, itemOptions }: Props) {
    const { flash, errors: pageErrors } = usePage().props as unknown as PageFlash;
    const [processingRowId, setProcessingRowId] = useState<number | null>(null);
    const { isRefreshing } = useSmartRefresh({
        only: ['itemOptions'],
        refreshOnFocus: true,
        refreshOnMount: true,
    });

    const form = useForm({
        fiscal_year: ppmp.fiscal_year,
        date_prepared: ppmp.date_prepared,
        fund_source: ppmp.fund_source,
        remarks: ppmp.remarks ?? '',
    });

    const newItem = useForm({
        item_id: '',
        item_category_id: '',
        quantity: '1',
        unit_cost: '0',
        procurement_schedule: '',
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
        newItem.post(route('office.ppmp.items.store', ppmp.ppmp_id), {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                newItem.reset('item_id', 'item_category_id', 'quantity', 'unit_cost', 'procurement_schedule', 'remarks');
                newItem.setData('quantity', '1');
                newItem.setData('unit_cost', '0');
            },
        });
    };

    const updateItem = (itemId: number) => {
        setProcessingRowId(itemId);
        router.put(route('office.ppmp.items.update', [ppmp.ppmp_id, itemId]), editingRows[itemId], {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => setProcessingRowId(null),
        });
    };

    const removeItem = (itemId: number) => {
        if (!window.confirm('Remove this PPMP item line?')) {
            return;
        }

        setProcessingRowId(itemId);
        router.delete(route('office.ppmp.items.destroy', [ppmp.ppmp_id, itemId]), {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => setProcessingRowId(null),
        });
    };

    const onOptionChange = (value: string) => {
        const option = itemOptions.find((entry) => String(entry.item_id) === value);
        if (!option) {
            newItem.setData('item_id', '');
            newItem.setData('item_category_id', '');
            return;
        }

        newItem.setData('item_id', String(option.item_id));
        newItem.setData('item_category_id', String(option.item_category_id));
    };

    const itemOptionById = new Map(itemOptions.map((option) => [String(option.item_id), option]));
    const selectedNewItemUnit = itemOptionById.get(newItem.data.item_id)?.default_unit || '-';

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit PPMP</h2>}>
            <Head title="Edit PPMP" />

            <div className="space-y-6 py-4">
                {flash?.success ? (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm">
                        {flash.success}
                    </div>
                ) : null}

                {flash?.error ? (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 shadow-sm">
                        {flash.error}
                    </div>
                ) : null}

                {pageErrors && Object.keys(pageErrors).length > 0 ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 shadow-sm">
                        Please review the highlighted item line fields and try again.
                    </div>
                ) : null}

                <form onSubmit={submitHeader} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-2xl font-semibold text-slate-800">PPMP #{ppmp.ppmp_id}</h3>
                        <div className="flex items-center gap-2">
                            {isRefreshing ? <span className="text-xs text-slate-500">Refreshing item options...</span> : null}
                            <Link href={route('office.ppmp.show', ppmp.ppmp_id)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to Details</Link>
                        </div>
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
                        <label className="block md:col-span-2">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Fund Source</span>
                            {ppmp.status === 'DRAFT' || ppmp.status === 'RETURNED_FOR_REVISION' ? (
                                <select value={form.data.fund_source} onChange={(event) => form.setData('fund_source', event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    {FUND_SOURCES.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            ) : (
                                <input type="text" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm" value={FUND_SOURCES.find(f => f.value === form.data.fund_source)?.label || form.data.fund_source} readOnly />
                            )}
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
                    <div>
                        <h4 className="text-xl font-semibold text-slate-800">Edit Existing Items</h4>
                        <p className="mt-1 text-sm text-slate-600">Unit Cost and Estimated Budget are planning/reference values used for PPMP preparation.</p>
                    </div>
                    {ppmp.items.length === 0 ? (
                        <p className="text-sm text-slate-600">No items yet.</p>
                    ) : (
                        <div className="space-y-3">
                            {ppmp.items.map((item) => {
                                const row = editingRows[item.ppmp_item_id];
                                const estimatedBudget = Number(row.quantity || 0) * Number(row.unit_cost || 0);
                                const unit = row.item?.default_unit || itemOptionById.get(String(row.item_id))?.default_unit || '-';

                                return (
                                    <div key={item.ppmp_item_id} className="rounded-xl border border-slate-200 p-4">
                                        <div className="mb-3 flex items-center justify-between">
                                            <p className="text-sm font-semibold text-slate-800">PPMP Item Line #{item.ppmp_item_id}</p>
                                            <p className="text-xs text-slate-500">Changes apply to this line only.</p>
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                                            <label className="block xl:col-span-2">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Item</span>
                                                <input
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm"
                                                    value={row.item?.item_name ?? `Item #${row.item_id}`}
                                                    readOnly
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Quantity</span>
                                                <input
                                                    type="number"
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                    value={row.quantity}
                                                    onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, quantity: Number(event.target.value) } })}
                                                />
                                                <span className="mt-1 block text-xs text-slate-500">Quantity: {row.quantity || 0} {unit}</span>
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Unit</span>
                                                <input
                                                    type="text"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm"
                                                    value={unit}
                                                    readOnly
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Unit Cost</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                    value={row.unit_cost}
                                                    onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, unit_cost: Number(event.target.value) } })}
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Estimated Budget</span>
                                                <input
                                                    type="text"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm"
                                                    value={estimatedBudget.toFixed(2)}
                                                    readOnly
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Procurement Schedule</span>
                                                <select
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                    value={row.procurement_schedule}
                                                    onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, procurement_schedule: event.target.value } })}
                                                >
                                                    {scheduleOptionsForValue(row.procurement_schedule).map((option) => (
                                                        <option key={option.value} value={option.value}>{option.label}</option>
                                                    ))}
                                                </select>
                                            </label>
                                        </div>

                                        <div className="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-3">
                                            <span className="mr-auto text-xs font-medium text-slate-500">Line Actions</span>
                                            <button
                                                type="button"
                                                onClick={() => updateItem(item.ppmp_item_id)}
                                                disabled={processingRowId === item.ppmp_item_id}
                                                className="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                {processingRowId === item.ppmp_item_id ? 'Updating...' : 'Update Line'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeItem(item.ppmp_item_id)}
                                                disabled={processingRowId === item.ppmp_item_id}
                                                className="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                {processingRowId === item.ppmp_item_id ? 'Removing...' : 'Remove Line'}
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-6 shadow-sm">
                    <div>
                        <h4 className="text-xl font-semibold text-slate-800">Add New Item</h4>
                        <p className="mt-1 text-sm text-slate-600">Add a planning line to this PPMP using the same required fields.</p>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                        <label className="block xl:col-span-2">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Item</span>
                            <select className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.data.item_id} onChange={(event) => onOptionChange(event.target.value)}>
                                <option value="">Select Item</option>
                                {itemOptions.map((option) => (
                                    <option key={option.item_id} value={option.item_id}>{option.item_name}</option>
                                ))}
                            </select>
                            <InputError message={newItem.errors.item_id} className="mt-1" />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Quantity</span>
                            <input type="number" min="1" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.data.quantity} onChange={(event) => newItem.setData('quantity', event.target.value)} />
                            <span className="mt-1 block text-xs text-slate-500">Quantity: {newItem.data.quantity || 0} {selectedNewItemUnit}</span>
                            <InputError message={newItem.errors.quantity} className="mt-1" />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Unit</span>
                            <input type="text" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm" value={selectedNewItemUnit} readOnly />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Unit Cost</span>
                            <input type="number" min="0" step="0.01" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.data.unit_cost} onChange={(event) => newItem.setData('unit_cost', event.target.value)} />
                            <InputError message={newItem.errors.unit_cost} className="mt-1" />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Procurement Schedule</span>
                            <select className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.data.procurement_schedule} onChange={(event) => newItem.setData('procurement_schedule', event.target.value)}>
                                <option value="">Select Quarter</option>
                                {QUARTER_OPTIONS.map((quarter) => (
                                    <option key={quarter} value={quarter}>{quarter}</option>
                                ))}
                            </select>
                            <InputError message={newItem.errors.procurement_schedule} className="mt-1" />
                        </label>
                    </div>

                    <InputError message={newItem.errors.item_category_id} className="mt-1" />
                    <InputError message={newItem.errors.remarks} className="mt-1" />

                    <div className="flex justify-end">
                        <button type="button" onClick={addItem} disabled={newItem.processing} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">Add Item</button>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function scheduleOptionsForValue(value: string): Array<{ value: string; label: string }> {
    const normalized = value.trim().toUpperCase();
    const options = QUARTER_OPTIONS.map((quarter) => ({ value: quarter, label: quarter }));

    if (normalized === '' || QUARTER_OPTIONS.includes(normalized as (typeof QUARTER_OPTIONS)[number])) {
        return [{ value: '', label: 'Select Quarter' }, ...options];
    }

    return [{ value: normalized, label: `${normalized} (legacy value)` }, ...options];
}





