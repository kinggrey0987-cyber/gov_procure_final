import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

type Props = {
    claim: {
        claims_id: number;
        claim_code: string;
        distribution_item_id?: number | null;
        status: string;
        claim_type: string;
        claim_date: string;
        claim_location: string;
        quantity_disputed?: number;
        quantity_damaged?: number;
        quantity_missing?: number;
        resolution_type?: string | null;
        replacement_quantity?: number;
        replacement_available_stock?: number | null;
        remarks?: string | null;
    };
    linkedDistribution?: {
        distribution_record_id: number;
        distribution_code: string;
        status: string;
        release_date?: string | null;
        office_name: string;
        request_code: string;
        items: Array<{
            distribution_item_id: number;
            item_name: string;
            category_name: string;
            quantity_released: number;
            quantity_received?: number;
            quantity_damaged?: number;
            quantity_missing?: number;
            unit: string;
        }>;
    } | null;
    attachments: Array<{
        document_id: number;
        title: string;
        file_name: string;
        uploaded_at?: string | null;
        uploader_name?: string | null;
    }>;
    attachmentPermissions: {
        upload: boolean;
        delete: boolean;
    };
    statusHistory: Array<{
        id: number;
        old_status?: string | null;
        new_status: string;
        remarks?: string | null;
        changed_at?: string | null;
        changed_by?: { id: number; name: string } | null;
    }>;
    approvalHistory: Array<{
        id: number;
        status: string;
        remarks?: string | null;
        approved_at?: string | null;
        approver?: { id: number; name: string } | null;
    }>;
    routes?: {
        index: string;
        show: string;
        updateStatus?: string;
    };
    permissions?: {
        update: boolean;
    };
};

