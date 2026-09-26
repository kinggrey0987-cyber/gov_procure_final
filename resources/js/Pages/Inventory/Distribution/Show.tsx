import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    record: {
        distribution_record_id: number;
        distribution_code: string;
        status: string;
        release_date?: string | null;
        storage_location?: string | null;
        notes?: string | null;
        office?: { name: string } | null;
        supply_request?: {
            request_code: string;
            status: string;
            items: Array<{
                supply_request_item_id: number;
                item_id: number;
                item_name: string;
                category_name: string;
                quantity_requested: number;
                quantity_released: number;
                quantity_remaining: number;
                unit: string;
                available_stock: number;
                max_releasable: number;
            }>;
        } | null;
        delivery?: { delivery_code: string } | null;
        preparer?: { name: string } | null;
        receiver?: { name: string } | null;
        is_claim_expired?: boolean;
        items: Array<{
            distribution_item_id: number;
            item_id: number;
            item_name_snapshot: string;
            quantity_released: number;
            unit: string;
            remarks?: string | null;
            item?: {
                item_name: string;
                category_name: string;
            } | null;
        }>;
        claims: Array<{
            claims_id: number;
            claim_code: string;
            status: string;
            claim_type: string;
            claim_date?: string | null;
        }>;
    };
};

export default function DistributionShow({ record }: Props) {
    const page = usePage().props as {
        auth?: {
            can?: {
                inventory?: boolean;
                office?: boolean;
            };
        };
    };

    const canManageDistribution = page.auth?.can?.inventory === true;
    const isOfficeUser = page.auth?.can?.office === true;
    const backRouteName = isOfficeUser ? 'office.supplies-ready.index' : 'inventory.distribution-records.index';
    const backLabel = isOfficeUser ? 'Back to Ready Supplies' : 'Back to Distribution Records';
    const canConfirmReceipt = isOfficeUser && record.status === 'RELEASED';
    const canFileClaim = isOfficeUser && record.status === 'RECEIVED' && !record.is_claim_expired;
    const [activeTab, setActiveTab] = useState<'overview' | 'items' | 'status' | 'related' | 'notes'>('overview');
    const isDeliveryBackedRelease = record.delivery !== null && record.delivery !== undefined;
    const releaseSource = isDeliveryBackedRelease ? 'Verified Delivery' : 'Available Inventory';
    const sourceReference = isDeliveryBackedRelease ? record.delivery?.delivery_code ?? 'Verified delivery' : 'Current inventory balance';
    const releaseDate = record.release_date ? formatDate(record.release_date) : (record.status === 'DRAFT' ? 'Pending item release' : 'Not recorded');

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Distribution Detail</h2>
                    <Link href={route(backRouteName)} className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        {backLabel}
                    </Link>
                </div>
            }
        >
            <Head title="Distribution Detail" />
            <div className="space-y-6 py-8">
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
                    <div className="grid gap-4 md:grid-cols-2">
                        <Info label="Distribution Code" value={record.distribution_code} mono />
                        <Info label="Status" value={displayDistributionStatus(record.status)} />
                        <Info label="Linked Office" value={record.office?.name ?? '-'} />
                        <Info label="Linked Request" value={record.supply_request?.request_code ?? '-'} mono />
                        <Info label="Release Source" value={releaseSource} />
                        <Info label="Source Reference" value={sourceReference} mono={isDeliveryBackedRelease} />
                        <Info label="Release Date" value={releaseDate} />
                        <Info label="Prepared / Released By" value={record.preparer?.name ?? '-'} />
                        <Info label="Received By" value={record.receiver?.name ?? 'Awaiting Office Confirmation'} />
                    </div>
                    <div className="mt-4 border-t border-slate-200 pt-4 text-sm text-slate-600">
                        <p><span className="font-semibold text-slate-700">Storage / Release Notes:</span> {record.storage_location ?? 'No storage location recorded.'}</p>
                        <p className="mt-1"><span className="font-semibold text-slate-700">Remarks:</span> {record.notes ?? 'No additional notes.'}</p>
                    </div>
                    {isOfficeUser ? (
                        <div className="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
                            {canFileClaim ? (
                                <Link
                                    href={route('office.claims.create', {
                                        distribution_record_id: record.distribution_record_id,
                                    })}
                                    className="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                >
                                    Claims and Adjustments
                                </Link>
                            ) : record.is_claim_expired && record.status === 'RECEIVED' ? (
                                <span
                                    className="rounded-md border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed select-none"
                                    title="Claims must be filed within 2 days (48 hours) of receiving supplies. The reporting window has closed."
                                >
                                    Claim Period Closed (2 days)
                                </span>
                            ) : null}

                            {canConfirmReceipt ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        router.post(route('office.distribution-records.receive', record.distribution_record_id), {}, {
                                            preserveScroll: true,
                                        });
                                    }}
                                    className="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                >
                                    Confirm Received
                                </button>
                            ) : null}
                        </div>
                    ) : null}
                    </div>
                ) : null}

                {activeTab === 'items' && !isOfficeUser ? (
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Requested Office Items</h3>
                        <p className="mt-1 text-xs text-slate-500">Inventory staff can compare requested quantities, released quantities, remaining quantities, and current stock while preparing releases.</p>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Requested</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Released</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Remaining</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Available Stock</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {(record.supply_request?.items ?? []).map((requestItem) => {
                                const maxReleasable = requestItem.max_releasable;
                                const canAdd = canManageDistribution && maxReleasable > 0;

                                return (
                                    <tr key={requestItem.supply_request_item_id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3 text-slate-700">{requestItem.item_name}</td>
                                        <td className="px-4 py-3 text-slate-600">{requestItem.category_name || '-'}</td>
                                        <td className="px-4 py-3 text-right font-semibold text-slate-700">{requestItem.quantity_requested.toFixed(2)} {requestItem.unit}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{requestItem.quantity_released.toFixed(2)} {requestItem.unit}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{requestItem.quantity_remaining.toFixed(2)} {requestItem.unit}</td>
                                        <td className="px-4 py-3 text-right text-slate-700">{requestItem.available_stock.toFixed(2)} {requestItem.unit}</td>
                                        <td className="px-4 py-3 text-right">
                                            {canManageDistribution ? (
                                                <button
                                                    type="button"
                                                    disabled={!canAdd}
                                                    onClick={() => {
                                                        router.post(route('inventory.distribution-records.items.store', record.distribution_record_id), {
                                                            item_id: requestItem.item_id,
                                                            item_name_snapshot: requestItem.item_name,
                                                            quantity_released: Number(maxReleasable.toFixed(2)),
                                                            unit: requestItem.unit,
                                                            remarks: `Released against ${record.supply_request?.request_code ?? 'linked supply request'}`,
                                                        }, {
                                                            preserveScroll: true,
                                                        });
                                                    }}
                                                    className="rounded-md border border-emerald-600 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-slate-300 disabled:text-slate-400 disabled:hover:bg-transparent"
                                                >
                                                    {canAdd ? 'Add to Release' : 'No Releasable Qty'}
                                                </button>
                                            ) : (
                                                <span className="text-xs text-slate-400">View only</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {(record.supply_request?.items?.length ?? 0) === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No requested item lines are available for this linked office request.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>
                ) : null}

                {activeTab === 'items' ? (
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Released Items</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Quantity</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Notes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {record.items.map((item) => (
                                <tr key={item.distribution_item_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 text-slate-700">{item.item?.item_name ?? item.item_name_snapshot}</td>
                                    <td className="px-4 py-3 text-slate-600">{item.item?.category_name || '-'}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-slate-700">{item.quantity_released.toFixed(2)}</td>
                                    <td className="px-4 py-3 text-slate-700">{item.unit}</td>
                                    <td className="px-4 py-3 text-slate-600">{item.remarks ?? '-'}</td>
                                </tr>
                            ))}
                            {record.items.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No released item lines were found for this distribution record.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>
                ) : null}

                {activeTab === 'related' ? (
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Related Claims and Issues</h3>
                    </div>
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Claim Code</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Issue Type</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {record.claims.map((claim) => (
                                <tr key={claim.claims_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{claim.claim_code}</td>
                                    <td className="px-4 py-3 text-slate-700">{humanizeEnum(claim.claim_type)}</td>
                                    <td className="px-4 py-3 text-slate-700">{displayClaimStatus(claim.status)}</td>
                                    <td className="px-4 py-3 text-slate-600">{formatDate(claim.claim_date)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route(isOfficeUser ? 'office.claims.show' : 'inventory.claims.show', claim.claims_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                            View Claim
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {record.claims.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">
                                        No claim issues are linked to this distribution record.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>
                ) : null}

                {activeTab === 'status' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Status History</h3>
                        <p className="mt-2 text-sm text-slate-600">Current status: {displayDistributionStatus(record.status)}</p>
                    </section>
                ) : null}

                {activeTab === 'notes' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Notes / Attachments</h3>
                        <p className="mt-2 text-sm text-slate-600"><span className="font-semibold">Storage / Release Notes:</span> {record.storage_location ?? 'No storage location recorded.'}</p>
                        <p className="mt-1 text-sm text-slate-600"><span className="font-semibold">Remarks:</span> {record.notes ?? 'No additional notes.'}</p>
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

function displayDistributionStatus(status: string): string {
    const map: Record<string, string> = {
        RELEASED: 'Released to Office',
        RECEIVED: 'Received by Office',
        DRAFT: 'Pending Review',
    };

    return map[status] ?? humanizeEnum(status);
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





