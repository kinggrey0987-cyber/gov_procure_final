import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';
import { FUND_SOURCES, DEFAULT_FUND_SOURCE } from '@/constants/fundSources';
import { todayInputValue } from '@/lib/dateInput';

type ItemOption = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    item_category_name: string;
    default_unit?: string | null;
};

type ItemDraft = {
    item_id: string;
    item_category_id: string;
    quantity: string;
    unit_cost: string;
    procurement_schedule: string;
    remarks: string;
};

type Props = {
    itemOptions: ItemOption[];
};

const QUARTER_OPTIONS = ['Q1', 'Q2', 'Q3', 'Q4'] as const;
const CURRENT_YEAR = new Date().getFullYear();
const TODAY = todayInputValue();

export default function PpmpCreate({ itemOptions }: Props) {
    const { isRefreshing } = useSmartRefresh({
        only: ['itemOptions'],
        refreshOnFocus: true,
        refreshOnMount: true,
    });

    const form = useForm({
        fiscal_year: CURRENT_YEAR,
        date_prepared: TODAY,
        fund_source: DEFAULT_FUND_SOURCE,
        remarks: '',
        items: [emptyItem()],
    });

    const duplicateRowIndexes = useMemo(() => duplicateIndexesForDraftItems(form.data.items), [form.data.items]);
    const hasDuplicateItems = duplicateRowIndexes.size > 0;

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
    const chooseItem = (index: number, itemId: string) => {
        const option = itemOptions.find((entry) => String(entry.item_id) === itemId);
        if (!option) {
            patchItem(index, { item_id: '', item_category_id: '' });
            return;
        }
        patchItem(index, { item_id: String(option.item_id), item_category_id: String(option.item_category_id) });
    };

    const itemOptionById = new Map(itemOptions.map((option) => [String(option.item_id), option]));

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create PPMP</h2>}>
            <Head title="Create PPMP" />

            <form onSubmit={submit} className="space-y-6 py-4">
                {Object.keys(form.errors).length > 0 ? (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm">
                        <p className="font-semibold">Unable to save PPMP.</p>
                        <p className="mt-1">Please complete all required fields and review the highlighted item rows.</p>
                    </div>
                ) : null}

                <section className="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm">
                    <div className="flex items-start gap-3">
                        <div className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" /></svg>
                        </div>
                        <div>
                            <h4 className="text-sm font-bold text-indigo-900">RA 12009 — Government Procurement Reform Act Guidance</h4>
                            <p className="mt-1 text-sm leading-relaxed text-indigo-800">
                                The <strong>PPMP (Project Procurement Management Plan)</strong> covers <strong>non-common use items</strong> such as capital outlay, infrastructure projects, and specialized equipment unique to your office's needs.
                            </p>
                            <p className="mt-1 text-sm leading-relaxed text-indigo-800">
                                <strong>Common-use supplies</strong> (e.g., ballpens, folders, bond paper, ink cartridges) should be included in the <strong>APP (Annual Procurement Plan)</strong> and procured through the Procurement Service (PS-DBM), <em>not</em> in this PPMP.
                            </p>
                            <p className="mt-2 text-xs text-indigo-700">
                                Reference: Republic Act No. 12009, Sec. 7 — Procurement Planning; IRR Rule VI — Annual Procurement Plan and Project Procurement Management Plan.
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-2xl font-semibold text-slate-800">PPMP Details</h3>
                        <div className="flex items-center gap-2">
                            {isRefreshing ? <span className="text-xs text-slate-500">Refreshing item options...</span> : null}
                            <Link href={route('office.ppmp.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to PPMP</Link>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Fiscal Year</span>
                            <input type="number" min={CURRENT_YEAR} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.fiscal_year} onChange={(event) => form.setData('fiscal_year', Number(event.target.value))} required />
                            <span className="mt-1 block text-xs text-slate-500">Fiscal year cannot be earlier than {CURRENT_YEAR}.</span>
                            <InputError message={form.errors.fiscal_year} className="mt-1" />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Date Prepared</span>
                            <input type="date" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" value={form.data.date_prepared} readOnly aria-readonly="true" required />
                            <span className="mt-1 block text-xs text-slate-500">Automatically set to today and locked for audit consistency.</span>
                            <InputError message={form.errors.date_prepared} className="mt-1" />
                        </label>
                        <label className="block md:col-span-2">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Fund Source</span>
                            <select value={form.data.fund_source} onChange={(event) => form.setData('fund_source', event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                                {FUND_SOURCES.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                            <InputError message={form.errors.fund_source} className="mt-1" />
                        </label>
                    </div>
                    <label className="mt-4 block">
                        <span className="mb-1 block text-sm font-medium text-slate-700">Remarks</span>
                        <textarea className="h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.remarks} onChange={(event) => form.setData('remarks', event.target.value)} />
                    </label>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <h4 className="text-xl font-semibold text-slate-800">PPMP Items</h4>
                        <button type="button" onClick={addItem} className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Add Item Row</button>
                    </div>

                    {hasDuplicateItems ? (
                        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                            Duplicate PPMP item lines are not allowed. Use one row per item and procurement quarter, then adjust the quantity or remove the duplicate row.
                        </div>
                    ) : null}

                    <div className="space-y-4">
                        {form.data.items.map((item, index) => {
                            const selectedItem = itemOptionById.get(item.item_id);
                            const unit = selectedItem?.default_unit || '-';
                            const isDuplicate = duplicateRowIndexes.has(index);

                            return (
                            <div key={index} className={`rounded-xl border p-4 ${isDuplicate ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200'}`}>
                                {isDuplicate ? (
                                    <div className="mb-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm font-semibold text-amber-800">
                                        This item is already planned for {item.procurement_schedule}. Update the existing row instead of saving a duplicate.
                                    </div>
                                ) : null}
                                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Item</span>
                                        <select value={item.item_id} onChange={(event) => chooseItem(index, event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                                            <option value="">Select item</option>
                                            {itemOptions.map((option) => (
                                                <option key={option.item_id} value={option.item_id}>{option.item_name}</option>
                                            ))}
                                        </select>
                                        <InputError message={form.errors[`items.${index}.item_id`]} className="mt-1" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Quantity</span>
                                        <input type="number" min="1" value={item.quantity} onChange={(event) => patchItem(index, { quantity: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                                        <span className="mt-1 block text-xs text-slate-500">Quantity: {item.quantity || 0} {unit}</span>
                                        <InputError message={form.errors[`items.${index}.quantity`]} className="mt-1" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Unit</span>
                                        <input value={unit} readOnly aria-readonly="true" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Unit Cost</span>
                                        <input type="number" min="0.01" step="0.01" value={item.unit_cost} onChange={(event) => patchItem(index, { unit_cost: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                                        <InputError message={form.errors[`items.${index}.unit_cost`]} className="mt-1" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Procurement Schedule</span>
                                        <select value={item.procurement_schedule} onChange={(event) => patchItem(index, { procurement_schedule: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                                            <option value="">Select Quarter</option>
                                            {QUARTER_OPTIONS.map((quarter) => (
                                                <option key={quarter} value={quarter}>{quarter}</option>
                                            ))}
                                        </select>
                                        <InputError message={form.errors[`items.${index}.procurement_schedule`]} className="mt-1" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Item Remarks</span>
                                        <input value={item.remarks} onChange={(event) => patchItem(index, { remarks: event.target.value })} placeholder="Required item specification for SR and PO" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                                        <span className="mt-1 block text-xs text-slate-500">This becomes the item specification shown on Supply Requests and auto-filled on Purchase Orders.</span>
                                        <InputError message={form.errors[`items.${index}.remarks`]} className="mt-1" />
                                    </label>
                                </div>
                                <div className="mt-3 flex justify-end">
                                    <button type="button" onClick={() => removeItem(index)} className="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700">Remove Row</button>
                                </div>
                            </div>
                            );
                        })}
                    </div>
                </section>

                <div className="flex justify-end">
                    <button type="submit" disabled={form.processing || hasDuplicateItems} className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">Save PPMP</button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function duplicateIndexesForDraftItems(items: ItemDraft[]): Set<number> {
    const firstIndexByKey = new Map<string, number>();
    const duplicates = new Set<number>();

    items.forEach((item, index) => {
        const itemId = item.item_id.trim();
        const schedule = item.procurement_schedule.trim().toUpperCase();

        if (itemId === '' || schedule === '') {
            return;
        }

        const key = `${itemId}|${schedule}`;
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

