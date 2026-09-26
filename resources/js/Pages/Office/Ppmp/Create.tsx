import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';
import { FUND_SOURCES, DEFAULT_FUND_SOURCE } from '@/constants/fundSources';
import { todayInputValue } from '@/lib/dateInput';
import {
    buildCatalogGroups,
    findGroupByItemId,
    findVariantByItemId,
    ItemOption,
} from '@/lib/catalogGrouping';

type ItemDraft = {
    item_id: string;
    item_category_id: string;
    quantity: string;
    unit_cost: string;
    procurement_schedule: string;
    remarks: string;
    selected_group_key?: string;
};

type Props = {
    itemOptions: ItemOption[];
};

const QUARTER_OPTIONS = ['Q1', 'Q2', 'Q3', 'Q4'] as const;
const CURRENT_YEAR = new Date().getFullYear();
const TODAY = todayInputValue();

export default function PpmpCreate({ itemOptions }: Props) {
    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['itemOptions'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const catalogGroups = useMemo(() => buildCatalogGroups(itemOptions), [itemOptions]);

    const form = useForm({
        fiscal_year: CURRENT_YEAR,
        date_prepared: TODAY,
        fund_source: DEFAULT_FUND_SOURCE,
        remarks: '',
        items: [emptyItem()],
    });

    const duplicateRowIndexes = useMemo(() => duplicateIndexesForDraftItems(form.data.items), [form.data.items]);
    const hasDuplicateItems = duplicateRowIndexes.size > 0;

    const totalEstimatedBudget = useMemo(() => {
        return form.data.items.reduce((sum, item) => {
            const q = Number(item.quantity) || 0;
            const c = Number(item.unit_cost) || 0;
            return sum + (q * c);
        }, 0);
    }, [form.data.items]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (hasDuplicateItems) {
            return;
        }

        form.post(route('office.ppmp.store'));
    };

    const addItem = () => form.setData('items', [...form.data.items, emptyItem()]);
    const removeItem = (index: number) => {
        if (form.data.items.length === 1) {
            return;
        }
        form.setData('items', form.data.items.filter((_, i) => i !== index));
    };
    const patchItem = (index: number, patch: Partial<ItemDraft>) => {
        form.setData('items', form.data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    };

    const chooseGroup = (index: number, groupKey: string) => {
        const group = catalogGroups.find((g) => g.group_key === groupKey);
        if (!group) {
            patchItem(index, {
                selected_group_key: '',
                item_id: '',
                item_category_id: '',
                remarks: '',
            });
            return;
        }

        if (group.variants.length === 1) {
            const single = group.variants[0];
            patchItem(index, {
                selected_group_key: group.group_key,
                item_id: String(single.item_id),
                item_category_id: String(single.item_category_id),
                remarks: single.specification,
            });
        } else {
            // Multi-variant product (e.g. Bond Paper with A4, Letter, Legal)
            patchItem(index, {
                selected_group_key: group.group_key,
                item_id: '',
                item_category_id: String(group.item_category_id),
                remarks: '',
            });
        }
    };

    const chooseVariant = (index: number, variantItemId: string) => {
        const groupKey = form.data.items[index]?.selected_group_key;
        const group = groupKey ? catalogGroups.find((g) => g.group_key === groupKey) : undefined;
        const variant = group?.variants.find((v) => String(v.item_id) === variantItemId)
            || findVariantByItemId(catalogGroups, variantItemId);

        if (!variant) {
            patchItem(index, { item_id: '', remarks: '' });
            return;
        }

        patchItem(index, {
            item_id: String(variant.item_id),
            item_category_id: String(variant.item_category_id),
            remarks: variant.specification,
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create PPMP</h2>}>
            <Head title="Create PPMP" />

            <form onSubmit={submit} className="lg:h-[calc(100vh-8rem)] flex flex-col justify-between py-1 space-y-2 overflow-y-auto lg:overflow-hidden">
                {Object.keys(form.errors).length > 0 ? (
                    <div className="shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="font-semibold">Unable to save PPMP. </span>
                            <span>Please review highlighted fields and items ({Object.keys(form.errors).length} error{Object.keys(form.errors).length > 1 ? 's' : ''}).</span>
                        </div>
                    </div>
                ) : null}

                {/* Card 1: PPMP Details (Matches User Manual Screenshot) */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-3 sm:p-3.5 shadow-xs">
                    <div className="mb-2 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <h3 className="text-base font-bold text-slate-800">PPMP Details</h3>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => void refreshNow()}
                                disabled={isRefreshing}
                                className="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition shadow-2xs"
                            >
                                {isRefreshing ? 'Refreshing…' : 'Refresh Options'}
                            </button>
                            <Link
                                href={route('office.ppmp.index')}
                                className="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Back to PPMP
                            </Link>
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Fiscal Year</span>
                            <input
                                type="number"
                                min={CURRENT_YEAR}
                                className={`w-full rounded-lg border px-2.5 py-1 text-xs font-medium ${form.errors.fiscal_year ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'}`}
                                value={form.data.fiscal_year}
                                onChange={(event) => form.setData('fiscal_year', Number(event.target.value))}
                                required
                            />
                            <span className="mt-0.5 block text-[10px] text-slate-500">Fiscal year cannot be earlier than {CURRENT_YEAR}.</span>
                            {Number(form.data.fiscal_year) > CURRENT_YEAR && (
                                <div className="mt-1.5 flex items-start gap-1.5 rounded-lg border border-purple-200 bg-purple-50/80 p-2 text-[11px] text-purple-800">
                                    <span className="text-sm leading-none">📘</span>
                                    <div>
                                        <strong className="font-semibold">Projected PPMP (FY {form.data.fiscal_year}):</strong> This plan is for a future fiscal year. It will be saved as a draft projection for forward planning and cannot be submitted for executive review until Fiscal Year {form.data.fiscal_year} starts.
                                    </div>
                                </div>
                            )}
                            <InputError message={form.errors.fiscal_year} className="mt-0.5 text-[10px]" />
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Date Prepared</span>
                            <input
                                type="date"
                                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs text-slate-700 font-medium"
                                value={form.data.date_prepared}
                                readOnly
                                aria-readonly="true"
                                required
                            />
                            <span className="mt-0.5 block text-[10px] text-slate-500">Automatically set to today and locked for audit consistency.</span>
                            <InputError message={form.errors.date_prepared} className="mt-0.5 text-[10px]" />
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Fund Source</span>
                            <select
                                value={form.data.fund_source}
                                onChange={(event) => form.setData('fund_source', event.target.value)}
                                className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors.fund_source ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'}`}
                                required
                            >
                                {FUND_SOURCES.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                            <InputError message={form.errors.fund_source} className="mt-0.5 text-[10px]" />
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Remarks <span className="text-slate-400 font-normal">(Optional)</span></span>
                            <input
                                type="text"
                                placeholder="Additional context or remarks..."
                                className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors.remarks ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'}`}
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                            />
                            <InputError message={form.errors.remarks} className="mt-0.5 text-[10px]" />
                        </label>
                    </div>
                </section>

                {/* Card 2: PPMP Items (Matches User Manual Screenshot) */}
                <section className="flex-1 flex flex-col min-h-0 rounded-xl border border-slate-200 bg-white p-3 sm:p-3.5 shadow-xs">
                    <div className="mb-2 flex items-center justify-between shrink-0">
                        <div className="flex items-center gap-2">
                            <h4 className="text-base font-bold text-slate-800">PPMP Items</h4>
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                {form.data.items.length} {form.data.items.length === 1 ? 'row' : 'rows'}
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={addItem}
                            className="inline-flex items-center gap-1 rounded-lg border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 transition shadow-xs"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Add Item Row</span>
                        </button>
                    </div>

                    {hasDuplicateItems ? (
                        <div className="mb-2 shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 flex items-center gap-2">
                            <svg className="w-4 h-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Duplicate PPMP item lines are not allowed. Items with the same specification cannot be planned multiple times in the same quarter; adjust the quantity on the existing row instead.</span>
                        </div>
                    ) : null}

                    {/* Scrollable Items Container (Page itself does not scroll; only rows scroll inside) */}
                    <div className="flex-1 overflow-y-auto pr-1 space-y-2 min-h-0">
                        {form.data.items.map((item, index) => {
                            const selectedVariant = item.item_id ? findVariantByItemId(catalogGroups, item.item_id) : undefined;
                            const selectedGroup = selectedVariant
                                ? findGroupByItemId(catalogGroups, item.item_id)
                                : (item.selected_group_key ? catalogGroups.find((g) => g.group_key === item.selected_group_key) : undefined);

                            const currentGroupKey = selectedGroup?.group_key || '';
                            const unit = selectedVariant?.default_unit || selectedGroup?.default_unit || 'box';
                            const piecesPerBox = selectedVariant?.pieces_per_box || selectedGroup?.pieces_per_box || 1;
                            const subUnit = selectedVariant?.sub_unit || selectedGroup?.sub_unit || 'piece';
                            const qtyNum = Number(item.quantity) || 0;
                            const unitCostNum = Number(item.unit_cost) || 0;
                            const totalPieces = qtyNum * piecesPerBox;
                            const lineSubtotal = qtyNum * unitCostNum;
                            const costPerPiece = piecesPerBox > 0 ? unitCostNum / piecesPerBox : unitCostNum;
                            const isDuplicate = duplicateRowIndexes.has(index);
                            const hasRowError = Object.keys(form.errors).some((key) => key.startsWith(`items.${index}.`));

                            return (
                                <div
                                    key={index}
                                    className={`rounded-xl border p-2.5 transition ${isDuplicate
                                            ? 'border-amber-300 bg-amber-50/40'
                                            : hasRowError
                                                ? 'border-rose-300 bg-rose-50/20 ring-1 ring-rose-200'
                                                : 'border-slate-200 bg-slate-50/40'
                                        }`}
                                >
                                    <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-200">
                                        <div className="flex items-center gap-2">
                                            <span className="inline-flex items-center justify-center h-5 px-2 rounded-full text-[11px] font-bold bg-slate-200 text-slate-700">
                                                Row #{index + 1}
                                            </span>
                                            {lineSubtotal > 0 ? (
                                                <span className="text-xs font-semibold text-emerald-700">
                                                    Subtotal: ₱{lineSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                </span>
                                            ) : null}
                                            {isDuplicate ? (
                                                <span className="text-[11px] font-medium text-amber-700">
                                                    (Duplicate allocation for {item.procurement_schedule || 'this quarter'})
                                                </span>
                                            ) : null}
                                        </div>

                                        {form.data.items.length > 1 ? (
                                            <button
                                                type="button"
                                                onClick={() => removeItem(index)}
                                                className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition"
                                            >
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                <span>Remove Row</span>
                                            </button>
                                        ) : null}
                                    </div>

                                    <div className="grid gap-2">
                                        {/* Catalog Item */}
                                        <label className="block">
                                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Catalog Item</span>
                                            <select
                                                value={currentGroupKey}
                                                onChange={(event) => chooseGroup(index, event.target.value)}
                                                className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors[`items.${index}.item_id`] || form.errors[`items.${index}.item_category_id`]
                                                        ? 'border-rose-400 bg-rose-50/30'
                                                        : 'border-slate-300'
                                                    }`}
                                                required
                                            >
                                                <option value="">Select Item</option>
                                                {catalogGroups.map((group) => (
                                                    <option key={group.group_key} value={group.group_key}>
                                                        {group.base_name} {group.pieces_per_box > 1 ? `(${group.pieces_per_box} ${group.sub_unit}s/${group.default_unit})` : `(${group.default_unit})`}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={form.errors[`items.${index}.item_id`]} className="mt-0.5 text-[10px]" />
                                            <InputError message={form.errors[`items.${index}.item_category_id`]} className="mt-0.5 text-[10px]" />
                                        </label>

                                        {/* Quantity, Packaging, Unit Cost, Schedule in a 4-col responsive grid */}
                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                            <label className="block">
                                                <span className="mb-0.5 block text-xs font-medium text-slate-700">Quantity ({unit.toUpperCase()})</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    value={item.quantity}
                                                    onChange={(event) => patchItem(index, { quantity: event.target.value })}
                                                    className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors[`items.${index}.quantity`]
                                                            ? 'border-rose-400 bg-rose-50/30'
                                                            : 'border-slate-300'
                                                        }`}
                                                    required
                                                />
                                                <div className="mt-0.5 text-[10px] text-slate-600 truncate" title={`Planned: ${qtyNum} ${unit}${piecesPerBox > 1 ? ` = ${totalPieces} ${subUnit}s` : ''}`}>
                                                    Planned: <strong className="text-slate-800">{qtyNum} {unit}</strong>
                                                    {piecesPerBox > 1 ? <span className="text-indigo-600 ml-1">({totalPieces.toLocaleString()} {subUnit}s)</span> : null}
                                                </div>
                                                <InputError message={form.errors[`items.${index}.quantity`]} className="mt-0.5 text-[10px]" />
                                            </label>

                                            <label className="block">
                                                <span className="mb-0.5 block text-xs font-medium text-slate-700">Packaging Unit</span>
                                                <input
                                                    value={unit + (piecesPerBox > 1 ? ` (${piecesPerBox} ${subUnit}s/${unit})` : '')}
                                                    readOnly
                                                    aria-readonly="true"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-100/70 px-2.5 py-1 text-xs text-slate-700"
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="mb-0.5 block text-xs font-medium text-slate-700">Est. Unit Cost (₱/{unit})</span>
                                                <input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    value={item.unit_cost}
                                                    onChange={(event) => patchItem(index, { unit_cost: event.target.value })}
                                                    className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors[`items.${index}.unit_cost`]
                                                            ? 'border-rose-400 bg-rose-50/30'
                                                            : 'border-slate-300'
                                                        }`}
                                                    required
                                                />
                                                {unitCostNum > 0 && piecesPerBox > 1 ? (
                                                    <div className="mt-0.5 text-[10px] text-emerald-700 font-medium">
                                                        ≈ ₱{costPerPiece.toFixed(2)}/{subUnit}
                                                    </div>
                                                ) : null}
                                                <InputError message={form.errors[`items.${index}.unit_cost`]} className="mt-0.5 text-[10px]" />
                                            </label>

                                            <label className="block">
                                                <span className="mb-0.5 block text-xs font-medium text-slate-700">Procurement Schedule</span>
                                                <select
                                                    value={item.procurement_schedule}
                                                    onChange={(event) => patchItem(index, { procurement_schedule: event.target.value })}
                                                    className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors[`items.${index}.procurement_schedule`]
                                                            ? 'border-rose-400 bg-rose-50/30'
                                                            : 'border-slate-300'
                                                        }`}
                                                    required
                                                >
                                                    <option value="">Select Quarter</option>
                                                    {QUARTER_OPTIONS.map((quarter) => (
                                                        <option key={quarter} value={quarter}>{quarter}</option>
                                                    ))}
                                                </select>
                                                <InputError message={form.errors[`items.${index}.procurement_schedule`]} className="mt-0.5 text-[10px]" />
                                            </label>
                                        </div>

                                        {/* Standard Specification / Technical Details */}
                                        <label className="block">
                                            <div className="flex items-center justify-between mb-0.5">
                                                <span className="block text-xs font-medium text-slate-700">
                                                    Standard Specification / Technical Details <span className="text-rose-600">*</span>
                                                </span>
                                                {selectedGroup && selectedGroup.variants.length > 1 ? (
                                                    <span className="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">
                                                        {selectedGroup.variants.length} Approved Specifications Available
                                                    </span>
                                                ) : selectedGroup && selectedGroup.variants.length === 1 ? (
                                                    <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200">
                                                        <svg className="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                        </svg>
                                                        Standard Catalog Specification (Locked)
                                                    </span>
                                                ) : null}
                                            </div>

                                            {selectedGroup && selectedGroup.variants.length > 1 ? (
                                                <select
                                                    value={item.item_id}
                                                    onChange={(event) => chooseVariant(index, event.target.value)}
                                                    className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors[`items.${index}.remarks`] || form.errors[`items.${index}.item_id`]
                                                            ? 'border-rose-400 bg-rose-50/30'
                                                            : 'border-slate-300'
                                                        }`}
                                                    required
                                                >
                                                    <option value="">Select standard specification</option>
                                                    {selectedGroup.variants.map((v) => (
                                                        <option key={v.item_id} value={v.item_id}>
                                                            {v.specification}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : selectedGroup && selectedGroup.variants.length === 1 ? (
                                                <input
                                                    value={item.remarks || selectedGroup.variants[0].specification}
                                                    readOnly
                                                    aria-readonly="true"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-100/70 px-2.5 py-1 text-xs text-slate-800 font-medium cursor-not-allowed"
                                                    required
                                                />
                                            ) : (
                                                <input
                                                    value={item.remarks}
                                                    readOnly
                                                    placeholder="Select a catalog item above first..."
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-400 cursor-not-allowed"
                                                    required
                                                />
                                            )}
                                            <span className="mt-0.5 block text-[10px] text-slate-500">
                                                {selectedGroup && selectedGroup.variants.length > 1
                                                    ? 'Select from GSO pre-approved standardized specifications for this item.'
                                                    : 'Pre-approved GSO standard technical specification auto-filled into Supply Requests and Purchase Orders.'}
                                            </span>
                                            <InputError message={form.errors[`items.${index}.remarks`]} className="mt-0.5 text-[10px]" />
                                        </label>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Bottom Action Strip inside Card 2 */}
                    <div className="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between shrink-0">
                        <div className="text-xs text-slate-600">
                            <span>Estimated PPMP Total: </span>
                            <strong className="text-sm font-bold text-emerald-700">
                                ₱{totalEstimatedBudget.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </strong>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                disabled={form.processing || hasDuplicateItems}
                                className="rounded-lg bg-red-600 px-5 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 transition shadow-xs"
                            >
                                {form.processing ? 'Saving...' : 'Save PPMP'}
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        </AuthenticatedLayout>
    );
}

function duplicateIndexesForDraftItems(items: ItemDraft[]): Set<number> {
    const firstIndexByKey = new Map<string, number>();
    const duplicates = new Set<number>();

    items.forEach((item, index) => {
        const itemId = item.item_id.trim();
        const spec = item.remarks.trim().toUpperCase();
        const schedule = item.procurement_schedule.trim().toUpperCase();

        if (itemId === '' || schedule === '') {
            return;
        }

        const key = `${itemId}|${spec}|${schedule}`;
        const firstIndex = firstIndexByKey.get(key);

        if (firstIndex !== undefined) {
            duplicates.add(firstIndex);
            duplicates.add(index);
            return;
        }

        firstIndexByKey.set(key, index);
    });

    return duplicates;
}

function emptyItem(): ItemDraft {
    return {
        item_id: '',
        item_category_id: '',
        quantity: '1',
        unit_cost: '',
        procurement_schedule: '',
        remarks: '',
    };
}
