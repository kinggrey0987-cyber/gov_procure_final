import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';

type DistributionItemOption = {
    distribution_item_id: number;
    item_id: number;
    item_name: string;
    quantity_released: number;
    quantity_received: number;
    quantity_damaged: number;
    quantity_missing: number;
    unit: string;
};

type DistributionRecordOption = {
    distribution_record_id: number;
    distribution_code: string;
    office_id: number;
    office_name: string;
    release_date?: string | null;
    status: string;
    request_code: string;
    has_ongoing_claim?: boolean;
    ongoing_claim_code?: string | null;
    items?: DistributionItemOption[];
};

type Props = {
    distributionRecords: DistributionRecordOption[];
    preselectedDistributionRecordId?: number | null;
    preselectedDistributionItemId?: number | null;
    parentClaim?: {
        claims_id: number;
        claim_code: string;
        status: string;
        distribution_item_id?: number | null;
        item_name?: string | null;
        quantity_disputed?: number;
        replacement_quantity?: number;
    } | null;
};

type IssueLine = {
    id: number;
    distribution_item_id: string;
    claim_type: string;
    quantity: string;
};

const claimTypeOptions = [
    { value: 'MISSING_ITEM', label: 'Missing Item' },
    { value: 'DAMAGED_ITEM', label: 'Damaged Item' },
];

const todayDate = (): string => {
    const date = new Date();
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return offsetDate.toISOString().split('T')[0];
};

