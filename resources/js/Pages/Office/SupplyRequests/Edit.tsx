import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationModal from '@/Components/ConfirmationModal';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';
import { dateInputValue } from '@/lib/dateInput';

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
    item_category_id?: number;
    category_name?: string;
    item_name: string;
    item_specification?: string | null;
    default_unit?: string | null;
    planned_quantity: number;
    requested_quantity: number;
    remaining_quantity: number;
    over_requested_quantity: number;
    suggested_unit_cost: number;
    procurement_schedule?: string | null;
    is_schedule_mismatch?: boolean;
    current_quarter?: string | null;
};

type ExistingItem = {
    supply_request_item_id: number;
    ppmp_item_id: number;
    item_id: number;
    item_name_snapshot: string;
    quantity_requested: number;
    item_specification?: string | null;
    ppmp_item?: { remarks?: string | null } | null;
};

type CategoryOption = {
    item_category_id: number;
    category_name: string;
};

type Props = {
    supplyRequest: {
        supply_request_id: number;
        request_code?: string;
        ppmp_id?: number | null;
        item_category_id?: number | null;
        category?: CategoryOption | null;
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
    categories?: CategoryOption[];
};

export default function SupplyRequestEdit({ supplyRequest, ppmpOptions, selectedPpmpId, ppmpItemOptions: initialOptions, categories = [] }: Props) {
    const [itemOptions, setItemOptions] = useState<Option[]>(initialOptions);
    const [itemPendingRemoval, setItemPendingRemoval] = useState<ExistingItem | null>(null);
    const [isRemoving, setIsRemoving] = useState(false);

    const form = useForm({
        ppmp_id: selectedPpmpId ? String(selectedPpmpId) : '',
        item_category_id: supplyRequest.item_category_id ? String(supplyRequest.item_category_id) : '',
        request_date: dateInputValue(supplyRequest.request_date),
        needed_by_date: dateInputValue(supplyRequest.needed_by_date),
        request_category: supplyRequest.category?.category_name ?? supplyRequest.request_category,
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

    const newItemOption = useMemo(() => optionById.get(newItem.ppmp_item_id), [newItem.ppmp_item_id, optionById]);
    const existingPpmpItemIds = useMemo(() => {
        return Object.values(editingRows).map((item) => String(item.ppmp_item_id));
    }, [editingRows]);

    const newItemIsDuplicate = newItem.ppmp_item_id !== '' && existingPpmpItemIds.includes(newItem.ppmp_item_id);

    const filteredItemOptions = useMemo(() => {
        const selectedCatId = Number(form.data.item_category_id);
        if (!selectedCatId) {
            return itemOptions;
        }
        return itemOptions.filter((opt) => !opt.item_category_id || opt.item_category_id === selectedCatId);
    }, [itemOptions, form.data.item_category_id]);

    const mismatchedExistingItems = useMemo(() => {
        const selectedCatId = Number(form.data.item_category_id);
        if (!selectedCatId) {
            return [];
        }

        return supplyRequest.items.filter((item) => {
            const row = editingRows[item.supply_request_item_id];
            const opt = optionById.get(String(row.ppmp_item_id));
            const catId = opt?.item_category_id;
            return Boolean(catId && catId !== selectedCatId);
        });
    }, [form.data.item_category_id, supplyRequest.items, editingRows, optionById]);

    const scheduleMismatchExistingLineCount = useMemo(() => {
        return supplyRequest.items.reduce((count, item) => {
            const row = editingRows[item.supply_request_item_id];
            const context = optionById.get(String(row.ppmp_item_id));

            return context?.is_schedule_mismatch ? count + 1 : count;
        }, 0);
    }, [editingRows, optionById, supplyRequest.items]);

    const handleCategoryChange = (val: string) => {
        const cat = categories.find((c) => String(c.item_category_id) === val);
        if (cat) {
            form.setData((prev) => ({
                ...prev,
                item_category_id: String(cat.item_category_id),
                request_category: cat.category_name,
            }));
        } else {
            form.setData((prev) => ({
                ...prev,
                item_category_id: '',
                request_category: val,
            }));
        }
        setNewItem({
            ppmp_item_id: '',
            item_id: '',
            item_name_snapshot: '',
            quantity_requested: '1',
        });
    };

    const submitHeader = (event: FormEvent) => {
        event.preventDefault();
        if (mismatchedExistingItems.length > 0) {
            return;
        }
        form.put(route('office.supply-requests.update', supplyRequest.supply_request_id));
    };

    const addItem = () => {
        if (!hasPpmp || !newItem.ppmp_item_id || newItemIsDuplicate) {
            return;
        }

        router.post(route('office.supply-requests.items.store', supplyRequest.supply_request_id), newItem, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setNewItem({
                    ppmp_item_id: '',
                    item_id: '',
                    item_name_snapshot: '',
                    quantity_requested: '1',
                });
            },
        });
    };

    const updateItem = (itemId: number) => {
        const item = editingRows[itemId];
        router.put(route('office.supply-requests.items.update', [supplyRequest.supply_request_id, itemId]), item, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const confirmRemoveItem = () => {
        if (!itemPendingRemoval) return;

        setIsRemoving(true);
        router.delete(route('office.supply-requests.items.destroy', [supplyRequest.supply_request_id, itemPendingRemoval.supply_request_item_id]), {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setItemPendingRemoval(null);
            },
            onFinish: () => {
                setIsRemoving(false);
            },
        });
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
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-bold leading-tight text-slate-900">Edit Supply Request</h2>
                        <p className="text-xs text-slate-500 mt-0.5">Manage supply request details and line items before submission.</p>
                    </div>
                    <Link
                        href={route('office.supply-requests.show', supplyRequest.supply_request_id)}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition"
                    >
                        <svg className="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Back to Details</span>
                    </Link>
                </div>
            }
        >
            <Head title={`Edit ${supplyRequest.request_code ?? 'Supply Request'}`} />

            <div className="max-w-7xl mx-auto space-y-6 py-4">
                {/* Request Header Details Card */}
                <form onSubmit={submitHeader} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div>
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Request Number</span>
                            <h3 className="text-2xl font-bold text-red-700">{supplyRequest.request_code ?? 'Supply Request'}</h3>
                        </div>
                        <div className="text-right">
                            <span className="text-xs font-medium text-slate-400">Status</span>
                            <div className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                Draft
                            </div>
                        </div>
                    </div>

                    <Field label="Source PPMP" required hint="Only approved PPMPs can be used as the basis for a supply request." error={form.errors.ppmp_id}>
                        <select
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                            value={form.data.ppmp_id}
                            onChange={(event) => void onHeaderPpmpChange(event.target.value)}
                            required
                        >
                            <option value="">Select PPMP</option>
                            {ppmpOptions.map((option) => (
                                <option key={option.ppmp_id} value={option.ppmp_id}>
                                    {option.label} ({option.status})
                                </option>
                            ))}
                        </select>
                    </Field>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Request Date" hint="Request date is automatically recorded by the system.">
                            <input
                                type="text"
                                className="w-full rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-600 cursor-not-allowed"
                                value={form.data.request_date}
                                readOnly
                                aria-readonly="true"
                            />
                        </Field>

                        <Field label="Needed By Date" required error={form.errors.needed_by_date}>
                            <input
                                type="date"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                                value={form.data.needed_by_date}
                                onChange={(event) => form.setData('needed_by_date', event.target.value)}
                                required
                            />
                        </Field>

                        <Field label="Request Category" required error={form.errors.request_category || form.errors.item_category_id}>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                                value={form.data.item_category_id || form.data.request_category}
                                onChange={(event) => handleCategoryChange(event.target.value)}
                                required
                            >
                                <option value="">Select Category</option>
                                {categories.map((cat) => (
                                    <option key={cat.item_category_id} value={cat.item_category_id}>
                                        {cat.category_name}
                                    </option>
                                ))}
                                {categories.length === 0 && form.data.request_category && (
                                    <option value={form.data.request_category}>{form.data.request_category}</option>
                                )}
                            </select>
                            {mismatchedExistingItems.length > 0 ? (
                                <div className="rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs text-rose-900 flex items-start gap-2 mt-2">
                                    <svg className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p className="font-semibold">Category Mismatch ({mismatchedExistingItems.length} item{mismatchedExistingItems.length > 1 ? 's' : ''})</p>
                                        <p className="mt-0.5">
                                            This request already contains {mismatchedExistingItems.length === 1 ? 'an item' : 'items'} ({mismatchedExistingItems.map((i) => i.item_name_snapshot).join(', ')}) from another category. You must remove them before changing to this category.
                                        </p>
                                    </div>
                                </div>
                            ) : null}
                        </Field>

                        <Field label="Priority Level" required error={form.errors.priority_level}>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                                value={form.data.priority_level}
                                onChange={(event) => form.setData('priority_level', event.target.value)}
                                required
                            >
                                <option value="NORMAL">Normal</option>
                                <option value="URGENT">Urgent (Priority Need)</option>
                                {!['NORMAL', 'URGENT'].includes(form.data.priority_level) && form.data.priority_level ? (
                                    <option value={form.data.priority_level}>{form.data.priority_level}</option>
                                ) : null}
                            </select>
                        </Field>
                    </div>

                    <Field label="Purpose" required error={form.errors.purpose}>
                        <textarea
                            rows={3}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                            value={form.data.purpose}
                            onChange={(event) => form.setData('purpose', event.target.value)}
                            placeholder="State the purpose or justification for this supply request..."
                            required
                        />
                    </Field>

                    <Field label="Notes / Remarks" hint="Optional notes or instructions for reviewing officers." error={form.errors.review_notes}>
                        <textarea
                            rows={2}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                            value={form.data.review_notes}
                            onChange={(event) => form.setData('review_notes', event.target.value)}
                            placeholder="Optional notes or remarks..."
                        />
                    </Field>

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={form.processing || mismatchedExistingItems.length > 0}
                            title={mismatchedExistingItems.length > 0 ? 'Remove mismatched items before changing category' : undefined}
                            className="inline-flex items-center gap-2 rounded-lg bg-red-700 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-xs hover:bg-red-800 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 active:bg-red-900 transition disabled:opacity-50"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{form.processing ? 'Saving Changes...' : 'Update Request Details'}</span>
                        </button>
                    </div>
                </form>

                {/* Edit Existing Items Section */}
                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div className="flex items-center gap-2.5">
                            <h4 className="text-lg font-bold text-slate-800">Requested Items</h4>
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                {supplyRequest.items.length} {supplyRequest.items.length === 1 ? 'line' : 'lines'}
                            </span>
                        </div>
                    </div>

                    {scheduleMismatchExistingLineCount > 0 ? (
                        <div className="rounded-xl border border-orange-200 bg-orange-50/80 p-3.5 text-xs text-orange-900 flex items-start gap-2.5">
                            <svg className="w-4 h-4 text-orange-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p className="font-semibold">{scheduleMismatchExistingLineCount} out-of-schedule PPMP line(s) detected.</p>
                                <p className="mt-0.5 text-orange-800">GSO may still process this supply request, but early consumption will be flagged in Executive anomaly monitoring.</p>
                            </div>
                        </div>
                    ) : null}

                    {supplyRequest.items.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center">
                            <p className="text-sm font-medium text-slate-600">No items attached to this request yet.</p>
                            <p className="text-xs text-slate-400 mt-1">Use the "Add Item to Request" section below to select items from your PPMP.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {supplyRequest.items.map((item, index) => {
                                const row = editingRows[item.supply_request_item_id];
                                const context = optionById.get(String(row.ppmp_item_id));
                                const unit = context?.default_unit || '-';
                                const itemSpec = context?.item_specification || item.item_specification || item.ppmp_item?.remarks;

                                return (
                                    <div
                                        key={item.supply_request_item_id}
                                        className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs hover:border-slate-300 transition"
                                    >
                                        {/* Card Header: Line Badge, Item Name, Specs Badge, Schedule */}
                                        <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="inline-flex items-center justify-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                    Line #{index + 1}
                                                </span>
                                                <span className="text-sm font-bold text-slate-800">
                                                    {row.item_name_snapshot}
                                                </span>
                                                {itemSpec ? (
                                                    <span className="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                                        {itemSpec}
                                                    </span>
                                                ) : null}
                                            </div>

                                            {context?.procurement_schedule ? (
                                                <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 border border-slate-200">
                                                    <span className="text-slate-400">Schedule:</span> {context.procurement_schedule}
                                                </span>
                                            ) : null}
                                        </div>

                                        {/* Metadata Metrics Row */}
                                        {context ? (
                                            <div className="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-slate-50/80 rounded-lg p-3 border border-slate-100">
                                                <div>
                                                    <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">Planned</span>
                                                    <span className="font-bold text-slate-700">{context.planned_quantity} {unit}</span>
                                                </div>
                                                <div>
                                                    <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">Total Requested</span>
                                                    <span className="font-bold text-slate-700">{context.requested_quantity} {unit}</span>
                                                </div>
                                                <div>
                                                    <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">Remaining Pre-Request</span>
                                                    <span className="font-bold text-emerald-700">{context.remaining_quantity} {unit}</span>
                                                </div>
                                                <div>
                                                    <span className="block text-[11px] font-medium text-slate-400 uppercase tracking-wider">Schedule</span>
                                                    <span className="font-bold text-slate-700">{context.procurement_schedule || 'All Year'}</span>
                                                </div>
                                            </div>
                                        ) : itemSpec ? (
                                            <div className="mt-2 text-xs text-slate-600">
                                                Specification: <strong className="text-blue-700">{itemSpec}</strong>
                                            </div>
                                        ) : null}

                                        {/* Schedule mismatch banner */}
                                        {context?.is_schedule_mismatch ? (
                                            <div className="mt-3 rounded-lg border border-orange-200 bg-orange-50/70 p-3 text-xs text-orange-900">
                                                <div className="flex items-center gap-2 font-semibold">
                                                    <svg className="w-4 h-4 text-orange-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                    <span>Out-of-schedule PPMP consumption detected</span>
                                                </div>
                                                <p className="mt-1 text-orange-800">
                                                    This item is planned for {context.procurement_schedule || 'a later schedule'} but is being requested in {context.current_quarter || 'the current quarter'}. It can proceed to GSO review but will be flagged for Executive anomaly review.
                                                </p>
                                            </div>
                                        ) : null}

                                        {/* Controls Row: Quantity Input + Update / Remove Buttons */}
                                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
                                            <div className="flex items-center gap-2">
                                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-600">
                                                    Quantity Requested:
                                                </label>
                                                <div className="inline-flex items-center rounded-lg border border-slate-300 bg-white shadow-2xs focus-within:border-red-500 focus-within:ring-1 focus-within:ring-red-500 overflow-hidden">
                                                    <input
                                                        type="number"
                                                        min="0.01"
                                                        step="any"
                                                        className="w-24 border-0 px-3 py-1.5 text-sm font-semibold text-slate-800 focus:ring-0 focus:outline-hidden"
                                                        value={row.quantity_requested}
                                                        onChange={(event) =>
                                                            setEditingRows({
                                                                ...editingRows,
                                                                [item.supply_request_item_id]: {
                                                                    ...row,
                                                                    quantity_requested: Number(event.target.value),
                                                                },
                                                            })
                                                        }
                                                    />
                                                    <span className="bg-slate-50 border-l border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 select-none">
                                                        {unit}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => updateItem(item.supply_request_item_id)}
                                                    disabled={!row.quantity_requested || row.quantity_requested <= 0}
                                                    className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-1.5 text-xs font-semibold shadow-2xs transition disabled:opacity-50"
                                                >
                                                    <svg className="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Save Quantity</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setItemPendingRemoval(row)}
                                                    disabled={isRemoving && itemPendingRemoval?.supply_request_item_id === item.supply_request_item_id}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 px-3 py-1.5 text-xs font-semibold shadow-2xs transition disabled:opacity-50"
                                                >
                                                    <svg className="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    <span>{isRemoving && itemPendingRemoval?.supply_request_item_id === item.supply_request_item_id ? 'Removing...' : 'Remove'}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                {/* Add New Item Section */}
                <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div>
                        <h4 className="text-lg font-bold text-slate-800">Add Item to Request</h4>
                        <p className="text-xs text-slate-500 mt-0.5">Select from approved PPMP items to attach to this supply request.</p>
                    </div>

                    {!hasPpmp ? (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-800">
                            Please select an approved PPMP in the request header first before adding items.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-12 items-end">
                                <div className="sm:col-span-6 lg:col-span-7">
                                    <label className="block">
                                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                            PPMP Item <span className="text-rose-500">*</span>
                                        </span>
                                        <select
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs sm:text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white"
                                            value={newItem.ppmp_item_id}
                                            onChange={(event) => onNewItemOptionChange(event.target.value)}
                                            disabled={!hasPpmp}
                                        >
                                            <option value="">-- Choose an approved item --</option>
                                            {filteredItemOptions.map((option) => {
                                                const specTag = option.item_specification ? ` — ${option.item_specification}` : '';
                                                const scheduleTag = option.procurement_schedule ? ` [${option.procurement_schedule}]` : '';
                                                return (
                                                    <option key={option.ppmp_item_id} value={option.ppmp_item_id}>
                                                        {option.item_name}{specTag}{scheduleTag} (remaining: {option.remaining_quantity})
                                                    </option>
                                                );
                                            })}
                                        </select>
                                        {filteredItemOptions.length === 0 && hasPpmp && form.data.item_category_id ? (
                                            <p className="mt-1 text-[11px] font-medium text-amber-700">No approved items found in this PPMP for the selected category.</p>
                                        ) : null}
                                    </label>
                                </div>

                                <div className="sm:col-span-3 lg:col-span-3">
                                    <label className="block">
                                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                            Quantity <span className="text-rose-500">*</span>
                                        </span>
                                        <div className="inline-flex w-full items-center rounded-lg border border-slate-300 bg-white shadow-2xs focus-within:border-red-500 focus-within:ring-1 focus-within:ring-red-500 overflow-hidden">
                                            <input
                                                className="w-full border-0 px-3 py-2 text-xs sm:text-sm font-semibold text-slate-800 focus:ring-0 focus:outline-hidden"
                                                value={newItem.quantity_requested}
                                                onChange={(event) => setNewItem({ ...newItem, quantity_requested: event.target.value })}
                                                type="number"
                                                step="any"
                                                min="0.01"
                                                placeholder="Qty"
                                            />
                                            <span className="bg-slate-50 border-l border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 min-w-[3.5rem] text-center select-none">
                                                {newItemOption?.default_unit || '-'}
                                            </span>
                                        </div>
                                    </label>
                                </div>

                                <div className="sm:col-span-3 lg:col-span-2">
                                    <button
                                        type="button"
                                        onClick={addItem}
                                        disabled={!hasPpmp || !newItem.ppmp_item_id || newItemIsDuplicate || Number(newItem.quantity_requested) <= 0}
                                        className="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-red-700 hover:bg-red-800 disabled:opacity-50 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-xs transition"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>Add Item</span>
                                    </button>
                                </div>
                            </div>

                            {/* Selected item preview card */}
                            {newItemOption ? (
                                <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-slate-800">{newItemOption.item_name}</span>
                                            {newItemOption.item_specification ? (
                                                <span className="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                                                    {newItemOption.item_specification}
                                                </span>
                                            ) : null}
                                        </div>
                                        {newItemOption.procurement_schedule ? (
                                            <span className="rounded-md bg-slate-200/80 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                                Schedule: {newItemOption.procurement_schedule}
                                            </span>
                                        ) : null}
                                    </div>

                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs pt-2 border-t border-slate-200/60">
                                        <div>
                                            <span className="text-slate-500">Planned:</span>{' '}
                                            <strong className="text-slate-800">{newItemOption.planned_quantity} {newItemOption.default_unit || ''}</strong>
                                        </div>
                                        <div>
                                            <span className="text-slate-500">Already requested:</span>{' '}
                                            <strong className="text-slate-800">{newItemOption.requested_quantity} {newItemOption.default_unit || ''}</strong>
                                        </div>
                                        <div>
                                            <span className="text-slate-500">Remaining:</span>{' '}
                                            <strong className="text-emerald-700">{newItemOption.remaining_quantity} {newItemOption.default_unit || ''}</strong>
                                        </div>
                                        <div>
                                            <span className="text-slate-500">Est. Unit Cost:</span>{' '}
                                            <strong className="text-slate-800">
                                                {newItemOption.suggested_unit_cost
                                                    ? `₱${Number(newItemOption.suggested_unit_cost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                    : '-'}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            ) : null}

                            {/* Warnings */}
                            {newItemIsDuplicate ? (
                                <div className="rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs text-rose-900 flex items-start gap-2">
                                    <svg className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p className="font-semibold">This PPMP item line is already included in this supply request.</p>
                                        <p className="mt-0.5">Please update the existing row quantity above instead of adding a duplicate line.</p>
                                    </div>
                                </div>
                            ) : null}

                            {newItemOption?.is_schedule_mismatch ? (
                                <div className="rounded-lg border border-orange-300 bg-orange-50 p-3 text-xs text-orange-900 flex items-start gap-2">
                                    <svg className="w-4 h-4 text-orange-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p className="font-semibold">Out-of-schedule PPMP consumption detected.</p>
                                        <p className="mt-0.5">
                                            This item is planned for {newItemOption.procurement_schedule || 'a later schedule'} but is being requested in {newItemOption.current_quarter || 'the current quarter'}. It can proceed to GSO review but will be flagged in Executive anomaly monitoring.
                                        </p>
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    )}
                </section>
            </div>

            <ConfirmationModal
                show={Boolean(itemPendingRemoval)}
                onClose={() => !isRemoving && setItemPendingRemoval(null)}
                processing={isRemoving}
                variant="danger"
                title="Remove Item from Supply Request"
                confirmText={isRemoving ? 'Removing...' : 'Remove Item'}
                cancelText="Cancel"
                message={
                    itemPendingRemoval ? (
                        <div className="space-y-3">
                            <p className="text-sm text-slate-600">
                                Are you sure you want to remove this item from the supply request?
                            </p>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700 space-y-2">
                                <div className="font-semibold text-slate-900 text-sm">
                                    {itemPendingRemoval.item_name_snapshot}
                                </div>
                                <div className="grid grid-cols-2 gap-2 pt-1.5 border-t border-slate-200 text-slate-600">
                                    <div>
                                        <span className="font-medium text-slate-500">Requested Qty: </span>
                                        <span className="font-semibold text-slate-800">{itemPendingRemoval.quantity_requested}</span>
                                    </div>
                                    {itemPendingRemoval.item_specification ? (
                                        <div className="col-span-2">
                                            <span className="font-medium text-slate-500">Specification: </span>
                                            <span className="text-slate-800">{itemPendingRemoval.item_specification}</span>
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                            <p className="text-xs text-rose-600 font-medium">
                                The allocated quantity will be released back to the PPMP balance.
                            </p>
                        </div>
                    ) : null
                }
                onConfirm={confirmRemoveItem}
            />
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    hint,
    required,
    error,
    children,
}: {
    label: string;
    hint?: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">
                {label} {required ? <span className="text-rose-500">*</span> : null}
            </span>
            {children}
            {error ? (
                <p className="mt-1 text-xs text-rose-600 font-medium">{error}</p>
            ) : hint ? (
                <p className="mt-1 text-xs text-slate-500">{hint}</p>
            ) : null}
        </label>
    );
}
