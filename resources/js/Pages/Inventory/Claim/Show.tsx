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
        status: string;
        claim_type: string;
        claim_date: string;
        claim_location: string;
        quantity_disputed?: number;
        resolution_type?: string | null;
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
        remarks: '',
    });

    useEffect(() => {
        statusForm.setData('status', nextStatusOptions[0]?.value ?? '');
        statusForm.clearErrors();
    }, [claim.status]);

    const submitStatusUpdate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!routeNames.updateStatus) {
            return;
        }

        statusForm.put(route(routeNames.updateStatus, claim.claims_id), {
            preserveScroll: true,
            onSuccess: () => statusForm.reset('remarks'),
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
                        <Info label="Disputed Quantity" value={String(claim.quantity_disputed ?? 0)} />
                        <Info label="Resolution Type" value={claim.resolution_type ?? 'Pending review'} />
                        <Info label="Remarks" value={claim.remarks ?? 'No remarks provided.'} />
                    </div>
                    {permissions?.update ? (
                        <div className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">GSO Claim Follow-up</h3>
                            {canUpdate ? (
                                <form onSubmit={submitStatusUpdate} className="mt-3 grid gap-3 md:grid-cols-[minmax(180px,240px)_1fr_auto] md:items-start">
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
                                            <td className="px-4 py-2 text-right font-semibold text-slate-700">{item.quantity_released.toFixed(2)}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-emerald-700">{(item.quantity_received ?? 0).toFixed(2)}</td>
                                            <td className="px-4 py-2 text-right text-rose-600">{(item.quantity_damaged ?? 0).toFixed(2)}</td>
                                            <td className="px-4 py-2 text-right text-amber-600">{(item.quantity_missing ?? 0).toFixed(2)}</td>
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
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Claimed Item Scope</h3>
                        <p className="mt-2 text-sm text-slate-600">Item-level breakdown is available through linked distribution records and claim evidence attachments.</p>
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
        SHORTAGE: 'Shortage',
    };

    return map[type] ?? humanizeEnum(type);
}

function displayClaimStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        IN_REVIEW: 'In Claim Review',
        RESOLVED: 'Resolved',
        REJECTED: 'Rejected',
    };

    return map[status] ?? humanizeEnum(status);
}

function getNextStatusOptions(status: string): Array<{ value: string; label: string }> {
    const transitions: Record<string, string[]> = {
        OPEN: ['IN_REVIEW', 'RESOLVED', 'REJECTED'],
        IN_REVIEW: ['RESOLVED', 'REJECTED'],
    };

    return (transitions[status] ?? []).map((value) => ({
        value,
        label: displayClaimStatus(value),
    }));
}



