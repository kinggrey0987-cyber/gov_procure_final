import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import { FUND_SOURCES } from '@/constants/fundSources';
import { dateInputValue } from '@/lib/dateInput';

import {
    buildCatalogGroups,
    findGroupByItemId,
    findVariantByItemId,
    ItemOption,
} from '@/lib/catalogGrouping';

type ExistingItem = {
    ppmp_item_id: number;
    item_id: number;
    item_category_id: number;
    quantity: number;
    unit_cost: number;
    estimated_budget?: number;
    procurement_schedule: string;
    remarks?: string | null;
    item?: { item_name?: string | null; default_unit?: string | null; pieces_per_box?: number | null; sub_unit?: string | null } | null;
};

type PpmpItemDraft = {
    item_id: string | number;
    item_category_id: string | number;
    quantity: string | number;
    unit_cost: string | number;
    procurement_schedule: string;
    remarks?: string | null;
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
const CURRENT_YEAR = new Date().getFullYear();

export default function PpmpEdit({ ppmp, itemOptions }: Props) {
    const { flash, errors: pageErrors } = usePage().props as unknown as PageFlash;
    const [processingRowId, setProcessingRowId] = useState<number | null>(null);
    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['itemOptions'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const form = useForm({
        fiscal_year: ppmp.fiscal_year,
        date_prepared: dateInputValue(ppmp.date_prepared),
        fund_source: ppmp.fund_source,
        remarks: ppmp.remarks ?? '',
    });

    const [selectedNewGroupKey, setSelectedNewGroupKey] = useState<string>('');

    const newItem = useForm({
        item_id: '',
        item_category_id: '',
        quantity: '1',
        unit_cost: '',
        procurement_schedule: '',
        remarks: '',
    });

    const [editingRows, setEditingRows] = useState<Record<number, ExistingItem>>(() => {
        const map: Record<number, ExistingItem> = {};
        for (const item of ppmp.items) {
            const option = itemOptions.find((entry) => entry.item_id === item.item_id);
            const specs = option?.specifications || [];
            const remarks = (item.remarks && item.remarks.trim() !== '')
                ? item.remarks
                : (specs.length === 1 ? specs[0].specification : (item.remarks ?? ''));
            map[item.ppmp_item_id] = { ...item, remarks };
        }
        return map;
    });

    const submitHeader = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('office.ppmp.update', ppmp.ppmp_id));
    };

    const addItem = () => {
        if (!isPpmpItemComplete(newItem.data)) {
            return;
        }

        newItem.post(route('office.ppmp.items.store', ppmp.ppmp_id), {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                newItem.reset('item_id', 'item_category_id', 'quantity', 'unit_cost', 'procurement_schedule', 'remarks');
                newItem.setData('quantity', '1');
                newItem.setData('unit_cost', '');
                setSelectedNewGroupKey('');
            },
        });
    };

    const updateItem = (itemId: number) => {
        if (!isPpmpItemComplete(editingRows[itemId])) {
            return;
        }

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

    const catalogGroups = useMemo(() => buildCatalogGroups(itemOptions), [itemOptions]);
    const selectedNewGroup = useMemo(
        () => catalogGroups.find((g) => g.group_key === selectedNewGroupKey),
        [catalogGroups, selectedNewGroupKey]
    );

    const chooseNewGroup = (groupKey: string) => {
        setSelectedNewGroupKey(groupKey);
        const group = catalogGroups.find((g) => g.group_key === groupKey);
        if (!group) {
            newItem.setData((prev) => ({
                ...prev,
                item_id: '',
                item_category_id: '',
                remarks: '',
            }));
            return;
        }

        if (group.variants.length === 1) {
            const single = group.variants[0];
            newItem.setData((prev) => ({
                ...prev,
                item_id: String(single.item_id),
                item_category_id: String(single.item_category_id),
                remarks: single.specification,
            }));
        } else {
            newItem.setData((prev) => ({
                ...prev,
                item_id: '',
                item_category_id: String(group.item_category_id),
                remarks: '',
            }));
        }
    };

    const chooseNewVariant = (variantItemId: string) => {
        const variant = selectedNewGroup?.variants.find((v) => String(v.item_id) === variantItemId)
            || findVariantByItemId(catalogGroups, variantItemId);

        if (!variant) {
            newItem.setData((prev) => ({
                ...prev,
                item_id: '',
                remarks: '',
            }));
            return;
        }

        newItem.setData((prev) => ({
            ...prev,
            item_id: String(variant.item_id),
            item_category_id: String(variant.item_category_id),
            remarks: variant.specification,
        }));
    };

    const itemOptionById = useMemo(
        () => new Map(itemOptions.map((option) => [String(option.item_id), option])),
        [itemOptions]
    );
    const selectedNewItemUnit = itemOptionById.get(newItem.data.item_id)?.default_unit || '-';
    const newItemDuplicateMessage = useMemo(
        () => duplicateMessageForNewItem(newItem.data.item_id, newItem.data.procurement_schedule, newItem.data.remarks, ppmp.items, itemOptionById),
        [itemOptionById, newItem.data.item_id, newItem.data.procurement_schedule, newItem.data.remarks, ppmp.items],
    );

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
                            <button
                                type="button"
                                onClick={() => void refreshNow()}
                                disabled={isRefreshing}
                                className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition shadow-2xs"
                            >
                                {isRefreshing ? 'Refreshing…' : 'Refresh Options'}
                            </button>
                            <Link href={route('office.ppmp.show', ppmp.ppmp_id)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to Details</Link>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Fiscal Year</span>
                            <input type="number" min={CURRENT_YEAR} className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" value={form.data.fiscal_year} readOnly aria-readonly="true" />
                            <span className="mt-1 block text-xs text-slate-500">Fiscal year is locked after PPMP creation.</span>
                            {Number(form.data.fiscal_year) > CURRENT_YEAR && (
                                <div className="mt-2 flex items-start gap-2 rounded-lg border border-purple-200 bg-purple-50/80 p-2.5 text-xs text-purple-800">
                                    <span className="text-sm leading-none">📘</span>
                                    <div>
                                        <strong className="font-semibold">Projected PPMP (FY {form.data.fiscal_year}):</strong> This plan is a forward-year budget projection. You can modify planned items, but it cannot be submitted for executive approval until Fiscal Year {form.data.fiscal_year} begins.
                                    </div>
                                </div>
                            )}
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Date Prepared</span>
                            <input type="date" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" value={form.data.date_prepared} readOnly aria-readonly="true" />
                            <span className="mt-1 block text-xs text-slate-500">Date prepared is system-recorded and cannot be edited.</span>
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
                                const selectedOption = itemOptionById.get(String(row.item_id));
                                const specs = selectedOption?.specifications || [];
                                const piecesPerBox = row.item?.pieces_per_box ?? selectedOption?.pieces_per_box ?? 1;
                                const subUnit = row.item?.sub_unit ?? selectedOption?.sub_unit ?? 'piece';
                                const qtyNum = Number(row.quantity || 0);
                                const unitCostNum = Number(row.unit_cost || 0);
                                const totalPieces = qtyNum * piecesPerBox;
                                const estimatedBudget = qtyNum * unitCostNum;
                                const costPerPiece = piecesPerBox > 0 ? unitCostNum / piecesPerBox : unitCostNum;
                                const unit = row.item?.default_unit || selectedOption?.default_unit || 'box';
                                const isRowComplete = isPpmpItemComplete(row);

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
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Quantity ({unit})</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                    value={row.quantity}
                                                    onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, quantity: Number(event.target.value) } })}
                                                    required
                                                />
                                                <div className="mt-1 text-xs text-slate-600">
                                                    <span>
                                                        {qtyNum} {unit}
                                                        {piecesPerBox > 1 ? (
                                                            <span className="text-indigo-600 font-semibold ml-1">
                                                                = {totalPieces.toLocaleString()} total {subUnit}s
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </div>
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Packaging Unit</span>
                                                <input
                                                    type="text"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm"
                                                    value={unit + (piecesPerBox > 1 ? ` (${piecesPerBox} ${subUnit}s/${unit})` : '')}
                                                    readOnly
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Unit Cost (₱/{unit})</span>
                                                <input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                    value={row.unit_cost}
                                                    onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, unit_cost: Number(event.target.value) } })}
                                                    required
                                                />
                                                {unitCostNum > 0 && piecesPerBox > 1 ? (
                                                    <span className="mt-1 block text-xs text-emerald-700 font-medium">
                                                        ≈ ₱{costPerPiece.toFixed(2)}/{subUnit}
                                                    </span>
                                                ) : null}
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Estimated Budget</span>
                                                <input
                                                    type="text"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800"
                                                    value={`₱${estimatedBudget.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
                                                    readOnly
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Procurement Schedule</span>
                                                <select
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                    value={row.procurement_schedule}
                                                    onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, procurement_schedule: event.target.value } })}
                                                    required
                                                >
                                                    {scheduleOptionsForValue(row.procurement_schedule).map((option) => (
                                                        <option key={option.value} value={option.value}>{option.label}</option>
                                                    ))}
                                                </select>
                                            </label>

                                            <label className="block xl:col-span-2">
                                                <div className="flex items-center justify-between mb-1">
                                                    <span className="block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                        Item Specification <span className="text-rose-600">*</span>
                                                    </span>
                                                    {specs.length === 1 ? (
                                                        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200">
                                                            <svg className="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                            </svg>
                                                            Standard Catalog Specification (Locked)
                                                        </span>
                                                    ) : specs.length > 1 ? (
                                                        <span className="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">
                                                            {specs.length} GSO Approved Options
                                                        </span>
                                                    ) : null}
                                                </div>
                                                {specs.length > 1 ? (
                                                    <select
                                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                        value={row.remarks ?? ''}
                                                        onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, remarks: event.target.value } })}
                                                        required
                                                    >
                                                        <option value="">Select standard specification...</option>
                                                        {specs.map((s) => (
                                                            <option key={s.item_specification_id} value={s.specification}>
                                                                {s.specification}
                                                            </option>
                                                        ))}
                                                        {row.remarks && !specs.some((s) => s.specification.toLowerCase() === row.remarks?.trim().toLowerCase()) ? (
                                                            <option value={row.remarks}>{row.remarks} (custom)</option>
                                                        ) : null}
                                                    </select>
                                                ) : specs.length === 1 ? (
                                                    <input
                                                        type="text"
                                                        className="w-full rounded-lg border border-slate-300 bg-slate-100/70 px-3 py-2 text-sm text-slate-800 font-medium cursor-not-allowed"
                                                        value={specs[0].specification}
                                                        readOnly
                                                        aria-readonly="true"
                                                        required
                                                    />
                                                ) : (
                                                    <input
                                                        type="text"
                                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                        value={row.remarks ?? ''}
                                                        onChange={(event) => setEditingRows({ ...editingRows, [item.ppmp_item_id]: { ...row, remarks: event.target.value } })}
                                                        placeholder="Required item specification for SR and PO"
                                                        required
                                                    />
                                                )}
                                                <span className="mt-1 block text-xs text-slate-500">This specification flows to Supply Requests and Purchase Orders.</span>
                                            </label>
                                        </div>

                                        <div className="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-3">
                                            <span className="mr-auto text-xs font-medium text-slate-500">Line Actions</span>
                                            <button
                                                type="button"
                                                onClick={() => updateItem(item.ppmp_item_id)}
                                                disabled={processingRowId === item.ppmp_item_id || !isRowComplete}
                                                title={!isRowComplete ? 'Complete quantity, unit cost, procurement schedule, and item specification first.' : undefined}
                                                className="rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
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
                                        {!isRowComplete ? (
                                            <p className="mt-2 text-right text-xs font-medium text-rose-700">
                                                Complete all required line fields before updating this PPMP item.
                                            </p>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-6 shadow-sm">
                    <div>
                        <h4 className="text-xl font-semibold text-slate-800">Add New Item</h4>
                        <p className="mt-1 text-sm text-slate-600">Add a planning line to this PPMP using the required fields. The same catalog item cannot be repeated for the same procurement quarter.</p>
                    </div>
                    {(() => {
                        const selectedNewOpt = itemOptionById.get(String(newItem.data.item_id))
                            || (selectedNewGroup?.variants[0] ? itemOptionById.get(String(selectedNewGroup.variants[0].item_id)) : undefined);
                        const newUnit = selectedNewOpt?.default_unit || selectedNewGroup?.default_unit || 'box';
                        const newPieces = selectedNewOpt?.pieces_per_box || selectedNewGroup?.pieces_per_box || 1;
                        const newSubUnit = selectedNewOpt?.sub_unit || selectedNewGroup?.sub_unit || 'piece';
                        const newQtyNum = Number(newItem.data.quantity || 0);
                        const newCostNum = Number(newItem.data.unit_cost || 0);
                        const newTotalPieces = newQtyNum * newPieces;
                        const newSubtotal = newQtyNum * newCostNum;
                        const newCostPerPiece = newPieces > 0 ? newCostNum / newPieces : newCostNum;

                        return (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                                <label className="block xl:col-span-2">
                                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Item</span>
                                    <select
                                        className={`w-full rounded-lg border px-3 py-2 text-sm ${newItemDuplicateMessage ? 'border-amber-300 bg-amber-50' : 'border-slate-300'}`}
                                        value={selectedNewGroupKey}
                                        onChange={(event) => chooseNewGroup(event.target.value)}
                                        required
                                    >
                                        <option value="">Select Item</option>
                                        {catalogGroups.map((group) => (
                                            <option key={group.group_key} value={group.group_key}>
                                                {group.base_name} {group.pieces_per_box > 1 ? `(${group.pieces_per_box} ${group.sub_unit}s/${group.default_unit})` : `(${group.default_unit})`}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={newItem.errors.item_id} className="mt-1" />
                                </label>

                                <label className="block">
                                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Quantity ({newUnit})</span>
                                    <input type="number" min="1" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.data.quantity} onChange={(event) => newItem.setData('quantity', event.target.value)} required />
                                    <div className="mt-1 text-xs text-slate-600">
                                        <span>
                                            {newQtyNum} {newUnit}
                                            {newPieces > 1 ? (
                                                <span className="text-indigo-600 font-semibold ml-1">
                                                    = {newTotalPieces.toLocaleString()} total {newSubUnit}s
                                                </span>
                                            ) : null}
                                        </span>
                                    </div>
                                    <InputError message={newItem.errors.quantity} className="mt-1" />
                                </label>

                                <label className="block">
                                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Packaging Unit</span>
                                    <input type="text" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm" value={newUnit + (newPieces > 1 ? ` (${newPieces} ${newSubUnit}s/${newUnit})` : '')} readOnly />
                                </label>

                                <label className="block">
                                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Unit Cost (₱/{newUnit})</span>
                                    <input type="number" min="0.01" step="0.01" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={newItem.data.unit_cost} onChange={(event) => newItem.setData('unit_cost', event.target.value)} required />
                                    <div className="mt-1 flex flex-wrap items-center justify-between text-xs text-slate-600">
                                        {newCostNum > 0 && newPieces > 1 ? (
                                            <span className="text-emerald-700 font-medium">
                                                ≈ ₱{newCostPerPiece.toFixed(2)}/{newSubUnit}
                                            </span>
                                        ) : <span />}
                                        {newSubtotal > 0 ? (
                                            <span className="font-semibold text-slate-800">
                                                ₱{newSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </span>
                                        ) : null}
                                    </div>
                                    <InputError message={newItem.errors.unit_cost} className="mt-1" />
                                </label>

                                <label className="block">
                                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Procurement Schedule</span>
                                    <select className={`w-full rounded-lg border px-3 py-2 text-sm ${newItemDuplicateMessage ? 'border-amber-300 bg-amber-50' : 'border-slate-300'}`} value={newItem.data.procurement_schedule} onChange={(event) => newItem.setData('procurement_schedule', event.target.value)} required>
                                        <option value="">Select Quarter</option>
                                        {QUARTER_OPTIONS.map((quarter) => (
                                            <option key={quarter} value={quarter}>{quarter}</option>
                                        ))}
                                    </select>
                                    <InputError message={newItem.errors.procurement_schedule} className="mt-1" />
                                </label>

                                <label className="block xl:col-span-2">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            Standard Specification / Technical Details <span className="text-rose-600">*</span>
                                        </span>
                                        {selectedNewGroup && selectedNewGroup.variants.length > 1 ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">
                                                {selectedNewGroup.variants.length} Approved Specifications Available
                                            </span>
                                        ) : selectedNewGroup && selectedNewGroup.variants.length === 1 ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200">
                                                <svg className="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                Standard Catalog Specification (Locked)
                                            </span>
                                        ) : null}
                                    </div>
                                    {selectedNewGroup && selectedNewGroup.variants.length > 1 ? (
                                        <select
                                            className={`w-full rounded-lg border px-3 py-2 text-sm ${newItem.errors.remarks || newItem.errors.item_id ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'}`}
                                            value={newItem.data.item_id}
                                            onChange={(event) => chooseNewVariant(event.target.value)}
                                            required
                                        >
                                            <option value="">Select standard specification (A4, Letter, Legal...)</option>
                                            {selectedNewGroup.variants.map((v) => (
                                                <option key={v.item_id} value={v.item_id}>
                                                    {v.specification}
                                                </option>
                                            ))}
                                        </select>
                                    ) : selectedNewGroup && selectedNewGroup.variants.length === 1 ? (
                                        <input
                                            type="text"
                                            className="w-full rounded-lg border border-slate-300 bg-slate-100/70 px-3 py-2 text-sm text-slate-800 font-medium cursor-not-allowed"
                                            value={newItem.data.remarks || selectedNewGroup.variants[0].specification}
                                            readOnly
                                            aria-readonly="true"
                                            required
                                        />
                                    ) : (
                                        <input
                                            type="text"
                                            className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-400 cursor-not-allowed"
                                            placeholder="Select an item above to choose specifications"
                                            disabled
                                        />
                                    )}
                                    <span className="mt-1 block text-xs text-slate-500">This becomes the item specification shown on Supply Requests and auto-filled on Purchase Orders.</span>
                                    <InputError message={newItem.errors.remarks} className="mt-1" />
                                </label>
                            </div>
                        );
                    })()}

                    <InputError message={newItem.errors.item_category_id} className="mt-1" />

                    {newItemDuplicateMessage ? (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                            {newItemDuplicateMessage}
                        </div>
                    ) : null}

                    <div className="flex justify-end">
                        <button type="button" onClick={addItem} disabled={newItem.processing || Boolean(newItemDuplicateMessage) || !isPpmpItemComplete(newItem.data)} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60">Add Item</button>
                    </div>
                    {!isPpmpItemComplete(newItem.data) ? (
                        <p className="text-right text-xs font-medium text-rose-700">
                            Complete all required item fields before adding this PPMP line.
                        </p>
                    ) : null}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function isPpmpItemComplete(item: Partial<PpmpItemDraft> | Partial<ExistingItem>): boolean {
    return Boolean(
        String(item.item_id ?? '').trim() !== ''
        && String(item.item_category_id ?? '').trim() !== ''
        && Number(item.quantity) > 0
        && String(item.unit_cost ?? '').trim() !== ''
        && Number(item.unit_cost) > 0
        && QUARTER_OPTIONS.includes(String(item.procurement_schedule ?? '').trim().toUpperCase() as (typeof QUARTER_OPTIONS)[number])
        && String(item.remarks ?? '').trim() !== '',
    );
}