function dateInputValue(value?: string | null): string | undefined {
    return value?.match(/^\d{4}-\d{2}-\d{2}/)?.[0];
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const datePart = dateInputValue(value);
    const parsed = new Date(datePart ? `${datePart}T00:00:00` : value);

    return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function ClaimCreate({ distributionRecords, preselectedDistributionRecordId = null, preselectedDistributionItemId = null, parentClaim = null }: Props) {
    const { auth } = usePage<PageProps>().props;
    const isOfficeUser = auth?.user?.role_code === 'OFFICE_USER';
    const isFollowUpClaim = parentClaim !== null;
    const targetDistributionItemId = parentClaim?.distribution_item_id ?? preselectedDistributionItemId;
    const preselectedRecord = preselectedDistributionRecordId
        ? distributionRecords.find((record) => record.distribution_record_id === preselectedDistributionRecordId)
        : null;
    const first = preselectedRecord ?? distributionRecords[0];
    const initialItem = (targetDistributionItemId
        ? first?.items?.find((item) => item.distribution_item_id === targetDistributionItemId)
        : null) ?? first?.items?.[0] ?? null;

    const followUpMaxQuantity = useMemo(() => {
        if (!isFollowUpClaim || !parentClaim) return null;
        if (parentClaim.replacement_quantity && parentClaim.replacement_quantity > 0) {
            return Math.trunc(parentClaim.replacement_quantity);
        }
        if (parentClaim.quantity_disputed && parentClaim.quantity_disputed > 0) {
            return Math.trunc(parentClaim.quantity_disputed);
        }
        return null;
    }, [isFollowUpClaim, parentClaim]);

    const form = useForm({
        distribution_record_id: first?.distribution_record_id ?? '',
        distribution_item_id: initialItem?.distribution_item_id ?? '',
        parent_claim_id: parentClaim?.claims_id ?? '',
        source_type: isFollowUpClaim ? 'CLAIM_REPLACEMENT_FOLLOW_UP' : '',
        office_id: first?.office_id ?? 0,
        claim_type: '',
        claim_date: todayDate(),
        claim_location: '',
        quantity_disputed: '',
        quantity_damaged: '',
        quantity_missing: '',
        remarks: '',
        attachment_title: '',
        attachment_file: null as File | null,
        attachments: [] as File[],
    });
    const [issueLines, setIssueLines] = useState<IssueLine[]>(() => buildInitialIssueLines(initialItem, isFollowUpClaim));
    const [validationError, setValidationError] = useState<string | null>(null);
    const [fileWarning, setFileWarning] = useState<string | null>(null);

    const groupedDistributionRecords = useMemo(() => {
        const groups: {
            requestCode: string;
            officeName: string;
            records: DistributionRecordOption[];
        }[] = [];

        const groupMap = new Map<string, { requestCode: string; officeName: string; records: DistributionRecordOption[] }>();

        for (const record of distributionRecords) {
            const key = record.request_code || 'Other';
            let group = groupMap.get(key);
            if (!group) {
                group = {
                    requestCode: key,
                    officeName: record.office_name,
                    records: [],
                };
                groupMap.set(key, group);
                groups.push(group);
            }
            group.records.push(record);
        }

        return groups;
    }, [distributionRecords]);

    const selectedRecord = useMemo(
        () => distributionRecords.find((record) => record.distribution_record_id === Number(form.data.distribution_record_id)),
        [distributionRecords, form.data.distribution_record_id],
    );
    const selectedRecordItems = useMemo(() => {
        const allItems = selectedRecord?.items ?? [];
        if (isFollowUpClaim && parentClaim?.distribution_item_id) {
            return allItems.filter((i) => i.distribution_item_id === Number(parentClaim.distribution_item_id));
        }
        return allItems;
    }, [selectedRecord, isFollowUpClaim, parentClaim]);
    const primaryDistributionItemId = issueLines[0]?.distribution_item_id || form.data.distribution_item_id;
    const selectedItem = useMemo(
        () => selectedRecordItems.find((item) => item.distribution_item_id === Number(primaryDistributionItemId)) ?? null,
        [selectedRecordItems, primaryDistributionItemId],
    );
    const selectedItemReleasedQuantity = Math.trunc(selectedItem?.quantity_released ?? 0);
    const damagedQuantity = issueLines.reduce((total, line) => total + (line.claim_type === 'DAMAGED_ITEM' ? toWholeQuantity(line.quantity) : 0), 0);
    const missingQuantity = issueLines.reduce((total, line) => total + (line.claim_type === 'MISSING_ITEM' ? toWholeQuantity(line.quantity) : 0), 0);
    const totalIssueQuantity = issueLines.reduce((total, line) => total + toWholeQuantity(line.quantity), 0);
    const derivedClaimType = getClaimTypeFromIssueLines(issueLines);
    const backRouteName = isOfficeUser ? 'office.claims.index' : 'inventory.claims.index';

    const isDuplicateCombo = (itemId: string | number, claimType: string, lineId: number) => {
        if (!itemId || !claimType) return false;
        return issueLines.some(
            (other) =>
                other.id !== lineId &&
                Boolean(other.distribution_item_id) &&
                String(other.distribution_item_id) === String(itemId) &&
                other.claim_type === claimType,
        );
    };

    const nextAvailableCombo = useMemo(() => {
        if (selectedRecordItems.length === 0) return null;

        const usedCombos = new Set(
            issueLines
                .filter((l) => Boolean(l.distribution_item_id))
                .map((l) => `${l.distribution_item_id}_${l.claim_type}`),
        );

        for (const item of selectedRecordItems) {
            for (const option of claimTypeOptions) {
                const key = `${item.distribution_item_id}_${option.value}`;
                if (!usedCombos.has(key)) {
                    return {
                        distribution_item_id: String(item.distribution_item_id),
                        claim_type: option.value,
                    };
                }
            }
        }
        return null;
    }, [selectedRecordItems, issueLines]);

    const hasEmptyLine = useMemo(() => issueLines.some((line) => !line.distribution_item_id), [issueLines]);
    const allCombosUsed = nextAvailableCombo === null;
    const canAddIssueLine = selectedRecordItems.length > 0 && !hasEmptyLine && !allCombosUsed;

    const hasDuplicateIssues = useMemo(() => {
        const seen = new Set<string>();
        for (const line of issueLines) {
            if (!line.distribution_item_id) continue;
            const key = `${line.distribution_item_id}_${line.claim_type}`;
            if (seen.has(key)) return true;
            seen.add(key);
        }
        return false;
    }, [issueLines]);

    const itemClaimedTotals = useMemo(() => {
        const totals: Record<string, number> = {};
        for (const line of issueLines) {
            if (!line.distribution_item_id) continue;
            const qty = toWholeQuantity(line.quantity);
            totals[line.distribution_item_id] = (totals[line.distribution_item_id] ?? 0) + qty;
        }
        return totals;
    }, [issueLines]);

    const distinctItemIds = useMemo(() => {
        return Array.from(new Set(issueLines.map((l) => l.distribution_item_id).filter(Boolean)));
    }, [issueLines]);

    const hasOverclaimedItems = useMemo(() => {
        for (const item of selectedRecordItems) {
            const total = itemClaimedTotals[String(item.distribution_item_id)] ?? 0;
            const releasedLimit = Math.trunc(item.quantity_released ?? 0);
            const effectiveLimit = (isFollowUpClaim && followUpMaxQuantity !== null)
                ? Math.min(releasedLimit || followUpMaxQuantity, followUpMaxQuantity)
                : releasedLimit;

            if (effectiveLimit > 0 && total > effectiveLimit) {
                return true;
            }
        }
        return false;
    }, [selectedRecordItems, itemClaimedTotals, isFollowUpClaim, followUpMaxQuantity]);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">File Claim</h2>}>
            <Head title="File Claim" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <Link href={route(backRouteName)} className="mb-4 inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Back to Claims and Adjustments
                    </Link>

                    <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-sm text-slate-600">Claims are filed against supply requests your office has already confirmed received.</p>
                        <p className="mt-2 text-xs text-slate-500">Claim types are limited to missing items and damaged items.</p>
                        {parentClaim ? (
                            <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                <p className="font-semibold">
                                    Follow-up claim for {parentClaim.claim_code}{parentClaim.item_name ? ` (${parentClaim.item_name})` : ''}
                                </p>
                                <p className="mt-1 text-xs text-amber-800">
                                    This follow-up claim is restricted specifically to this item line.
                                    {followUpMaxQuantity !== null ? ` Disputed quantity is capped at ${followUpMaxQuantity} (resolved replacement quantity).` : ''}
                                    {' '}Resolving this follow-up will also resolve the original claim.
                                </p>
                            </div>
                        ) : null}
                    </div>

                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setValidationError(null);

                            if (!form.data.distribution_record_id) {
                                setValidationError('Please select a received supply request.');
                                return;
                            }

                            if (selectedRecord?.has_ongoing_claim) {
                                setValidationError(`This supply request already has an ongoing claim (${selectedRecord.ongoing_claim_code}) that is not yet completed. You cannot file a second claim until it is resolved or rejected.`);
                                return;
                            }

                            const unselectedLineIndex = issueLines.findIndex((l) => !l.distribution_item_id);
                            if (unselectedLineIndex !== -1) {
                                setValidationError(`Please select an item on issue line #${unselectedLineIndex + 1} before submitting.`);
                                return;
                            }

                            if (hasDuplicateIssues) {
                                setValidationError('Duplicate issue lines found for the same item and reason. Please remove duplicate lines before submitting.');
                                return;
                            }
                            if (hasOverclaimedItems) {
                                setValidationError(
                                    isFollowUpClaim && followUpMaxQuantity !== null
                                        ? `Total claimed quantity exceeds the replacement quantity limit (${followUpMaxQuantity}). Please adjust the quantity before submitting.`
                                        : 'Total claimed quantity exceeds the released quantity for one or more items. Please adjust the quantities before submitting.'
                                );
                                return;
                            }
                            if (totalIssueQuantity <= 0) {
                                setValidationError('Please specify a claimed quantity greater than 0 on at least one issue line.');
                                return;
                            }

                            if (!form.data.remarks.trim()) {
                                setValidationError('Please provide discrepancy details & findings before submitting.');
                                return;
                            }

                            const primaryItemId = issueLines.find((l) => toWholeQuantity(l.quantity) > 0)?.distribution_item_id || issueLines[0]?.distribution_item_id || form.data.distribution_item_id;

                            const itemsPayload = issueLines
                                .filter((l) => Boolean(l.distribution_item_id) && toWholeQuantity(l.quantity) > 0)
                                .map((l) => ({
                                    distribution_item_id: Number(l.distribution_item_id),
                                    claim_type: l.claim_type,
                                    quantity_disputed: toWholeQuantity(l.quantity),
                                    quantity_damaged: l.claim_type === 'DAMAGED_ITEM' ? toWholeQuantity(l.quantity) : 0,
                                    quantity_missing: l.claim_type === 'MISSING_ITEM' ? toWholeQuantity(l.quantity) : 0,
                                }));

                            const primaryItemLines = issueLines.filter((l) => String(l.distribution_item_id) === String(primaryItemId));
                            const primaryDamaged = primaryItemLines
                                .filter((l) => l.claim_type === 'DAMAGED_ITEM')
                                .reduce((sum, l) => sum + toWholeQuantity(l.quantity), 0);
                            const primaryMissing = primaryItemLines
                                .filter((l) => l.claim_type === 'MISSING_ITEM')
                                .reduce((sum, l) => sum + toWholeQuantity(l.quantity), 0);
                            const primaryTotal = primaryDamaged + primaryMissing;

                            const distinctItemCount = new Set(itemsPayload.map((i) => i.distribution_item_id)).size;

                            form.transform((data) => ({
                                ...data,
                                distribution_item_id: primaryItemId,
                                claim_type: derivedClaimType,
                                quantity_disputed: String(distinctItemCount > 1 ? primaryTotal : totalIssueQuantity),
                                quantity_damaged: String(distinctItemCount > 1 ? primaryDamaged : damagedQuantity),
                                quantity_missing: String(distinctItemCount > 1 ? primaryMissing : missingQuantity),
                                items: itemsPayload,
                            }));
                            form.post(route(isOfficeUser ? 'office.claims.store' : 'inventory.claims.store'), { forceFormData: true });
                        }}
                    >
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Select Received Supply Request & Delivery Batch
                            </label>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.distribution_record_id}
                                disabled={isFollowUpClaim}
                                onChange={(e) => {
                                    setValidationError(null);
                                    const selectedRecordId = Number(e.target.value);
                                    const selected = distributionRecords.find((record) => record.distribution_record_id === selectedRecordId);
                                    if (selected?.has_ongoing_claim) {
                                        setValidationError(`This supply request already has an ongoing claim (${selected.ongoing_claim_code}) that is not yet completed. You cannot file another claim until it is resolved or rejected.`);
                                        return;
                                    }
                                    const firstItem = selected?.items?.[0] ?? null;
                                    form.setData('distribution_record_id', selectedRecordId);
                                    form.setData('distribution_item_id', firstItem?.distribution_item_id ?? '');
                                    form.setData('office_id', selected?.office_id ?? 0);
                                    form.setData('claim_date', todayDate());
                                    form.setData('quantity_disputed', String(Math.trunc((firstItem?.quantity_missing ?? 0) + (firstItem?.quantity_damaged ?? 0))));
                                    form.setData('quantity_damaged', String(Math.trunc(firstItem?.quantity_damaged ?? 0)));
                                    form.setData('quantity_missing', String(Math.trunc(firstItem?.quantity_missing ?? 0)));
                                    setIssueLines(buildInitialIssueLines(firstItem));
                                }}
                            >
                                <option value="">Select Received Supply Request / Delivery Batch</option>
                                {groupedDistributionRecords.map((group) => (
                                    <optgroup
                                        key={group.requestCode}
                                        label={`${group.requestCode} — ${group.officeName}`}
                                    >
                                        {group.records.map((record, index) => {
                                            const batchPrefix = group.records.length > 1 ? `Batch ${index + 1}: ` : '';
                                            const itemsSummary = record.items && record.items.length > 0
                                                ? record.items.slice(0, 2).map((i) => `${Math.trunc(i.quantity_released)} ${i.unit || 'unit'} ${i.item_name}`).join(', ') + (record.items.length > 2 ? ` +${record.items.length - 2} more` : '')
                                                : 'No items recorded';

                                            return (
                                                <option
                                                    key={record.distribution_record_id}
                                                    value={record.distribution_record_id}
                                                    disabled={record.has_ongoing_claim}
                                                >
                                                    {batchPrefix}{record.distribution_code} ({formatDate(record.release_date)}) — {itemsSummary}
                                                    {record.has_ongoing_claim ? ` (Has Ongoing Claim: ${record.ongoing_claim_code} - Pending)` : ''}
                                                </option>
                                            );
                                        })}
                                    </optgroup>
                                ))}
                            </select>
                            {form.errors.distribution_record_id ? <p className="mt-1 text-xs text-rose-600">{form.errors.distribution_record_id}</p> : null}
                            {form.errors.parent_claim_id ? <p className="mt-1 text-xs text-rose-600">{form.errors.parent_claim_id}</p> : null}
                            {form.errors.source_type ? <p className="mt-1 text-xs text-rose-600">{form.errors.source_type}</p> : null}
                        </div>

                        {selectedRecord ? (
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <p><span className="font-semibold text-slate-800">Office:</span> {selectedRecord.office_name}</p>
                                        <p><span className="font-semibold text-slate-800">Supply Request:</span> {selectedRecord.request_code}</p>
                                        <p><span className="font-semibold text-slate-800">Distribution Voucher:</span> <span className="font-mono font-medium text-slate-900">{selectedRecord.distribution_code}</span></p>
                                    </div>
                                    <div>
                                        <p><span className="font-semibold text-slate-800">Delivered / Released:</span> {formatDate(selectedRecord.release_date)}</p>
                                        <p><span className="font-semibold text-slate-800">Receipt Status:</span> <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">{selectedRecord.status}</span></p>
                                        <p><span className="font-semibold text-slate-800">Delivered Items:</span> {selectedRecord.items?.length ?? 0} item line(s)</p>
                                    </div>
                                </div>
                                {selectedRecord.has_ongoing_claim ? (
                                    <div className="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                        <p className="font-bold">Ongoing Claim in Progress</p>
                                        <p className="mt-0.5">
                                            This supply request already has an ongoing claim ({selectedRecord.ongoing_claim_code}) that is not yet completed.
                                            Under GSO rules, you cannot file another claim for this request until the ongoing claim is resolved or rejected.
                                        </p>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date Observed</label>
                                <input
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    type="date"
                                    min={dateInputValue(selectedRecord?.release_date)}
                                    max={todayDate()}
                                    value={form.data.claim_date}
                                    onChange={(e) => form.setData('claim_date', e.target.value)}
                                />
                                <p className="mt-1 text-xs text-slate-500">Date observed cannot be before the supply release date or after today.</p>
                                {form.errors.claim_date ? <p className="mt-1 text-xs text-rose-600">{form.errors.claim_date}</p> : null}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Claim Location</label>
                                <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Receiving area, stock room, office desk" value={form.data.claim_location} onChange={(e) => form.setData('claim_location', e.target.value)} />
                                {form.errors.claim_location ? <p className="mt-1 text-xs text-rose-600">{form.errors.claim_location}</p> : null}
                            </div>
                        </div>

                        <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-semibold text-amber-900">Multiple affected items or issue types?</h3>
                                    <p className="text-xs text-amber-800">Add issue lines here. The system totals the claimed quantity automatically.</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {hasEmptyLine && (
                                        <span className="text-[11px] font-medium text-amber-800 bg-amber-100/70 border border-amber-200/80 rounded px-2 py-0.5">
                                            Select an item on current lines to add more
                                        </span>
                                    )}
                                    {!hasEmptyLine && allCombosUsed && selectedRecordItems.length > 0 && (
                                        <span className="text-[11px] font-medium text-slate-500 bg-slate-100 border border-slate-200 rounded px-2 py-0.5">
                                            All issue combinations added
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        className="rounded-lg border border-amber-400 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                        disabled={!canAddIssueLine}
                                        title={
                                            selectedRecordItems.length === 0
                                                ? 'Please select a received supply request first'
                                                : hasEmptyLine
                                                ? 'Select an item for all current issue lines before adding another'
                                                : allCombosUsed
                                                ? 'All possible issue combinations for these items have been added'
                                                : 'Add another issue line'
                                        }
                                        onClick={() => {
                                            if (!canAddIssueLine || !nextAvailableCombo) return;
                                            setValidationError(null);
                                            setIssueLines((lines) => [
                                                ...lines,
                                                createIssueLine(nextAvailableCombo.claim_type, '', nextAvailableCombo.distribution_item_id),
                                            ]);
                                        }}
                                    >
                                        Add Issue Line
                                    </button>
                                </div>
                            </div>

                            <div className="mt-3 space-y-3">
                                {issueLines.map((line, lineIndex) => {
                                    const lineItem = line.distribution_item_id
                                        ? selectedRecordItems.find((item) => item.distribution_item_id === Number(line.distribution_item_id)) ?? null
                                        : null;
                                    const itemReleasedQty = Math.trunc(lineItem?.quantity_released ?? 0);
                                    const lineMaxQuantity = (isFollowUpClaim && followUpMaxQuantity !== null)
                                        ? Math.min(itemReleasedQty || followUpMaxQuantity, followUpMaxQuantity)
                                        : itemReleasedQty;
                                    const lineIsDuplicate = Boolean(line.distribution_item_id) && isDuplicateCombo(line.distribution_item_id, line.claim_type, line.id);
                                    const itemTotal = line.distribution_item_id ? itemClaimedTotals[line.distribution_item_id] ?? 0 : 0;
                                    const itemOverclaimed = lineMaxQuantity > 0 && itemTotal > lineMaxQuantity;
                                    const lineMissingItem = !line.distribution_item_id;

                                    return (
                                        <div key={line.id} className="space-y-1.5 rounded-lg border border-amber-200 bg-white p-3">
                                            <div className="grid gap-2 sm:grid-cols-[1.35fr_1.1fr_0.65fr_auto]">
                                                <select
                                                    className={`w-full rounded-lg border px-3 py-2 text-sm ${
                                                        lineIsDuplicate
                                                            ? 'border-rose-400 bg-rose-50/50'
                                                            : lineMissingItem && validationError
                                                            ? 'border-rose-400 bg-rose-50/30'
                                                            : isFollowUpClaim
                                                            ? 'border-slate-200 bg-slate-100 text-slate-700 cursor-not-allowed'
                                                            : 'border-slate-300'
                                                    }`}
                                                    value={line.distribution_item_id}
                                                    disabled={isFollowUpClaim}
                                                    onChange={(e) => {
                                                        const newDistributionItemId = e.target.value;
                                                        setValidationError(null);
                                                        updateIssueLine(setIssueLines, line.id, { distribution_item_id: newDistributionItemId });
                                                        if (issueLines[0]?.id === line.id) {
                                                            form.setData('distribution_item_id', newDistributionItemId);
                                                        }
                                                    }}
                                                >
                                                    <option value="">Select item</option>
                                                    {selectedRecordItems.map((item) => {
                                                        const isUsed = isDuplicateCombo(item.distribution_item_id, line.claim_type, line.id);
                                                        return (
                                                            <option
                                                                key={item.distribution_item_id}
                                                                value={item.distribution_item_id}
                                                                disabled={isUsed}
                                                            >
                                                                {item.item_name} {isUsed ? '(Already added)' : ''}
                                                            </option>
                                                        );
                                                    })}
                                                </select>
                                                <select
                                                    className={`w-full rounded-lg border px-3 py-2 text-sm ${lineIsDuplicate ? 'border-rose-400 bg-rose-50/50' : 'border-slate-300'}`}
                                                    value={line.claim_type}
                                                    onChange={(e) => {
                                                        setValidationError(null);
                                                        updateIssueLine(setIssueLines, line.id, { claim_type: e.target.value });
                                                    }}
                                                >
                                                    {claimTypeOptions.map((option) => {
                                                        const isUsed = isDuplicateCombo(line.distribution_item_id, option.value, line.id);
                                                        return (
                                                            <option
                                                                key={option.value}
                                                                value={option.value}
                                                                disabled={isUsed}
                                                            >
                                                                {option.label} {isUsed ? '(Already added)' : ''}
                                                            </option>
                                                        );
                                                    })}
                                                </select>
                                                <input
                                                    className={`w-full rounded-lg border px-3 py-2 text-sm ${itemOverclaimed ? 'border-rose-400 bg-rose-50/50' : 'border-slate-300'}`}
                                                    type="text"
                                                    inputMode="numeric"
                                                    pattern="[0-9]*"
                                                    placeholder="Qty"
                                                    value={line.quantity}
                                                    onChange={(e) => {
                                                        setValidationError(null);
                                                        const wholeQuantity = e.target.value.replace(/\D/g, '');
                                                        const cappedQuantity = lineMaxQuantity > 0 && Number(wholeQuantity) > lineMaxQuantity ? String(lineMaxQuantity) : wholeQuantity;

                                                        updateIssueLine(setIssueLines, line.id, { quantity: cappedQuantity });
                                                    }}
                                                />
                                                <button
                                                    type="button"
                                                    className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                                                    disabled={issueLines.length === 1}
                                                    onClick={() => {
                                                        setValidationError(null);
                                                        setIssueLines((lines) => lines.filter((current) => current.id !== line.id));
                                                    }}
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                            {lineIsDuplicate && (
                                                <p className="text-[11px] font-semibold text-rose-600">
                                                    ⚠ Duplicate issue: An issue line for this item with reason "{claimTypeOptions.find((o) => o.value === line.claim_type)?.label ?? line.claim_type}" is already added.
                                                </p>
                                            )}
                                            {lineMissingItem && validationError && (
                                                <p className="text-[11px] font-semibold text-rose-600">
                                                    ⚠ Line #{lineIndex + 1}: Please select an item.
                                                </p>
                                            )}
                                            {itemOverclaimed && (
                                                <p className="text-[11px] font-semibold text-rose-600">
                                                    ⚠ Total claimed quantity for {lineItem?.item_name} ({itemTotal} {lineItem?.unit ?? ''}) exceeds released quantity ({lineMaxQuantity} {lineItem?.unit ?? ''}).
                                                </p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-amber-200/80 pt-3">
                                <p className="text-xs font-semibold text-amber-900">
                                    Total claimed quantity: {formatQuantity(totalIssueQuantity)}{distinctItemIds.length <= 1 && selectedItem ? ` ${selectedItem.unit}` : ' total'}
                                    {derivedClaimType ? ` - Claim type: ${displayClaimType(derivedClaimType)}` : ''}
                                </p>
                                {distinctItemIds.length <= 1 && selectedItem ? (
                                    <span className="text-[11px] text-amber-800">
                                        Released quantity for {selectedItem.item_name}: {formatQuantity(selectedItemReleasedQuantity)} {selectedItem.unit}
                                    </span>
                                ) : distinctItemIds.length > 1 ? (
                                    <span className="text-[11px] font-medium text-amber-800">
                                        Filing claims for {distinctItemIds.length} items
                                    </span>
                                ) : null}
                            </div>
                            {form.errors.claim_type ? <p className="mt-1 text-xs text-rose-600 sm:col-span-3">{form.errors.claim_type}</p> : null}
                            {form.errors.distribution_item_id ? <p className="mt-1 text-xs text-rose-600 sm:col-span-3">{form.errors.distribution_item_id}</p> : null}
                            {form.errors.quantity_disputed ? <p className="mt-1 text-xs text-rose-600 sm:col-span-3">{form.errors.quantity_disputed}</p> : null}
                            {form.errors.quantity_damaged ? <p className="mt-1 text-xs text-rose-600">{form.errors.quantity_damaged}</p> : null}
                            {form.errors.quantity_missing ? <p className="mt-1 text-xs text-rose-600">{form.errors.quantity_missing}</p> : null}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">Discrepancy Details & Findings <span className="text-rose-600 font-bold">* Required</span></label>
                            <textarea
                                className={`min-h-[90px] w-full rounded-lg border px-3 py-2 text-sm ${validationError && !form.data.remarks.trim() ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300'}`}
                                placeholder="Describe what was observed and what quantity/item was affected."
                                value={form.data.remarks}
                                onChange={(e) => {
                                    setValidationError(null);
                                    form.setData('remarks', e.target.value);
                                }}
                            />
                            {form.errors.remarks ? <p className="mt-1 text-xs text-rose-600">{form.errors.remarks}</p> : null}
                        </div>

                        {derivedClaimType ? (
                            <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
                                <p className="text-sm font-semibold text-rose-800">⚠ Evidence Required</p>
                                <p className="mt-1 text-xs text-rose-700">
                                    Photo or document evidence is <strong>required</strong> for {displayClaimType(derivedClaimType)} claims. Please attach a file showing the issue (e.g., photo of damaged item, count discrepancy record).
                                </p>
                            </div>
                        ) : null}

                        <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                            <div className="flex items-center justify-between">
                                <label className="block text-xs font-semibold uppercase tracking-wide text-slate-700">
                                    Evidence Attachments {derivedClaimType ? <span className="text-rose-600 font-bold">* Required</span> : '(Optional)'}
                                </label>
                                <span className="text-[11px] text-slate-500">Max 10MB per file (PDF, PNG, JPG, WEBP, DOC)</span>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-600">Attachment Title</label>
                                    <input
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        placeholder="E.g. Inspection photos, discrepancy memo"
                                        value={form.data.attachment_title}
                                        onChange={(e) => form.setData('attachment_title', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-600">Upload Files</label>
                                    <input
                                        type="file"
                                        multiple
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-red-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-red-700 hover:file:bg-red-100"
                                        onChange={(e) => {
                                            const newFiles = Array.from(e.target.files ?? []);
                                            const validFiles = newFiles.filter((f) => f.size <= 10 * 1024 * 1024);
                                            if (validFiles.length < newFiles.length) {
                                                setFileWarning('Some files exceed the 10MB limit and were skipped.');
                                            } else {
                                                setFileWarning(null);
                                            }
                                            const combined = [...form.data.attachments, ...validFiles];
                                            form.setData('attachments', combined);
                                            form.setData('attachment_file', combined[0] ?? null);
                                        }}
                                    />
                                    {fileWarning && (
                                        <div className="mt-2 flex items-center justify-between rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                            <span className="flex items-center gap-1.5 font-medium">
                                                ⚠ {fileWarning}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setFileWarning(null)}
                                                className="ml-2 font-bold text-amber-700 hover:text-amber-950"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {form.data.attachments.length > 0 && (
                                <div className="space-y-1.5 pt-2">
                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Selected Files ({form.data.attachments.length}):</p>
                                    <div className="grid gap-1.5 sm:grid-cols-2">
                                        {form.data.attachments.map((file, idx) => (
                                            <div key={idx} className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs shadow-2xs">
                                                <div className="truncate pr-2">
                                                    <p className="truncate font-medium text-slate-800">{file.name}</p>
                                                    <p className="text-[10px] text-slate-400">{(file.size / 1024).toFixed(0)} KB</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        const updated = form.data.attachments.filter((_, i) => i !== idx);
                                                        form.setData('attachments', updated);
                                                        form.setData('attachment_file', updated[0] ?? null);
                                                    }}
                                                    className="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                                    title="Remove attachment"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {form.errors.attachment_file ? <p className="text-xs text-rose-600">{form.errors.attachment_file}</p> : null}
                        </div>

                        {validationError && (
                            <div
                                id="claim-validation-banner"
                                className="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50/90 p-4 text-sm text-rose-800 shadow-xs"
                            >
                                <svg className="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div className="flex-1">
                                    <p className="font-semibold text-rose-900">Submission Blocked</p>
                                    <p className="mt-0.5 text-xs text-rose-700">{validationError}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setValidationError(null)}
                                    className="p-1 text-xs font-semibold text-rose-500 hover:text-rose-800"
                                    title="Dismiss error"
                                >
                                    ✕
                                </button>
                            </div>
                        )}

                        {form.hasErrors ? <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">Please review the highlighted claim form fields before submitting.</p> : null}

                        <input type="hidden" value={form.data.office_id} readOnly />
                        <input type="hidden" value={form.data.parent_claim_id} readOnly />
                        <input type="hidden" value={form.data.source_type} readOnly />

                        <button type="submit" disabled={form.processing || Boolean(selectedRecord?.has_ongoing_claim)} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60">
                            {form.processing ? 'Submitting Claim...' : isFollowUpClaim ? 'Submit Follow-up Claim' : 'Submit Claim'}
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatQuantity(value: number): string {
    return new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(Math.trunc(value));
}

function toWholeQuantity(value: number | string): number {
    return Math.max(Number.parseInt(String(value || 0), 10) || 0, 0);
}

function createIssueLine(claimType: string, quantity = '', distributionItemId: number | string = ''): IssueLine {
    return {
        id: Date.now() + Math.floor(Math.random() * 1000),
        distribution_item_id: String(distributionItemId),
        claim_type: claimType,
        quantity,
    };
}

function buildInitialIssueLines(item: DistributionItemOption | null, isFollowUp = false): IssueLine[] {
    const missingQuantity = Math.trunc(item?.quantity_missing ?? 0);
    const damagedQuantity = Math.trunc(item?.quantity_damaged ?? 0);
    const lines: IssueLine[] = [];

    if (!isFollowUp) {
        if (missingQuantity > 0) {
            lines.push(createIssueLine('MISSING_ITEM', String(missingQuantity), item?.distribution_item_id ?? ''));
        }

        if (damagedQuantity > 0) {
            lines.push(createIssueLine('DAMAGED_ITEM', String(damagedQuantity), item?.distribution_item_id ?? ''));
        }
    }

    return lines.length > 0 ? lines : [createIssueLine('MISSING_ITEM', '', item?.distribution_item_id ?? '')];
}

function updateIssueLine(setIssueLines: Dispatch<SetStateAction<IssueLine[]>>, id: number, patch: Partial<IssueLine>): void {
    setIssueLines((lines) => lines.map((line) => (line.id === id ? { ...line, ...patch } : line)));
}

function getClaimTypeFromIssueLines(issueLines: IssueLine[]): string {
    const activeTypes = new Set(issueLines.filter((line) => toWholeQuantity(line.quantity) > 0).map((line) => line.claim_type));

    if (activeTypes.has('MISSING_ITEM') && activeTypes.has('DAMAGED_ITEM')) {
        return 'MISSING_AND_DAMAGED';
    }

    const [firstType] = Array.from(activeTypes);

    return firstType || '';
}

function displayClaimType(type: string): string {
    if (type === 'MISSING_AND_DAMAGED') {
        return 'Missing and Damaged';
    }

    if (type === 'DAMAGED_ITEM') {
        return 'Damaged Item';
    }

    return claimTypeOptions.find((option) => option.value === type)?.label ?? type;
}
