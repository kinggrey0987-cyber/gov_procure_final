import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useCallback, useMemo, useState } from 'react';

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

type RequestItemDraft = {
    ppmp_item_id: string;
    item_id: string;
    item_name_snapshot: string;
    quantity_requested: string;
};

type Props = {
    ppmpOptions: PpmpOption[];
    selectedPpmpId: number | null;
    ppmpItemOptions: Option[];
    requestDate: string;
};

export default function SupplyRequestCreate({ ppmpOptions, selectedPpmpId, ppmpItemOptions: initialOptions, requestDate }: Props) {
    const [itemOptions, setItemOptions] = useState<Option[]>(initialOptions);
    const [loadingOptions, setLoadingOptions] = useState(false);

    const form = useForm({
        ppmp_id: selectedPpmpId ? String(selectedPpmpId) : '',
        needed_by_date: new Date().toISOString().slice(0, 10),
        request_category: 'GENERAL',
        purpose: '',
        priority_level: 'MEDIUM',
        review_notes: '',
        items: [emptyItem()],
    });

    const hasPpmp = form.data.ppmp_id !== '';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('office.supply-requests.store'));
    };

    const addItem = () => {
        if (!hasPpmp) {
            return;
        }

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
        const option = itemOptions.find((current) => String(current.ppmp_item_id) === value);
        if (!option) {
            updateItem(index, {
                ppmp_item_id: '',
                item_id: '',
                item_name_snapshot: '',
            });
            return;
        }

        updateItem(index, {
            ppmp_item_id: String(option.ppmp_item_id),
            item_id: String(option.item_id),
            item_name_snapshot: option.item_name,
        });
    };

    const fetchPpmpItems = useCallback(async (value: string) => {
        if (!value) {
            setItemOptions([]);
            return;
        }

        setLoadingOptions(true);
        try {
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
        } finally {
            setLoadingOptions(false);
        }
    }, []);

    const handlePpmpChange = async (value: string) => {
        form.setData('ppmp_id', value);
        form.setData('items', [emptyItem()]);
        await fetchPpmpItems(value);
    };

    const { isRefreshing, lastRefreshedAt, refreshNow } = useSmartRefresh({
        only: ['ppmpOptions', 'requestDate'],
        onRefresh: async () => {
            await new Promise<void>((resolve) => {
                router.reload({
                    only: ['ppmpOptions', 'requestDate'],
                    onFinish: () => resolve(),
                });
            });

            if (form.data.ppmp_id) {
                await fetchPpmpItems(form.data.ppmp_id);
            }
        },
        refreshOnFocus: true,
        refreshOnMount: true,
    });

    const optionById = useMemo(() => {
        return new Map(itemOptions.map((option) => [String(option.ppmp_item_id), option]));
    }, [itemOptions]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Supply Request</h2>}>
            <Head title="Create Supply Request" />

            <form onSubmit={submit} className="space-y-6 py-4">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-semibold text-slate-800">New Supply Request</h3>
                            <p className="text-sm text-slate-600">Select a PPMP first, then add request lines from that plan only.</p>
                            <p className="mt-1 text-xs text-slate-500">
                                {isRefreshing
                                    ? 'Refreshing PPMP references...'
                                    : lastRefreshedAt
                                    ? `Updated ${lastRefreshedAt.toLocaleTimeString()}`
                                    : 'Auto-refreshes on tab focus'}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => void refreshNow()}
                                disabled={isRefreshing}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                            >
                                {isRefreshing ? 'Refreshing...' : 'Refresh Options'}
                            </button>
                            <Link
                                href={route('office.supply-requests.index')}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Back to Tracking
                            </Link>
                        </div>
                    </div>

                    <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <Field label="Source PPMP">
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.ppmp_id}
                                onChange={(event) => void handlePpmpChange(event.target.value)}
                                required
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
                        {!hasPpmp ? <p className="mt-2 text-xs font-medium text-amber-700">No approved PPMP is available yet. Your office must have an approved PPMP before creating a supply request.</p> : null}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Request Date" hint="Request date is automatically recorded by the system.">
                            <input type="text" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" value={requestDate} readOnly aria-readonly="true" />
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
                            disabled={!hasPpmp}
                            className="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                        >
                            Add Item Row
                        </button>
                    </div>

                    {loadingOptions ? <p className="mb-3 text-sm text-slate-500">Loading approved PPMP items...</p> : null}

                    <div className="space-y-4">
                        {form.data.items.map((item, index) => {
                            const option = optionById.get(item.ppmp_item_id);
                            const rowQuantity = Number(item.quantity_requested || '0');
                            const remaining = option?.remaining_quantity ?? null;
                            const exceedsRemaining = remaining !== null && rowQuantity > remaining;

                            return (
                                <div key={index} className="rounded-xl border border-slate-200 p-4">
                                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                                        <Field label="PPMP Item">
                                            <select
                                                value={item.ppmp_item_id}
                                                onChange={(event) => handleOptionChange(index, event.target.value)}
                                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                disabled={!hasPpmp || loadingOptions}
                                            >
                                                <option value="">{hasPpmp ? 'Select PPMP item' : 'Select PPMP first'}</option>
                                                {itemOptions.map((entry) => (
                                                    <option key={entry.ppmp_item_id} value={entry.ppmp_item_id}>
                                                        {entry.item_name} (remaining: {entry.remaining_quantity})
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>

                                        <Field label="Item Snapshot" hint="Auto-filled from the selected PPMP item.">
                                            <input
                                                value={item.item_name_snapshot}
                                                readOnly
                                                aria-readonly="true"
                                                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700"
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

                                    </div>

                                    {option ? (
                                        <div className="mt-3 grid gap-2 text-xs text-slate-600 md:grid-cols-4">
                                            <div>Planned: <strong>{option.planned_quantity}</strong></div>
                                            <div>Already requested: <strong>{option.requested_quantity}</strong></div>
                                            <div>Remaining: <strong>{option.remaining_quantity}</strong></div>
                                            <div>PPMP reference unit cost: <strong>{option.suggested_unit_cost}</strong></div>
                                        </div>
                                    ) : null}

                                    {exceedsRemaining ? (
                                        <p className="mt-2 text-xs font-semibold text-rose-700">
                                            Requested quantity exceeds remaining PPMP quantity for this line.
                                        </p>
                                    ) : null}

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
                            );
                        })}
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
                            disabled={form.processing || !hasPpmp}
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
    };
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
