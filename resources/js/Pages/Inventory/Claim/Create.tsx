import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

type DistributionRecordOption = {
    distribution_record_id: number;
    distribution_code: string;
    office_id: number;
    office_name: string;
    release_date?: string | null;
    status: string;
    request_code: string;
    items?: Array<{
        distribution_item_id: number;
        item_id: number;
        item_name: string;
        quantity_released: number;
        quantity_received: number;
        quantity_damaged: number;
        quantity_missing: number;
        unit: string;
    }>;
};

type Props = {
    distributionRecords: DistributionRecordOption[];
    preselectedDistributionRecordId?: number | null;
};

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

export default function ClaimCreate({ distributionRecords, preselectedDistributionRecordId = null }: Props) {
    const { auth } = usePage<PageProps>().props;
    const isOfficeUser = auth?.user?.role_code === 'OFFICE_USER';
    const preselectedRecord = preselectedDistributionRecordId
        ? distributionRecords.find((record) => record.distribution_record_id === preselectedDistributionRecordId)
        : null;
    const first = preselectedRecord ?? distributionRecords[0];
    const form = useForm({
        distribution_record_id: first?.distribution_record_id ?? '',
        distribution_item_id: '',
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
    });

    const selectedRecord = useMemo(
        () => distributionRecords.find((record) => record.distribution_record_id === Number(form.data.distribution_record_id)),
        [distributionRecords, form.data.distribution_record_id],
    );
    const selectedItem = useMemo(
        () => selectedRecord?.items?.find((item) => item.distribution_item_id === Number(form.data.distribution_item_id)) ?? null,
        [selectedRecord, form.data.distribution_item_id],
    );
    const selectedItemReleasedQuantity = Math.trunc(selectedItem?.quantity_released ?? 0);
    const damagedQuantity = toWholeQuantity(form.data.quantity_damaged);
    const missingQuantity = toWholeQuantity(form.data.quantity_missing);
    const disputedQuantity = damagedQuantity + missingQuantity;
    const derivedClaimType = getClaimTypeFromQuantities(damagedQuantity, missingQuantity, form.data.claim_type);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">File Claim</h2>}>
            <Head title="File Claim" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-sm text-slate-600">Claims are post-distribution issue records. Select an existing released or received distribution record first.</p>
                        <p className="mt-2 text-xs text-slate-500">Typical claim types: missing items, damaged items, quantity mismatch, or wrong item received.</p>
                    </div>

                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                claim_type: derivedClaimType,
                                quantity_disputed: String(disputedQuantity),
                            }));
                            form.post(route(isOfficeUser ? 'office.claims.store' : 'inventory.claims.store'), { forceFormData: true });
                        }}
                    >
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Select Released Distribution Record</label>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.distribution_record_id}
                                onChange={(e) => {
                                    const selectedRecordId = Number(e.target.value);
                                    const selected = distributionRecords.find((record) => record.distribution_record_id === selectedRecordId);
                                    form.setData('distribution_record_id', selectedRecordId);
                                    form.setData('distribution_item_id', '');
                                    form.setData('office_id', selected?.office_id ?? 0);
                                    form.setData('claim_date', todayDate());
                                    form.setData('quantity_disputed', '');
                                    form.setData('quantity_damaged', '');
                                    form.setData('quantity_missing', '');
                                }}
                            >
                                <option value="">Select Distribution Record</option>
                                {distributionRecords.map((record) => (
                                    <option key={record.distribution_record_id} value={record.distribution_record_id}>
                                        {record.distribution_code} - {record.office_name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.distribution_record_id ? <p className="mt-1 text-xs text-rose-600">{form.errors.distribution_record_id}</p> : null}
                        </div>

                        {selectedRecord ? (
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <p><span className="font-semibold text-slate-800">Office:</span> {selectedRecord.office_name}</p>
                                <p><span className="font-semibold text-slate-800">Distribution Code:</span> {selectedRecord.distribution_code}</p>
                                <p><span className="font-semibold text-slate-800">Linked Request:</span> {selectedRecord.request_code}</p>
                                <p><span className="font-semibold text-slate-800">Release Date:</span> {formatDate(selectedRecord.release_date)}</p>
                                <p><span className="font-semibold text-slate-800">Distribution Status:</span> {selectedRecord.status}</p>
                            </div>
                        ) : null}

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Affected Released Item</label>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.distribution_item_id}
                                onChange={(e) => {
                                    const distributionItemId = e.target.value;
                                    const nextItem = selectedRecord?.items?.find((item) => item.distribution_item_id === Number(distributionItemId));
                                    form.setData('distribution_item_id', distributionItemId);
                                    form.setData('quantity_disputed', String(Math.trunc((nextItem?.quantity_missing ?? 0) + (nextItem?.quantity_damaged ?? 0))));
                                    form.setData('quantity_damaged', String(Math.trunc(nextItem?.quantity_damaged ?? 0)));
                                    form.setData('quantity_missing', String(Math.trunc(nextItem?.quantity_missing ?? 0)));
                                }}
                            >
                                <option value="">Select affected released item</option>
                                {(selectedRecord?.items ?? []).map((item) => (
                                    <option key={item.distribution_item_id} value={item.distribution_item_id}>
                                        {item.item_name} - released {item.quantity_released} {item.unit}
                                    </option>
                                ))}
                            </select>
                            {form.errors.distribution_item_id ? <p className="mt-1 text-xs text-rose-600">{form.errors.distribution_item_id}</p> : null}
                            {selectedItem ? (
                                <p className="mt-2 text-xs text-slate-500">
                                    Received: {formatQuantity(selectedItem.quantity_received)} {selectedItem.unit} · Damaged: {formatQuantity(selectedItem.quantity_damaged)} · Missing: {formatQuantity(selectedItem.quantity_missing)}
                                </p>
                            ) : null}
                        </div>

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

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Missing Qty</label>
                                <input
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    type="number"
                                    min="0"
                                    max={selectedItem ? selectedItemReleasedQuantity : undefined}
                                    step="1"
                                    value={form.data.quantity_missing}
                                    onChange={(e) => form.setData('quantity_missing', e.target.value.replace(/\D/g, ''))}
                                />
                                {form.errors.quantity_missing ? <p className="mt-1 text-xs text-rose-600">{form.errors.quantity_missing}</p> : null}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Damaged Qty</label>
                                <input
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    type="number"
                                    min="0"
                                    max={selectedItem ? selectedItemReleasedQuantity : undefined}
                                    step="1"
                                    value={form.data.quantity_damaged}
                                    onChange={(e) => form.setData('quantity_damaged', e.target.value.replace(/\D/g, ''))}
                                />
                                {form.errors.quantity_damaged ? <p className="mt-1 text-xs text-rose-600">{form.errors.quantity_damaged}</p> : null}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Disputed Qty</label>
                                <input
                                    className="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700"
                                    type="number"
                                    min="0"
                                    max={selectedItem ? selectedItemReleasedQuantity : undefined}
                                    step="1"
                                    value={disputedQuantity}
                                    readOnly
                                />
                            </div>
                            {form.errors.claim_type ? <p className="mt-1 text-xs text-rose-600 sm:col-span-3">{form.errors.claim_type}</p> : null}
                            {form.errors.quantity_disputed ? <p className="mt-1 text-xs text-rose-600 sm:col-span-3">{form.errors.quantity_disputed}</p> : null}
                            {selectedItem ? (
                                <p className="mt-1 text-xs text-slate-500 sm:col-span-3">
                                    Maximum for this claim: {formatQuantity(selectedItemReleasedQuantity)} {selectedItem.unit}.
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Description / Remarks</label>
                            <textarea className="min-h-[90px] w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Describe what was observed and what quantity/item was affected." value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                            {form.errors.remarks ? <p className="mt-1 text-xs text-rose-600">{form.errors.remarks}</p> : null}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Attachment Title (Optional)</label>
                                <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Photo evidence or issue report" value={form.data.attachment_title} onChange={(e) => form.setData('attachment_title', e.target.value)} />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Attachment File (Optional)</label>
                                <input
                                    type="file"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    onChange={(e) => form.setData('attachment_file', e.target.files?.[0] ?? null)}
                                />
                            </div>
                        </div>

                        {form.hasErrors ? <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">Please review the highlighted claim form fields before submitting.</p> : null}

                        <input type="hidden" value={form.data.office_id} readOnly />

                        <button type="submit" disabled={form.processing} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60">
                            {form.processing ? 'Submitting Claim...' : 'Submit Claim'}
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

function getClaimTypeFromQuantities(damagedQuantity: number, missingQuantity: number, fallback: string): string {
    if (damagedQuantity > 0 && missingQuantity > 0) {
        return 'MISSING_AND_DAMAGED';
    }

    if (damagedQuantity > 0) {
        return 'DAMAGED_ITEM';
    }

    if (missingQuantity > 0) {
        return 'MISSING_ITEM';
    }

    return fallback || 'QUANTITY_MISMATCH';
}
