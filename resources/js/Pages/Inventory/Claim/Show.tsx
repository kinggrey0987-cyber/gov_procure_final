import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import WorkflowHistoryTimeline from '@/Components/WorkflowHistoryTimeline';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
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
        parent_claim_id?: number | null;
        source_type?: string | null;
    };
    parentClaim?: {
        claims_id: number;
        claim_code: string;
        status: string;
    } | null;
    followUpClaims?: Array<{
        claims_id: number;
        claim_code: string;
        status: string;
    }>;
    followUpPurchaseRequest?: {
        purchase_request_id: number;
        pr_number: string;
        status: string;
        source_type?: string | null;
        remarks?: string | null;
        created_at?: string | null;
        purchase_order_count?: number;
        purchase_orders?: Array<{
            purchase_order_id: number;
            po_number: string;
            status: string;
        }>;
        status_history?: Array<{
            old_status?: string | null;
            new_status: string;
            remarks?: string | null;
            changed_at?: string | null;
            changed_by?: {
                name: string;
            } | null;
        }>;
    } | null;
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
        changed_by?: {
            id: number;
            name: string;
            role?: string | null;
            office?: string | null;
        } | null;
    }>;
    approvalHistory: Array<{
        id: number;
        status: string;
        remarks?: string | null;
        approved_at?: string | null;
        approver?: {
            id: number;
            name: string;
            role?: string | null;
            office?: string | null;
        } | null;
    }>;
    routes?: {
        index: string;
        create?: string;
        show: string;
        updateStatus?: string;
        verifyReplacement?: string;
        followUpPurchaseRequest?: string;
    };
    permissions?: {
        update: boolean;
        can_create_follow_up_purchase_request?: boolean;
        createFollowUp?: boolean;
        verifyReplacement?: boolean;
    };
};