export default function ClaimShow({ claim, linkedDistribution, attachments, attachmentPermissions, statusHistory, approvalHistory, routes, permissions }: Props) {
    const [activeTab, setActiveTab] = useState<'overview' | 'items' | 'status' | 'related' | 'notes'>('overview');
    const routeNames = routes ?? {
        index: 'inventory.claims.index',
        show: 'inventory.claims.show',
        updateStatus: 'inventory.claims.update-status',
    };
    const nextStatusOptions = getNextStatusOptions(claim.status);
    const canUpdate = (permissions?.update ?? false) && nextStatusOptions.length > 0;
    const statusForm = useForm({
        status: nextStatusOptions[0]?.value ?? '',
        resolution_type: '',
        replacement_quantity: '',
        remarks: '',
    });
    const needsResolution = statusForm.data.status !== '' && statusForm.data.status !== 'IN_REVIEW';
    const usesReplacement = ['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT'].includes(statusForm.data.resolution_type);
    const claimedItems = linkedDistribution?.items.filter((item) => {
        if (!claim.distribution_item_id) {
            return true;
        }

        return item.distribution_item_id === claim.distribution_item_id;
    }) ?? [];
    const hasSpecificClaimedItem = claim.distribution_item_id !== null && claim.distribution_item_id !== undefined;
    const claimedIssueQuantities = getClaimIssueQuantities(claim);

    useEffect(() => {
        statusForm.setData('status', nextStatusOptions[0]?.value ?? '');
        statusForm.setData('resolution_type', '');
        statusForm.setData('replacement_quantity', '');
        statusForm.clearErrors();
    }, [claim.status]);

    const submitStatusUpdate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!routeNames.updateStatus) {
            return;
        }

        statusForm.put(route(routeNames.updateStatus, claim.claims_id), {
            preserveScroll: true,
            onSuccess: () => statusForm.reset('resolution_type', 'replacement_quantity', 'remarks'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Claim Detail</h2>
                    <Link href={route(routeNames.index)} className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to Claims and Adjustments
                    </Link>
                </div>
            }
        >
            <Head title="Claim Detail" />
            <div className="space-y-4 py-8">
                <section className="rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="grid grid-cols-2 gap-2 md:grid-cols-5">
                        {[
                            { key: 'overview', label: 'Overview' },
                            { key: 'items', label: 'Items' },
                            { key: 'status', label: 'Status History' },
                            { key: 'related', label: 'Related Records' },
                            { key: 'notes', label: 'Notes/Attachments' },
                        ].map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key as 'overview' | 'items' | 'status' | 'related' | 'notes')}
                                className={`rounded-lg px-3 py-2 text-xs font-semibold md:text-sm ${activeTab === tab.key ? 'bg-red-700 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </section>

                {activeTab === 'overview' ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Claim Summary</h3>
                    <div className="mt-3 grid gap-4 md:grid-cols-2">
                        <Info label="Claim Code" value={claim.claim_code} mono />
                        <Info label="Status" value={displayClaimStatus(claim.status)} />
                        <Info label="Issue Type" value={displayClaimType(claim.claim_type)} />
                        <Info label="Date Observed" value={formatDate(claim.claim_date)} />
                        <Info label="Issue Location" value={claim.claim_location} />
                        <Info label="Disputed Quantity" value={formatQuantity(claim.quantity_disputed ?? 0)} />
                        <Info label="Claimed Damaged Qty" value={formatQuantity(claimedIssueQuantities.damaged)} />
                        <Info label="Claimed Missing Qty" value={formatQuantity(claimedIssueQuantities.missing)} />
                        <Info label="Resolution Type" value={claim.resolution_type ?? 'Pending review'} />
                        <Info label="Replacement Released" value={formatQuantity(claim.replacement_quantity ?? 0)} />
                        <Info label="Available Replacement Stock" value={claim.replacement_available_stock === null || claim.replacement_available_stock === undefined ? '-' : formatQuantity(claim.replacement_available_stock)} />
                        <Info label="Remarks" value={claim.remarks ?? 'No remarks provided.'} />
                    </div>
                    {permissions?.update ? (
                        <div className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">GSO Claim Follow-up</h3>
                            {canUpdate ? (
                                <form onSubmit={submitStatusUpdate} className="mt-3 grid gap-3 md:grid-cols-[minmax(180px,240px)_minmax(200px,260px)_minmax(140px,180px)_1fr_auto] md:items-start">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Next Status</label>
                                        <select
                                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                            value={statusForm.data.status}
                                            onChange={(event) => statusForm.setData('status', event.target.value)}
                                        >
                                            {nextStatusOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                        {statusForm.errors.status ? <p className="mt-1 text-xs text-rose-600">{statusForm.errors.status}</p> : null}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Resolution Type</label>
                                        <select
                                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-100"
                                            value={statusForm.data.resolution_type}
                                            disabled={!needsResolution}
                                            onChange={(event) => {
                                                statusForm.setData('resolution_type', event.target.value);
                                                if (!['REPLACEMENT_RELEASED', 'PARTIAL_REPLACEMENT'].includes(event.target.value)) {
                                                    statusForm.setData('replacement_quantity', '');
                                                }
                                            }}
                                        >
                                            <option value="">{needsResolution ? 'Select resolution' : 'Not required'}</option>
                                            {getResolutionTypeOptions(statusForm.data.status).map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                        {statusForm.errors.resolution_type ? <p className="mt-1 text-xs text-rose-600">{statusForm.errors.resolution_type}</p> : null}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Replacement Qty</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            inputMode="numeric"
                                            pattern="[0-9]*"
                                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-100"
                                            value={statusForm.data.replacement_quantity}
                                            disabled={!usesReplacement}
                                            onChange={(event) => statusForm.setData('replacement_quantity', event.target.value.replace(/\D/g, ''))}
                                        />
                                        {statusForm.errors.replacement_quantity ? <p className="mt-1 text-xs text-rose-600">{statusForm.errors.replacement_quantity}</p> : null}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Follow-up Remarks</label>
                                        <textarea
                                            className="min-h-20 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                            value={statusForm.data.remarks}
                                            onChange={(event) => statusForm.setData('remarks', event.target.value)}
                                            placeholder="Record investigation notes, resolution details, or rejection reason."
                                        />
                                        {statusForm.errors.remarks ? <p className="mt-1 text-xs text-rose-600">{statusForm.errors.remarks}</p> : null}
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={statusForm.processing || statusForm.data.status === ''}
                                        className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 md:mt-5"
                                    >
                                        {statusForm.processing ? 'Saving...' : 'Save Follow-up'}
                                    </button>
                                </form>
                            ) : (
                                <p className="mt-2 text-sm text-slate-600">This claim has already been closed and can only be viewed.</p>
                            )}
                        </div>
                    ) : null}
                    </div>
                ) : null}

                {activeTab === 'related' && linkedDistribution ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Linked Distribution Record</h3>
                        <div className="mt-3 grid gap-4 md:grid-cols-2">
                            <Info label="Distribution Code" value={linkedDistribution.distribution_code} mono />
                            <Info label="Distribution Status" value={linkedDistribution.status} />
                            <Info label="Office" value={linkedDistribution.office_name} />
                            <Info label="Office Request" value={linkedDistribution.request_code} mono />
                            <Info label="Release Date" value={formatDate(linkedDistribution.release_date)} />
                            <Info label="Record ID" value={String(linkedDistribution.distribution_record_id)} />
                        </div>

                        <div className="mt-4 overflow-hidden rounded-lg border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Category</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Released Qty</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Received Qty</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Damaged Qty</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Missing Qty</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Unit</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {linkedDistribution.items.map((item) => (
                                        <tr key={item.distribution_item_id} className="hover:bg-slate-50/60">
                                            <td className="px-4 py-2 text-slate-700">{item.item_name}</td>
                                            <td className="px-4 py-2 text-slate-600">{item.category_name || '-'}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-slate-700">{formatQuantity(item.quantity_released)}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-emerald-700">{formatQuantity(item.quantity_received ?? 0)}</td>
                                            <td className="px-4 py-2 text-right text-rose-600">{formatQuantity(item.quantity_damaged ?? 0)}</td>
                                            <td className="px-4 py-2 text-right text-amber-600">{formatQuantity(item.quantity_missing ?? 0)}</td>
                                            <td className="px-4 py-2 text-slate-700">{item.unit}</td>
                                        </tr>
                                    ))}
                                    {linkedDistribution.items.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-6 text-center text-sm text-slate-500">
                                                No released item lines available for this distribution record.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                {activeTab === 'notes' ? (
                    <AttachmentsPanel
                        title="Claim Attachments"
                        attachments={attachments}
                        uploadRoute={route('documents.store', {
                            related_module: 'claims',
                            related_reference_id: claim.claims_id,
                        })}
                        canUpload={attachmentPermissions.upload}
                        canDelete={attachmentPermissions.delete}
                        downloadRouteFor={(documentId: number) => route('documents.show', documentId)}
                        deleteRouteFor={(documentId: number) => route('documents.destroy', documentId)}
                    />
                ) : null}

                {activeTab === 'status' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Workflow History</h3>
                        <div className="mt-4 space-y-3">
                            {statusHistory.map((entry) => (
                                <div key={entry.id} className="rounded-lg border border-slate-200 p-3">
                                    <p className="text-sm font-semibold text-slate-800">
                                        {entry.old_status ? `${displayClaimStatus(entry.old_status)} to ` : ''}{displayClaimStatus(entry.new_status)}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {formatDateTime(entry.changed_at)} by {entry.changed_by?.name ?? 'System'}
                                    </p>
                                    {entry.remarks ? <p className="mt-2 text-sm text-slate-600">{entry.remarks}</p> : null}
                                </div>
                            ))}
                            {statusHistory.length === 0 ? <p className="text-sm text-slate-600">No status history has been recorded.</p> : null}
                        </div>
                    </section>
                ) : null}

                {activeTab === 'items' ? (
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-4 py-3">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Claimed Items</h3>
                            <p className="mt-1 text-xs text-slate-500">
                                {hasSpecificClaimedItem
                                    ? 'Showing the released item selected when this claim was filed.'
                                    : 'No specific item was selected for this claim, so the linked distribution item lines are shown.'}
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Released Qty</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Received Qty</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Claimed Damaged Qty</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Claimed Missing Qty</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Disputed Qty</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {claimedItems.map((item) => {
                                        const rowClaimedDamaged = hasSpecificClaimedItem ? formatQuantity(claimedIssueQuantities.damaged) : '-';
                                        const rowClaimedMissing = hasSpecificClaimedItem ? formatQuantity(claimedIssueQuantities.missing) : '-';

                                        return (
                                            <tr key={item.distribution_item_id} className="hover:bg-slate-50/60">
                                                <td className="px-4 py-3 text-slate-700">{item.item_name}</td>
                                                <td className="px-4 py-3 text-slate-600">{item.category_name || '-'}</td>
                                                <td className="px-4 py-3 text-right font-semibold text-slate-700">{formatQuantity(item.quantity_released)}</td>
                                                <td className="px-4 py-3 text-right font-semibold text-emerald-700">{formatQuantity(item.quantity_received ?? 0)}</td>
                                                <td className="px-4 py-3 text-right text-rose-600">{rowClaimedDamaged}</td>
                                                <td className="px-4 py-3 text-right text-amber-600">{rowClaimedMissing}</td>
                                                <td className="px-4 py-3 text-right font-semibold text-red-700">
                                                    {hasSpecificClaimedItem ? formatQuantity(claim.quantity_disputed ?? 0) : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-slate-700">{item.unit}</td>
                                            </tr>
                                        );
                                    })}
                                    {claimedItems.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-8 text-center text-sm text-slate-500">
                                                No item line is linked to this claim.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-1 text-sm text-slate-800 ${mono ? 'font-mono text-xs' : ''}`}>{value}</p>
        </div>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const datePart = value.match(/^\d{4}-\d{2}-\d{2}/)?.[0];
    const parsed = new Date(datePart ? `${datePart}T00:00:00` : value);

    return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function displayClaimType(type: string): string {
    const map: Record<string, string> = {
        MISSING_ITEM: 'Missing Item',
        DAMAGED_ITEM: 'Damaged Item',
        QUANTITY_MISMATCH: 'Quantity Mismatch',
        WRONG_ITEM_RECEIVED: 'Wrong Item Received',
        MISSING_AND_DAMAGED: 'Missing and Damaged',
        SHORTAGE: 'Shortage',
    };

    return map[type] ?? humanizeEnum(type);
}

function displayClaimStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        IN_REVIEW: 'In Claim Review',
        PENDING_REPLACEMENT: 'Pending Replacement',
        PARTIALLY_RESOLVED: 'Partially Resolved',
        RESOLVED: 'Resolved',
        REJECTED: 'Rejected',
    };

    return map[status] ?? humanizeEnum(status);
}

