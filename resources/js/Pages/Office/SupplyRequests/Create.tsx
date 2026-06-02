import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { dateInputValue, todayInputValue } from '@/lib/dateInput';

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
    const today = todayInputValue();

    const form = useForm({
        ppmp_id: selectedPpmpId ? String(selectedPpmpId) : '',
        needed_by_date: today,
        request_category: 'GENERAL',
        purpose: '',
        priority_level: 'MEDIUM',
        review_notes: '',
        submit_after_save: false,
        items: [emptyItem()],
    });
    const submitAfterSaveRef = useRef(false);
    const fetchedItemsForPpmpRef = useRef<string | null>(initialOptions.length > 0 && selectedPpmpId ? String(selectedPpmpId) : null);

    const hasPpmp = form.data.ppmp_id !== '';
    const neededByDateError = useMemo(() => {
        if (form.data.needed_by_date.trim() === '') {
            return '';
        }

        return form.data.needed_by_date < today
            ? 'The needed by date field must be a date after or equal to today.'
            : '';
    }, [form.data.needed_by_date, today]);

    const isFormComplete = useMemo(() => {
        return Boolean(
            form.data.ppmp_id.trim() !== ''
            && form.data.needed_by_date.trim() !== ''
            && neededByDateError === ''
            && form.data.request_category.trim() !== ''
            && form.data.priority_level.trim() !== ''
            && form.data.purpose.trim() !== ''
            && form.data.items.length > 0
            && form.data.items.every((item) => item.ppmp_item_id.trim() !== '' && Number(item.quantity_requested) > 0),
        );
    }, [form.data.items, form.data.needed_by_date, form.data.ppmp_id, form.data.priority_level, form.data.purpose, form.data.request_category, neededByDateError]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (neededByDateError) {
            return;
        }

        form.transform((data) => ({ ...data, submit_after_save: submitAfterSaveRef.current }));
        form.post(route('office.supply-requests.store'), {
            onFinish: () => {
                submitAfterSaveRef.current = false;
            },
        });
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
            fetchedItemsForPpmpRef.current = null;
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
            fetchedItemsForPpmpRef.current = value;
        } finally {
            setLoadingOptions(false);
        }
    }, []);

    const handlePpmpChange = async (value: string) => {
        form.setData('ppmp_id', value);
        form.setData('items', [emptyItem()]);
        await fetchPpmpItems(value);
    };

    const selectedPpmpIdRef = useRef(form.data.ppmp_id);

    useEffect(() => {
        selectedPpmpIdRef.current = form.data.ppmp_id;
    }, [form.data.ppmp_id]);

    useEffect(() => {
        if (!form.data.ppmp_id || loadingOptions || fetchedItemsForPpmpRef.current === form.data.ppmp_id) {
            return;
        }

        void fetchPpmpItems(form.data.ppmp_id);
    }, [fetchPpmpItems, form.data.ppmp_id, loadingOptions]);

    const handleReferenceRefresh = useCallback(async () => {
        await new Promise<void>((resolve) => {
            router.reload({
                only: ['ppmpOptions', 'requestDate'],
                onFinish: () => resolve(),
            });
        });

        if (selectedPpmpIdRef.current) {
            await fetchPpmpItems(selectedPpmpIdRef.current);
        }
    }, [fetchPpmpItems]);

    const { isRefreshing, lastRefreshedAt, refreshNow } = useSmartRefresh({
        only: ['ppmpOptions', 'requestDate'],
        onRefresh: handleReferenceRefresh,
        enabled: !form.processing,
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const optionById = useMemo(() => {
        return new Map(itemOptions.map((option) => [String(option.ppmp_item_id), option]));
    }, [itemOptions]);

    const overPlanLineCount = useMemo(() => {
        return form.data.items.reduce((count, item) => {
            const option = optionById.get(item.ppmp_item_id);
            if (!option) {
                return count;
            }

            const rowQuantity = Number(item.quantity_requested || '0');

            return rowQuantity > option.remaining_quantity ? count + 1 : count;
        }, 0);
    }, [form.data.items, optionById]);

    const selectedPpmpItemIds = useMemo(() => {
        return form.data.items
            .map((item) => item.ppmp_item_id)
            .filter((value) => value !== '');
    }, [form.data.items]);

    const duplicatePpmpItemIds = useMemo(() => {
        return selectedPpmpItemIds.filter((value, index, values) => values.indexOf(value) !== index);
    }, [selectedPpmpItemIds]);

    const duplicateLineCount = useMemo(() => {
        return form.data.items.filter((item) => item.ppmp_item_id !== '' && duplicatePpmpItemIds.includes(item.ppmp_item_id)).length;
    }, [duplicatePpmpItemIds, form.data.items]);

    const scheduleMismatchLineCount = useMemo(() => {
        return form.data.items.reduce((count, item) => {
            const option = optionById.get(item.ppmp_item_id);
            return option?.is_schedule_mismatch ? count + 1 : count;
        }, 0);
    }, [form.data.items, optionById]);

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
                                    : 'Use "Refresh Options" to sync approved PPMP references.'}
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
                            <input type="text" className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" value={dateInputValue(requestDate)} readOnly aria-readonly="true" />
                        </Field>

                        <Field label="Needed By Date">
                            <input
                                type="date"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.needed_by_date}
                                min={today}
                                onChange={(event) => {
                                    form.setData('needed_by_date', event.target.value);
                                    form.clearErrors('needed_by_date');
                                }}
                                required
                            />
                            {neededByDateError || form.errors.needed_by_date ? <p className="mt-1 text-xs text-rose-600">{neededByDateError || form.errors.needed_by_date}</p> : null}
                        </Field>

                        <Field label="Request Category">
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.request_category}
                                onChange={(event) => form.setData('request_category', event.target.value)}
                                required
                            >
                                <option value="GENERAL">General</option>
                                <option value="OFFICE_SUPPLIES">Office Supplies</option>
                                <option value="IT_EQUIPMENT">IT Equipment</option>
                                <option value="FACILITY">Facility</option>
                            </select>
                            {form.errors.request_category ? <p className="mt-1 text-xs text-rose-600">{form.errors.request_category}</p> : null}
                        </Field>

                        <Field label="Priority Level">
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.priority_level}
                                onChange={(event) => form.setData('priority_level', event.target.value)}
                                required
                            >
                                <option value="LOW">Low</option>
                                <option value="MEDIUM">Medium</option>
                                <option value="HIGH">High</option>
                                <option value="URGENT">Urgent</option>
                            </select>
                            {form.errors.priority_level ? <p className="mt-1 text-xs text-rose-600">{form.errors.priority_level}</p> : null}
                        </Field>
                    </div>

                    <div className="mt-4 grid gap-4">
                        <Field label="Purpose">
                            <textarea
                                className="h-24 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.purpose}
                                onChange={(event) => form.setData('purpose', event.target.value)}
                                required
                            />
                            {form.errors.purpose ? <p className="mt-1 text-xs text-rose-600">{form.errors.purpose}</p> : null}
                        </Field>
                        <Field label="Notes (Optional)">
                            <textarea
                                className="h-20 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.review_notes}
                                onChange={(event) => form.setData('review_notes', event.target.value)}
                            />
                            {form.errors.review_notes ? <p className="mt-1 text-xs text-rose-600">{form.errors.review_notes}</p> : null}
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
                            className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                        >
                            Add Item Row
                        </button>
                    </div>

                    {loadingOptions ? <p className="mb-3 text-sm text-slate-500">Loading approved PPMP items...</p> : null}

                    {hasPpmp && !loadingOptions && itemOptions.length === 0 ? (
                        <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <p className="font-semibold">No requestable PPMP items were found for the selected PPMP.</p>
                            <p>Use Refresh Options after approval, or verify that the planned item is still active in the item catalog.</p>
                        </div>
                    ) : null}

                    {overPlanLineCount > 0 ? (
                        <div className="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <p className="font-semibold">{overPlanLineCount} line item(s) exceed remaining PPMP allocation.</p>
                            <p>You may still submit for urgent or unplanned needs, but this request will be flagged for executive review.</p>
                        </div>
                    ) : null}

                    {scheduleMismatchLineCount > 0 ? (
                        <div className="mb-3 rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-xs text-orange-900">
                            <p className="font-semibold">{scheduleMismatchLineCount} out-of-schedule PPMP line(s) detected.</p>
                            <p>GSO may still process this SR, but early consumption will be flagged in Executive anomaly monitoring.</p>
                        </div>
                    ) : null}

                    {duplicateLineCount > 0 ? (
                        <div className="mb-3 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            <p className="font-semibold">{duplicateLineCount} duplicate PPMP line selection(s) detected.</p>
                            <p>Use one row per PPMP item line and update the quantity instead of adding the same line again.</p>
                        </div>
                    ) : null}

                    <div className="space-y-4">
                        {form.data.items.map((item, index) => {
                            const option = optionById.get(item.ppmp_item_id);
                            const rowQuantity = Number(item.quantity_requested || '0');
                            const remaining = option?.remaining_quantity ?? null;
                            const exceedsRemaining = remaining !== null && rowQuantity > remaining;
                            const isDuplicate = item.ppmp_item_id !== '' && duplicatePpmpItemIds.includes(item.ppmp_item_id);

                            return (
                                <div key={index} className="rounded-xl border border-slate-200 p-4">
                                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                                        <Field label="PPMP Item">
                                            <select
                                                value={item.ppmp_item_id}
                                                onChange={(event) => handleOptionChange(index, event.target.value)}
                                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                disabled={!hasPpmp || loadingOptions}
                                                required
                                            >
                                                <option value="">{hasPpmp ? 'Select PPMP item' : 'Select PPMP first'}</option>
                                                {itemOptions.map((entry) => (
                                                    <option key={entry.ppmp_item_id} value={entry.ppmp_item_id}>
                                                        {entry.item_name} {entry.procurement_schedule ? `- ${entry.procurement_schedule}` : ''} (remaining: {entry.remaining_quantity})
                                                    </option>
                                                ))}
                                            </select>
                                            {form.errors[`items.${index}.ppmp_item_id`] ? <p className="mt-1 text-xs text-rose-600">{form.errors[`items.${index}.ppmp_item_id`]}</p> : null}
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
                                                required
                                            />
                                            <span className="mt-1 block text-xs text-slate-500">Quantity: {item.quantity_requested || 0} {option?.default_unit || '-'}</span>
                                            {form.errors[`items.${index}.quantity_requested`] ? <p className="mt-1 text-xs text-rose-600">{form.errors[`items.${index}.quantity_requested`]}</p> : null}
                                        </Field>

                                        <Field label="Unit" hint="Auto-filled from the item catalog.">
                                            <input
                                                value={option?.default_unit || '-'}
                                                readOnly
                                                aria-readonly="true"
                                                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            />
                                        </Field>

                                    </div>

                                    {option ? (
                                        <div className="mt-3 grid gap-2 text-xs text-slate-600 md:grid-cols-4">
                                            <div>Planned: <strong>{option.planned_quantity} {option.default_unit || ''}</strong></div>
                                            <div>Already requested: <strong>{option.requested_quantity} {option.default_unit || ''}</strong></div>
                                            <div>Remaining: <strong>{option.remaining_quantity} {option.default_unit || ''}</strong></div>
                                            <div>PPMP reference unit cost: <strong>{option.suggested_unit_cost}</strong></div>
                                            <div>Schedule: <strong>{option.procurement_schedule || '-'}</strong></div>
                                        </div>
                                    ) : null}

                                    {isDuplicate ? (
                                        <div className="mt-2 rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                            <p className="font-semibold">This PPMP item line is already selected in another row.</p>
                                            <p>Remove the duplicate row and update the quantity on the existing row.</p>
                                        </div>
                                    ) : null}

                                    {option?.is_schedule_mismatch ? (
                                        <div className="mt-2 rounded-md border border-orange-300 bg-orange-50 px-3 py-2 text-xs text-orange-900">
                                            <p className="font-semibold">Out-of-schedule PPMP consumption detected.</p>
                                            <p>This item is planned for {option.procurement_schedule || 'a later schedule'} but the request is being made in {option.current_quarter || 'the current quarter'}. It can proceed to GSO but will be flagged for Executive anomaly review.</p>
                                        </div>
                                    ) : null}

                                    {exceedsRemaining ? (
                                        <div className="mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                            <p className="font-semibold">This request exceeds the remaining PPMP allocation for this item.</p>
                                            <p>You may still submit for urgent or unplanned needs, but it will be flagged for executive review.</p>
                                        </div>
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
                    {Object.keys(form.errors).length > 0 ? (
                        <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            <p className="font-semibold">Unable to save supply request.</p>
                            <p className="mt-1">Please fix the highlighted fields and submit again.</p>
                        </div>
                    ) : null}

                    <div className="flex flex-wrap justify-end gap-2">
                        <Link
                            href={route('office.supply-requests.index')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            onClick={() => {
                                submitAfterSaveRef.current = false;
                            }}
                            disabled={form.processing || !hasPpmp || duplicateLineCount > 0 || !isFormComplete}
                            className="rounded-lg border border-red-300 bg-white px-5 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
                        >
                            Save Request
                        </button>
                        <button
                            type="submit"
                            onClick={() => {
                                submitAfterSaveRef.current = true;
                            }}
                            disabled={form.processing || !hasPpmp || duplicateLineCount > 0 || !isFormComplete}
                            className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                        >
                            Submit Request
                        </button>
                    </div>
                    {!hasPpmp ? (
                        <p className="mt-3 text-right text-xs font-medium text-amber-700">
                            Save is disabled until you select an approved PPMP.
                        </p>
                    ) : null}
                    {duplicateLineCount > 0 ? (
                        <p className="mt-3 text-right text-xs font-medium text-rose-700">
                            Save is disabled while duplicate PPMP item lines exist.
                        </p>
                    ) : null}
                    {hasPpmp && !isFormComplete ? (
                        <p className="mt-3 text-right text-xs font-medium text-rose-700">
                            Save is disabled until purpose and every request item field are complete.
                        </p>
                    ) : null}
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