export default function ClaimShow({ claim, parentClaim, followUpClaims = [], followUpPurchaseRequest, linkedDistribution, attachments, attachmentPermissions, statusHistory, approvalHistory, routes, permissions }: Props) {
    const { auth } = usePage<PageProps>().props;
    const isOfficeUser = auth?.user?.role_code === 'OFFICE_USER';
    const [activeTab, setActiveTab] = useState<'overview' | 'items' | 'status' | 'related' | 'notes'>('overview');
    const [isCreatingPR, setIsCreatingPR] = useState(false);
    const routeNames = {
        index: routes?.index ?? 'inventory.claims.index',
        create: routes?.create,
        show: routes?.show ?? 'inventory.claims.show',
        updateStatus: routes?.updateStatus ?? 'inventory.claims.update-status',
        verifyReplacement: routes?.verifyReplacement ?? 'office.claims.verify-replacement',
        followUpPurchaseRequest: routes?.followUpPurchaseRequest ?? 'inventory.claims.follow-up-purchase-request.store',
    };
    const remainingReplacementQuantity = Math.max((claim.quantity_disputed ?? 0) - (claim.replacement_quantity ?? 0), 0);
    const nextStatusOptions = getNextStatusOptions(
        claim.status,
        claim.replacement_available_stock,
        remainingReplacementQuantity,
        Boolean(followUpPurchaseRequest),
        claim.replacement_quantity ?? 0
    );
    const canUpdate = (permissions?.update ?? false) && nextStatusOptions.length > 0;
    const statusForm = useForm({
        status: nextStatusOptions[0]?.value ?? '',
        resolution_type: '',
        replacement_quantity: '',
        remarks: '',
    });
    const usesReplacement = statusForm.data.status === 'RESOLVED' || statusForm.data.status === 'REPLACEMENT_RELEASED';
    const isStockInsufficient = usesReplacement && (claim.replacement_available_stock ?? 0) < remainingReplacementQuantity;
    const claimedItems = linkedDistribution?.items.filter((item) => {
        if (!claim.distribution_item_id) {
            return true;
        }

        return item.distribution_item_id === claim.distribution_item_id;
    }) ?? [];
    const hasSpecificClaimedItem = claim.distribution_item_id !== null && claim.distribution_item_id !== undefined;
    const claimedIssueQuantities = getClaimIssueQuantities(claim);
    const canCreateFollowUp = (permissions?.createFollowUp ?? false) && linkedDistribution !== null && claim.distribution_item_id !== null && claim.distribution_item_id !== undefined;
    const canCreateFollowUpPR = (permissions?.can_create_follow_up_purchase_request ?? false) && !followUpPurchaseRequest;

    const handleCreateFollowUpPR = () => {
        if (!routeNames.followUpPurchaseRequest) return;
        setIsCreatingPR(true);
        router.post(route(routeNames.followUpPurchaseRequest, claim.claims_id), {}, {
            preserveScroll: true,
            onFinish: () => setIsCreatingPR(false),
        });
    };

    const applyStatusDecision = (status: string) => {
        statusForm.setData('status', status);

        if (status === 'REPLACEMENT_RELEASED' || status === 'RESOLVED') {
            statusForm.setData('resolution_type', 'REPLACEMENT_RELEASED');
            statusForm.setData('replacement_quantity', String(Math.trunc(remainingReplacementQuantity)));
            return;
        }

        if (status === 'PENDING_REPLACEMENT') {
            statusForm.setData('resolution_type', 'PENDING_REPLACEMENT');
            statusForm.setData('replacement_quantity', '');
            return;
        }

        if (status === 'REJECTED') {
            statusForm.setData('resolution_type', 'OTHER_RESOLUTION');
            statusForm.setData('replacement_quantity', '');
            return;
        }

        statusForm.setData('resolution_type', '');
        statusForm.setData('replacement_quantity', '');
    };

    useEffect(() => {
        applyStatusDecision(nextStatusOptions[0]?.value ?? '');
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
                        {parentClaim ? <Info label="Original Claim" value={parentClaim.claim_code} mono /> : null}
                        <Info label="Issue Type" value={displayClaimType(claim.claim_type)} />
                        <Info label="Date Observed" value={formatDate(claim.claim_date)} />
                        <Info label="Issue Location" value={claim.claim_location} />
                        <Info label="Disputed Quantity" value={formatQuantity(claim.quantity_disputed ?? 0)} />
                        <Info label="Claimed Damaged Qty" value={formatQuantity(claimedIssueQuantities.damaged)} />
                        <Info label="Claimed Missing Qty" value={formatQuantity(claimedIssueQuantities.missing)} />
                        <Info label="Resolution Type" value={claim.resolution_type ?? 'Pending review'} />
                        <Info label="Replacement Released" value={formatQuantity(claim.replacement_quantity ?? 0)} />
                        {!isOfficeUser && (
                            <Info label="Available Replacement Stock" value={claim.replacement_available_stock === null || claim.replacement_available_stock === undefined ? '-' : formatQuantity(claim.replacement_available_stock)} />
                        )}
                        <Info label="Remarks" value={claim.remarks ?? 'No remarks provided.'} />
                    </div>

                    {/* Follow-up Purchase Request Banner */}
                    {followUpPurchaseRequest ? (
                        <div className="mt-5 rounded-lg border border-indigo-200 bg-indigo-50/70 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-800">
                                            Follow-up PR Queued
                                        </span>
                                        <span className="font-mono text-sm font-bold text-indigo-950">
                                            {followUpPurchaseRequest.pr_number || `PR-${String(followUpPurchaseRequest.purchase_request_id).padStart(6, '0')}`}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-600">
                                        Procurement Status: <span className="font-semibold text-slate-800">{humanizeEnum(followUpPurchaseRequest.status)}</span>
                                        {followUpPurchaseRequest.purchase_order_count ? ` • Linked POs: ${followUpPurchaseRequest.purchase_order_count}` : ''}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {followUpPurchaseRequest.remarks || 'Follow-up purchase request queued to procure shortage replacement items.'}
                                    </p>
                                </div>
                                <span className="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200">
                                    PR In Procurement
                                </span>
                            </div>
                        </div>
                    ) : (!isOfficeUser && claim.replacement_available_stock !== null && (claim.replacement_available_stock ?? 0) < remainingReplacementQuantity && remainingReplacementQuantity > 0) ? (
                        <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex rounded-full bg-amber-200 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                            Stock Shortage
                                        </span>
                                        <h4 className="text-sm font-semibold text-amber-900">Insufficient Replacement Stock</h4>
                                    </div>
                                    <p className="mt-1 text-xs text-amber-700">
                                        Available replacement stock ({claim.replacement_available_stock ?? 0}) cannot fulfill remaining disputed quantity ({remainingReplacementQuantity}). Forward a follow-up PR to Procurement.
                                    </p>
                                </div>
                                {canCreateFollowUpPR ? (
                                    <button
                                        type="button"
                                        onClick={handleCreateFollowUpPR}
                                        disabled={isCreatingPR}
                                        className="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-amber-700 disabled:opacity-50 transition"
                                    >
                                        {isCreatingPR ? 'Queuing PR...' : 'Forward Follow-up PR to Procurement'}
                                    </button>
                                ) : null}
                            </div>
                        </div>
                    ) : null}

                    {permissions?.verifyReplacement ? (
                        <div className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 className="text-sm font-semibold text-emerald-900">Replacement Received by Office?</h4>
                                    <p className="text-xs text-emerald-700">
                                        If GSO has physically released or delivered the replacement items and they are inspected, confirm to close this claim.
                                    </p>
                                </div>
                                <Link
                                    as="button"
                                    method="post"
                                    href={route(routeNames.verifyReplacement, claim.claims_id)}
                                    className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-emerald-700 transition"
                                >
                                    ✓ Confirm &amp; Accept Replacement
                                </Link>
                            </div>
                        </div>
                    ) : null}
                    {canCreateFollowUp && routeNames.create ? (
                        <div className="mt-5">
                            <Link
                                href={route(routeNames.create, {
                                    parent_claim_id: claim.claims_id,
                                    distribution_record_id: linkedDistribution?.distribution_record_id,
                                    distribution_item_id: claim.distribution_item_id,
                                })}
                                className="inline-flex rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100"
                            >
                                File Follow-up Claim
                            </Link>
                        </div>
                    ) : null}
                    {followUpClaims.length > 0 ? (
                        <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Follow-up Claims</h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {followUpClaims.map((followUp) => (
                                    <Link
                                        key={followUp.claims_id}
                                        href={route(routeNames.show, followUp.claims_id)}
                                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    >
                                        {followUp.claim_code} - {displayClaimStatus(followUp.status)}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    ) : null}
                    {permissions?.update ? (
                        <div className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">GSO Claim Follow-up</h3>
                            {canUpdate ? (
                                <form onSubmit={submitStatusUpdate} className="mt-3 grid gap-3 md:grid-cols-[minmax(180px,240px)_minmax(140px,180px)_1fr_auto] md:items-start">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Next Status</label>
                                        <select
                                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                            value={statusForm.data.status}
                                            onChange={(event) => applyStatusDecision(event.target.value)}
                                        >
                                            {nextStatusOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                        {statusForm.errors.status ? <p className="mt-1 text-xs text-rose-600">{statusForm.errors.status}</p> : null}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Replacement Qty</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            inputMode="numeric"
                                            pattern="[0-9]*"
                                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm read-only:bg-slate-100 disabled:bg-slate-100"
                                            value={statusForm.data.replacement_quantity}
                                            disabled={!usesReplacement}
                                            readOnly={usesReplacement}
                                            onChange={(event) => statusForm.setData('replacement_quantity', event.target.value.replace(/\D/g, ''))}
                                        />
                                        {statusForm.errors.replacement_quantity ? <p className="mt-1 text-xs text-rose-600">{statusForm.errors.replacement_quantity}</p> : null}
                                        {usesReplacement && (claim.replacement_available_stock ?? 0) < remainingReplacementQuantity ? (
                                            <p className="mt-1 text-xs text-amber-600">Available stock is below the remaining disputed quantity.</p>
                                        ) : null}
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
                                        disabled={statusForm.processing || statusForm.data.status === '' || (statusForm.data.status === 'REPLACEMENT_RELEASED' && isStockInsufficient)}
                                        className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 md:mt-5"
                                    >
                                        {statusForm.processing ? 'Saving...' : 'Save Follow-up'}
                                    </button>
                                    {statusForm.data.status === 'REPLACEMENT_RELEASED' && isStockInsufficient ? (
                                        <p className="col-span-full mt-2 text-xs font-medium text-amber-700">
                                            Cannot release replacement: available inventory stock ({claim.replacement_available_stock ?? 0}) is less than the remaining disputed quantity ({remainingReplacementQuantity}). Forward a follow-up PR above or select Rejected.
                                        </p>
                                    ) : null}
                                </form>
                            ) : (
                                <p className="mt-2 text-sm text-slate-600">
                                    {claim.status === 'REPLACEMENT_RELEASED'
                                        ? 'Replacement items have been released. Awaiting verification and acceptance by the requesting office.'
                                        : claim.status === 'PENDING_REPLACEMENT' && followUpPurchaseRequest
                                        ? 'Follow-up purchase request has been forwarded to Procurement. Awaiting fulfillment and stock arrival.'
                                        : 'This claim has already been closed and can only be viewed.'}
                                </p>
                            )}
                        </div>
                    ) : null}
                    </div>
                ) : null}

                {activeTab === 'related' ? (
                    <div className="space-y-4">
                        {linkedDistribution ? (
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
                            </div>
                        ) : null}

                        {followUpPurchaseRequest ? (
                            <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Linked Follow-up Purchase Request</h3>
                                <div className="mt-3 grid gap-4 md:grid-cols-2">
                                    <Info label="PR Number" value={followUpPurchaseRequest.pr_number || `PR-${String(followUpPurchaseRequest.purchase_request_id).padStart(6, '0')}`} mono />
                                    <Info label="Procurement Status" value={humanizeEnum(followUpPurchaseRequest.status)} />
                                    <Info label="Created Date" value={formatDateTime(followUpPurchaseRequest.created_at)} />
                                    <Info label="Linked Purchase Orders" value={String(followUpPurchaseRequest.purchase_order_count ?? 0)} />
                                    <div className="col-span-full">
                                        <Info label="Remarks" value={followUpPurchaseRequest.remarks ?? 'No remarks recorded.'} />
                                    </div>
                                </div>

                                {followUpPurchaseRequest.purchase_orders?.length ? (
                                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Purchase Orders</p>
                                        <div className="mt-3 space-y-2">
                                            {followUpPurchaseRequest.purchase_orders.map((po) => (
                                                <div key={po.purchase_order_id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                    <span className="font-semibold text-slate-800">{po.po_number || `PO #${po.purchase_order_id}`}</span>
                                                    <span className="text-slate-600">{po.status ? humanizeEnum(po.status) : '-'}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : null}

                                {followUpPurchaseRequest.status_history?.length ? (
                                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">PR Workflow History</p>
                                        <div className="mt-3 space-y-2">
                                            {followUpPurchaseRequest.status_history.map((history, index) => (
                                                <div key={`${history.new_status ?? 'status'}-${index}`} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                                    <div className="flex items-center justify-between gap-3">
                                                        <span className="font-semibold text-slate-800">{history.new_status ? humanizeEnum(history.new_status) : '-'}</span>
                                                        <span className="text-xs text-slate-500">{history.changed_at ? formatDateTime(history.changed_at) : '-'}</span>
                                                    </div>
                                                    {history.remarks ? <p className="mt-1 text-slate-600">{history.remarks}</p> : null}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : null}
                            </section>
                        ) : null}

                        {!linkedDistribution && !followUpPurchaseRequest ? (
                            <div className="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                                No related records found for this claim.
                            </div>
                        ) : null}
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
                    <WorkflowHistoryTimeline
                        statusHistory={statusHistory}
                        approvalHistory={approvalHistory}
                        title="Claim Workflow & Status History"
                        subtitle="Chronological timeline of discrepancy reporting, GSO investigation, and resolution."
                    />
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
        REPLACEMENT_RELEASED: 'Replacement Released (Pending Office Verification)',
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

function getNextStatusOptions(
    status: string,
    availableStock?: number | null,
    remainingDisputed?: number,
    hasFollowUpPr: boolean = false,
    replacementQuantity: number = 0
): Array<{ value: string; label: string }> {
    const hasSufficientStock = availableStock !== null && availableStock !== undefined && (remainingDisputed ?? 0) <= availableStock;
    const hasInsufficientStock = availableStock !== null && availableStock !== undefined && (remainingDisputed ?? 0) > availableStock;

    let allowed: string[] = [];
    if (status === 'OPEN' || status === 'IN_REVIEW' || status === 'PARTIALLY_RESOLVED') {
        if (hasSufficientStock) {
            allowed = ['REPLACEMENT_RELEASED', 'REJECTED'];
        } else if (hasInsufficientStock) {
            allowed = ['PENDING_REPLACEMENT', 'REJECTED'];
        } else {
            allowed = ['REPLACEMENT_RELEASED', 'PENDING_REPLACEMENT', 'REJECTED'];
        }
    } else if (status === 'PENDING_REPLACEMENT') {
        if (hasSufficientStock) {
            allowed = ['REPLACEMENT_RELEASED'];
        } else if (!hasFollowUpPr && replacementQuantity <= 0) {
            allowed = ['REJECTED'];
        } else {
            allowed = [];
        }
    } else if (status === 'REPLACEMENT_RELEASED') {
        allowed = [];
    }

    return allowed.map((value) => ({
        value,
        label: displayClaimStatus(value),
    }));
}