function duplicateMessageForNewItem(
    itemId: string,
    procurementSchedule: string,
    remarks: string,
    existingItems: ExistingItem[],
    itemOptionById: Map<string, ItemOption>,
): string | null {
    const normalizedItemId = itemId.trim();
    const normalizedSchedule = procurementSchedule.trim().toUpperCase();
    const normalizedRemarks = remarks.trim().toLowerCase();

    if (normalizedItemId === '' || normalizedSchedule === '' || normalizedRemarks === '') {
        return null;
    }

    const exists = existingItems.some(
        (item) =>
            String(item.item_id) === normalizedItemId
            && item.procurement_schedule.trim().toUpperCase() === normalizedSchedule
            && (item.remarks ?? '').trim().toLowerCase() === normalizedRemarks,
    );

    if (!exists) {
        return null;
    }

    const itemName = itemOptionById.get(normalizedItemId)?.item_name ?? `Item #${normalizedItemId}`;

    return `${itemName} with specification "${remarks.trim()}" is already planned for ${normalizedSchedule}. Update the existing line instead of adding a duplicate.`;
}

function scheduleOptionsForValue(value: string): Array<{ value: string; label: string }> {
    const normalized = value.trim().toUpperCase();
    const options = QUARTER_OPTIONS.map((quarter) => ({ value: quarter, label: quarter }));

    if (normalized === '' || QUARTER_OPTIONS.includes(normalized as (typeof QUARTER_OPTIONS)[number])) {
        return [{ value: '', label: 'Select Quarter' }, ...options];
    }

    return [{ value: normalized, label: `${normalized} (legacy value)` }, ...options];
}
