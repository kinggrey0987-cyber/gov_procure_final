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
    item_specification?: string | null;
    default_unit?: string | null;
    pieces_per_box?: number | null;
    sub_unit?: string | null;
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
        priority_level: 'NORMAL',
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

    const totalRequestedItems = useMemo(() => {
        return form.data.items.reduce((sum, item) => {
            return sum + (Number(item.quantity_requested) || 0);
        }, 0);
    }, [form.data.items]);

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

    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['ppmpOptions', 'requestDate'],
        onRefresh: handleReferenceRefresh,
        enabled: !form.processing,
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const optionById = useMemo(() => {
        return new Map(itemOptions.map((option) => [String(option.ppmp_item_id), option]));
    }, [itemOptions]);

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

            <form onSubmit={submit} className="lg:h-[calc(100vh-8rem)] flex flex-col justify-between py-1 space-y-2 overflow-y-auto lg:overflow-hidden">
                {/* Card 1: New Supply Request (Matches User Manual Screenshot) */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-3 sm:p-3.5 shadow-xs">
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="text-base font-bold text-slate-800">New Supply Request</h3>
                            <p className="text-xs text-slate-600">Select a PPMP first, then add request lines from that plan only.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => void refreshNow()}
                                disabled={isRefreshing}
                                className="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition"
                            >
                                {isRefreshing ? 'Refreshing...' : 'Refresh Options'}
                            </button>
                            <Link
                                href={route('office.supply-requests.index')}
                                className="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Back to Tracking
                            </Link>
                        </div>
                    </div>

                    {/* Source PPMP Box */}
                    <div className="mb-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium text-slate-700">Source PPMP</span>
                            <select
                                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium"
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
                            <p className="mt-0.5 text-[10px] text-slate-500">Only approved PPMPs for the active fiscal year can be used as the basis for a supply request.</p>
                        </label>
                        {ppmpOptions.length === 0 ? (
                            <p className="mt-1 text-[11px] font-medium text-amber-700">No approved PPMP found for the active fiscal year. Your office must have an approved PPMP before submitting supply requests.</p>
                        ) : null}
                    </div>

                    {/* Compact 4-column row for dates & categories */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Request Date</span>
                            <input
                                type="text"
                                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs text-slate-700 font-medium"
                                value={dateInputValue(requestDate)}
                                readOnly
                                aria-readonly="true"
                            />
                            <span className="mt-0.5 block text-[10px] text-slate-500">Auto-recorded</span>
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Needed By Date</span>
                            <input
                                type="date"
                                className="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium"
                                value={form.data.needed_by_date}
                                min={today}
                                onChange={(event) => {
                                    form.setData('needed_by_date', event.target.value);
                                    form.clearErrors('needed_by_date');
                                }}
                                required
                            />
                            {neededByDateError || form.errors.needed_by_date ? (
                                <p className="mt-0.5 text-[10px] text-rose-600 truncate">{neededByDateError || form.errors.needed_by_date}</p>
                            ) : null}
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Request Category</span>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs"
                                value={form.data.request_category}
                                onChange={(event) => form.setData('request_category', event.target.value)}
                                required
                            >
                                <option value="GENERAL">General</option>
                                <option value="OFFICE_SUPPLIES">Office Supplies</option>
                                <option value="IT_EQUIPMENT">IT Equipment</option>
                                <option value="FACILITY">Facility</option>
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Priority Level</span>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium"
                                value={form.data.priority_level}
                                onChange={(event) => form.setData('priority_level', event.target.value)}
                                required
                            >
                                <option value="NORMAL">Normal</option>
                                <option value="URGENT">🔥 Urgent (Priority Need)</option>
                            </select>
                        </label>
                    </div>

                    {/* Purpose & Notes in 2-column row */}
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Purpose <span className="text-rose-500">*</span></span>
                            <input
                                type="text"
                                placeholder="Purpose of this supply request..."
                                className={`w-full rounded-lg border px-2.5 py-1 text-xs ${form.errors.purpose ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'}`}
                                value={form.data.purpose}
                                onChange={(event) => form.setData('purpose', event.target.value)}
                                required
                            />
                            {form.errors.purpose ? <p className="mt-0.5 text-[10px] text-rose-600">{form.errors.purpose}</p> : null}
                        </label>

                        <label className="block">
                            <span className="mb-0.5 block text-xs font-medium text-slate-700">Notes <span className="text-slate-400 font-normal">(Optional)</span></span>
                            <input
                                type="text"
                                placeholder="Optional notes for review..."
                                className="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs"
                                value={form.data.review_notes}
                                onChange={(event) => form.setData('review_notes', event.target.value)}
                            />
                        </label>
                    </div>
                </section>

                {/* Card 2: Request Items (Matches User Manual Screenshot) */}
                <section className="flex-1 flex flex-col min-h-0 rounded-xl border border-slate-200 bg-white p-3 sm:p-3.5 shadow-xs">
                    <div className="mb-2 flex items-center justify-between shrink-0">
                        <div className="flex items-center gap-2">
                            <h4 className="text-base font-bold text-slate-800">Request Items</h4>
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                {form.data.items.length} {form.data.items.length === 1 ? 'line' : 'lines'}
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={addItem}
                            disabled={!hasPpmp}
                            className="inline-flex items-center gap-1 rounded-lg border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400 transition shadow-xs"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Add Item Row</span>
                        </button>
                    </div>

                    {loadingOptions ? <p className="mb-1 text-xs text-slate-500">Loading approved PPMP items...</p> : null}

                    {scheduleMismatchLineCount > 0 ? (
                        <div className="mb-1.5 shrink-0 rounded-md border border-orange-300 bg-orange-50 px-2.5 py-1 text-[11px] text-orange-900">
                            <strong>Notice:</strong> {scheduleMismatchLineCount} out-of-schedule PPMP line(s) detected (early requisition will be flagged for anomaly monitoring).
                        </div>
                    ) : null}

                    {duplicateLineCount > 0 ? (
                        <div className="mb-1.5 shrink-0 rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-[11px] text-rose-900">
                            <strong>Duplicate:</strong> {duplicateLineCount} duplicate PPMP line selection(s) detected. Adjust quantity on a single row instead.
                        </div>
                    ) : null}

                    {/* Scrollable Items Container (Page stays fixed; only rows scroll) */}
                    <div className="flex-1 overflow-y-auto pr-1 space-y-2 min-h-0">
                        {form.data.items.map((item, index) => {
                            const option = optionById.get(item.ppmp_item_id);
                            const isDuplicate = item.ppmp_item_id !== '' && duplicatePpmpItemIds.includes(item.ppmp_item_id);
                            const piecesPerBox = option?.pieces_per_box || 1;
                            const subUnit = option?.sub_unit || 'piece';
                            const reqQty = Number(item.quantity_requested || 0);
                            const reqPieces = reqQty * piecesPerBox;
                            const postReqBalance = (option?.remaining_quantity ?? 0) - reqQty;

                            return (
                                <div
                                    key={index}
                                    className={`rounded-xl border p-2.5 transition ${
                                        isDuplicate
                                            ? 'border-rose-300 bg-rose-50/40'
                                            : option?.is_schedule_mismatch
                                            ? 'border-orange-200 bg-orange-50/30'
                                            : 'border-slate-200 bg-slate-50/40'
                                    }`}
                                >
                                    <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-200">
                                        <div className="flex items-center gap-2">
                                            <span className="inline-flex items-center justify-center h-5 px-2 rounded-full text-[11px] font-bold bg-slate-200 text-slate-700">
                                                Line #{index + 1}
                                            </span>
                                            {item.item_name_snapshot ? (
                                                <span className="text-xs font-semibold text-slate-800 truncate max-w-sm">
                                                    {item.item_name_snapshot}
                                                </span>
                                            ) : null}
                                            {option?.item_specification ? (
                                                <span className="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                                                    {option.item_specification}
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
                                        {/* PPMP Item Selector */}
                                        <label className="block">
                                            <span className="mb-0.5 block text-xs font-medium text-slate-700">PPMP Item</span>
                                            <select
                                                value={item.ppmp_item_id}
                                                onChange={(event) => handleOptionChange(index, event.target.value)}
                                                className="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs"
                                                disabled={!hasPpmp || loadingOptions}
                                                required
                                            >
                                                <option value="">{hasPpmp ? 'Select PPMP item' : 'Select PPMP first'}</option>
                                                {itemOptions.map((entry) => {
                                                    const isExhausted = Number(entry.remaining_quantity) <= 0;
                                                    const isOutSchedule = Boolean(entry.is_schedule_mismatch);

                                                    let statusTag = `(Remaining: ${entry.remaining_quantity} ${entry.default_unit || 'units'})`;
                                                    if (isExhausted) {
                                                        statusTag = `⚠️ [Allocation Exhausted (0 left)]`;
                                                    } else if (isOutSchedule) {
                                                        statusTag = `⏰ [Schedule: ${entry.procurement_schedule || 'Later Quarter'}] (Remaining: ${entry.remaining_quantity})`;
                                                    }

                                                    const specTag = entry.item_specification ? ` — ${entry.item_specification}` : '';

                                                    return (
                                                        <option
                                                            key={entry.ppmp_item_id}
                                                            value={entry.ppmp_item_id}
                                                            className={isExhausted ? 'text-amber-800 bg-amber-50 font-medium' : ''}
                                                        >
                                                            {entry.item_name}{specTag} {entry.procurement_schedule ? `[${entry.procurement_schedule}]` : ''} {statusTag}
                                                        </option>
                                                    );
                                                })}
                                            </select>
                                            {form.errors[`items.${index}.ppmp_item_id`] ? <p className="mt-0.5 text-[10px] text-rose-600">{form.errors[`items.${index}.ppmp_item_id`]}</p> : null}
                                        </label>

                                        {option?.item_specification ? (
                                            <div className="flex items-center gap-1.5 text-xs text-slate-600 bg-blue-50/60 border border-blue-200/80 rounded-md px-2.5 py-1">
                                                <span className="text-[11px] font-semibold uppercase tracking-wider text-blue-700">Specification:</span>
                                                <span className="font-semibold text-blue-900">{option.item_specification}</span>
                                            </div>
                                        ) : null}

                                        {/* Quantity & Packaging Unit in 2-col row */}
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <label className="block">
                                                <span className="mb-0.5 block text-xs font-medium text-slate-700">Quantity ({option?.default_unit || 'Units'})</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={item.quantity_requested}
                                                    onChange={(event) => updateItem(index, { quantity_requested: event.target.value })}
                                                    className="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium"
                                                    required
                                                />
                                                <div className="mt-0.5 text-[10px] text-slate-600">
                                                    Requested: <strong className="text-slate-800">{reqQty} {option?.default_unit || 'units'}</strong>
                                                    {piecesPerBox > 1 ? (
                                                        <span className="text-indigo-600 font-semibold ml-1">
                                                            = {reqPieces.toLocaleString()} total {subUnit}s
                                                        </span>
                                                    ) : null}
                                                </div>
                                                {form.errors[`items.${index}.quantity_requested`] ? <p className="mt-0.5 text-[10px] text-rose-600">{form.errors[`items.${index}.quantity_requested`]}</p> : null}
                                            </label>

                                            <label className="block">
                                                <span className="mb-0.5 block text-xs font-medium text-slate-700">Packaging Unit</span>
                                                <input
                                                    value={option?.default_unit ? `${option.default_unit}${option.pieces_per_box && option.pieces_per_box > 1 ? ` (${option.pieces_per_box} ${option.sub_unit || 'piece'}s/${option.default_unit})` : ''}` : '-'}
                                                    readOnly
                                                    aria-readonly="true"
                                                    className="w-full rounded-lg border border-slate-300 bg-slate-100/70 px-2.5 py-1 text-xs text-slate-700"
                                                />
                                            </label>
                                        </div>

                                        {/* Allocation Balances Strip */}
                                        {option ? (
                                            <div className="rounded-lg bg-slate-100/80 p-2 text-[11px] text-slate-600 grid grid-cols-2 sm:grid-cols-4 gap-1">
                                                <div>Planned: <strong>{option.planned_quantity} {option.default_unit}</strong></div>
                                                <div>Requested: <strong>{option.requested_quantity} {option.default_unit}</strong></div>
                                                <div>
                                                    Remaining: <strong className={option.remaining_quantity <= 0 ? 'text-amber-700 font-bold' : 'text-emerald-700 font-bold'}>
                                                        {option.remaining_quantity} {option.default_unit}
                                                    </strong>
                                                </div>
                                                <div>
                                                    Post-Req Balance: <strong className={postReqBalance < 0 ? 'text-rose-600 font-bold' : 'text-slate-900 font-bold'}>
                                                        {postReqBalance} {option.default_unit}
                                                    </strong>
                                                </div>
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Bottom Action Strip (Save & Submit) */}
                    <div className="mt-2 pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 shrink-0">
                        <div className="text-xs text-slate-600">
                            <span>Total Units Requested: </span>
                            <strong className="text-sm font-bold text-slate-900">{totalRequestedItems}</strong>
                            {!hasPpmp ? (
                                <span className="ml-2 text-[11px] text-amber-700 font-medium">(Select approved PPMP first)</span>
                            ) : duplicateLineCount > 0 ? (
                                <span className="ml-2 text-[11px] text-rose-700 font-medium">(Remove duplicate lines)</span>
                            ) : null}
                        </div>

                        <div className="flex items-center gap-2">
                            <Link
                                href={route('office.supply-requests.index')}
                                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                onClick={() => {
                                    submitAfterSaveRef.current = false;
                                }}
                                disabled={form.processing || !hasPpmp || duplicateLineCount > 0 || !isFormComplete}
                                className="rounded-lg border border-red-300 bg-white px-4 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60 transition shadow-xs"
                            >
                                Save Request
                            </button>
                            <button
                                type="submit"
                                onClick={() => {
                                    submitAfterSaveRef.current = true;
                                }}
                                disabled={form.processing || !hasPpmp || duplicateLineCount > 0 || !isFormComplete}
                                className="rounded-lg bg-red-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60 transition shadow-xs"
                            >
                                Submit Request
                            </button>
                        </div>
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
