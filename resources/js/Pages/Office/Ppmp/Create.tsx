import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { FUND_SOURCES, DEFAULT_FUND_SOURCE } from '@/constants/fundSources';

type ItemOption = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    item_category_name: string;
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

export default function PpmpCreate({ itemOptions }: Props) {
    const { isRefreshing } = useSmartRefresh({
        only: ['itemOptions'],
        refreshOnFocus: true,
        refreshOnMount: true,
    });

    const form = useForm({
        fiscal_year: new Date().getFullYear(),
        date_prepared: new Date().toISOString().slice(0, 10),
        fund_source: DEFAULT_FUND_SOURCE,
        remarks: '',
        items: [emptyItem()],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
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

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create PPMP</h2>}>
            <Head title="Create PPMP" />

            <form onSubmit={submit} className="space-y-6 py-4">
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
                            <input type="number" min="2000" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.fiscal_year} onChange={(event) => form.setData('fiscal_year', Number(event.target.value))} />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Date Prepared</span>
                            <input type="date" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={form.data.date_prepared} onChange={(event) => form.setData('date_prepared', event.target.value)} />
                        </label>
                        <label className="block md:col-span-2">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Fund Source</span>
                            <select value={form.data.fund_source} onChange={(event) => form.setData('fund_source', event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                {FUND_SOURCES.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
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
                        <button type="button" onClick={addItem} className="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700">Add Item Row</button>
                    </div>

                    <div className="space-y-4">
                        {form.data.items.map((item, index) => (
                            <div key={index} className="rounded-xl border border-slate-200 p-4">
                                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Item</span>
                                        <select value={item.item_id} onChange={(event) => chooseItem(index, event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            <option value="">Select item</option>
                                            {itemOptions.map((option) => (
                                                <option key={option.item_id} value={option.item_id}>{option.item_name}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Quantity</span>
                                        <input type="number" min="1" value={item.quantity} onChange={(event) => patchItem(index, { quantity: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Unit Cost</span>
                                        <input type="number" min="0" step="0.01" value={item.unit_cost} onChange={(event) => patchItem(index, { unit_cost: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Procurement Schedule</span>
                                        <select value={item.procurement_schedule} onChange={(event) => patchItem(index, { procurement_schedule: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            <option value="">Select Quarter</option>
                                            {QUARTER_OPTIONS.map((quarter) => (
                                                <option key={quarter} value={quarter}>{quarter}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="block">
                                        <span className="mb-1 block text-sm font-medium text-slate-700">Item Remarks</span>
                                        <input value={item.remarks} onChange={(event) => patchItem(index, { remarks: event.target.value })} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                    </label>
                                </div>
                                <div className="mt-3 flex justify-end">
                                    <button type="button" onClick={() => removeItem(index)} className="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700">Remove Row</button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="flex justify-end">
                    <button type="submit" disabled={form.processing} className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">Save PPMP</button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function emptyItem(): ItemDraft {
    return {
        item_id: '',
        item_category_id: '',
        quantity: '1',
        unit_cost: '0',
        procurement_schedule: '',
        remarks: '',
    };
}