function getClaimIssueQuantities(claim: Props['claim']): { damaged: number; missing: number } {
    const damaged = claim.quantity_damaged ?? 0;
    const missing = claim.quantity_missing ?? 0;

    if (damaged > 0 || missing > 0) {
        return { damaged, missing };
    }

    const disputedQuantity = claim.quantity_disputed ?? 0;
    if (claim.claim_type === 'DAMAGED_ITEM') {
        return { damaged: disputedQuantity, missing: 0 };
    }

    if (['MISSING_ITEM', 'SHORTAGE'].includes(claim.claim_type)) {
        return { damaged: 0, missing: disputedQuantity };
    }

    return { damaged: 0, missing: 0 };
}

function formatQuantity(value: number): string {
    return new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(Math.trunc(value));
}

function getNextStatusOptions(status: string): Array<{ value: string; label: string }> {
    const transitions: Record<string, string[]> = {
        OPEN: ['IN_REVIEW', 'PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED', 'RESOLVED', 'REJECTED'],
        IN_REVIEW: ['PENDING_REPLACEMENT', 'PARTIALLY_RESOLVED', 'RESOLVED', 'REJECTED'],
        PENDING_REPLACEMENT: ['PARTIALLY_RESOLVED', 'RESOLVED', 'REJECTED'],
        PARTIALLY_RESOLVED: ['PENDING_REPLACEMENT', 'RESOLVED', 'REJECTED'],
    };

    return (transitions[status] ?? []).map((value) => ({
        value,
        label: displayClaimStatus(value),
    }));
}

function getResolutionTypeOptions(status: string): Array<{ value: string; label: string }> {
    const common = [
        { value: 'NO_STOCK_ACTION', label: 'No Stock Action' },
        { value: 'COUNT_CORRECTED', label: 'Count Corrected' },
        { value: 'OTHER_RESOLUTION', label: 'Other Resolution' },
    ];

    if (status === 'PENDING_REPLACEMENT') {
        return [{ value: 'PENDING_REPLACEMENT', label: 'Pending Replacement' }];
    }

    if (status === 'PARTIALLY_RESOLVED') {
        return [{ value: 'PARTIAL_REPLACEMENT', label: 'Partial Replacement' }];
    }

    if (status === 'RESOLVED') {
        return [{ value: 'REPLACEMENT_RELEASED', label: 'Replacement Released' }, ...common];
    }

    if (status === 'REJECTED') {
        return common;
    }

    return [];
}
