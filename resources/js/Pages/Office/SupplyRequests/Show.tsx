import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import WorkflowHistoryTimeline from '@/Components/WorkflowHistoryTimeline';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Props = {
    supplyRequest: {
        supply_request_id: number;
        office_id: number;
        ppmp_id?: number | null;
        request_code: string;
        status: string;
        raw_status?: string;
        request_date?: string | null;
        needed_by_date?: string | null;
        request_category: string;
        purpose: string;
        priority_level: string;
        review_notes: string | null;
        follow_up_context?: {
            is_follow_up: boolean;
            type: string;
            label: string;
            parent?: { supply_request_id: number; request_code: string } | null;
            children?: Array<{ supply_request_id: number; request_code: string; status: string; reason?: string }>;
        } | null;
        purchase_requests?: Array<{
            purchase_request_id: number;
            status: string;
            remarks?: string | null;
            created_at?: string | null;
            updated_at?: string | null;
        }>;
        items: Array<{
            supply_request_item_id: number;
            ppmp_item_id: number;
            item_name_snapshot?: string | null;
            default_unit?: string | null;
            pieces_per_box?: number | null;
            sub_unit?: string | null;
            quantity_requested: number;
            quantity_released?: number | null;
            quantity_received?: number | null;
            item_specification?: string | null;
            planned_quantity?: number | null;
            already_requested_quantity?: number | null;
            remaining_quantity?: number | null;
            over_requested_quantity?: number | null;
            exceeds_ppmp_allocation?: boolean;
        }>;
        processing_timeline: {
            mode: 'PROCUREMENT' | 'DIRECT_STOCK';
            current_status: string;
            workflow_status?: 'COMPLETED' | 'REJECTED' | 'CANCELLED' | 'RETURNED' | 'IN_PROGRESS';
            is_completed?: boolean;
            is_rejected: boolean;
            is_cancelled?: boolean;
            is_returned?: boolean;
            stages: Array<{
                order?: number;
                key: string;
                label: string;
                owner: string;
                reached: boolean;
                is_current: boolean;
                is_completed?: boolean;
                is_rejected?: boolean;
                is_cancelled?: boolean;
                is_discontinued?: boolean;
                is_returned?: boolean;
                status_badge?: 'COMPLETED' | 'ACTIVE' | 'REJECTED' | 'CANCELLED' | 'DISCONTINUED' | 'RETURNED' | 'PENDING';
                action_label?: string | null;
                actor_name?: string | null;
                actor_role?: string | null;
                actor_office?: string | null;
                completed_at?: string | null;
                action_summary?: string | null;
            }>;
        };
    };
    distributionRecords: Array<{
        distribution_record_id: number;
        status: string;
        storage_location?: string | null;
        release_date?: string | null;
    }>;
    canEdit: boolean;
    canSubmit: boolean;
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
};

export default function SupplyRequestShow({
    supplyRequest,
    distributionRecords,
    canEdit,
    canSubmit,
    statusHistory,
    approvalHistory,
}: Props) {
    const [reportIssueOpen, setReportIssueOpen] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [activeTab, setActiveTab] = useState<'overview' | 'items' | 'status' | 'related' | 'notes'>('overview');

    const procurementDecision = useMemo(() => {
        const currentStatus = supplyRequest.status.toUpperCase();
        if (!['RETURNED_FOR_REVISION', 'REJECTED'].includes(currentStatus)) {
            return null;
        }

        return (supplyRequest.purchase_requests ?? []).find((request) => request.status.toUpperCase() === currentStatus) ?? null;
    }, [supplyRequest.purchase_requests, supplyRequest.status]);

    const submitForm = useForm({ review_notes: supplyRequest.review_notes ?? '' });
    const issueForm = useForm({
        office_id: String(supplyRequest.office_id),
        distribution_record_id: distributionRecords[0]?.distribution_record_id ? String(distributionRecords[0].distribution_record_id) : '',
        claim_date: new Date().toISOString().slice(0, 10),
        claim_location: distributionRecords[0]?.storage_location ?? '',
        remarks: '',
        claim_type: 'MISSING_ITEM',
        attachment_title: '',
        attachment_file: null as File | null,
    });

    const isReleasedOrCompleted = useMemo(() => {
        const status = supplyRequest.status.toUpperCase();
        return status === 'RELEASED' || status === 'PARTIALLY_RELEASED' || status === 'PARTIALLY_RECEIVED' || status === 'COMPLETED';
    }, [supplyRequest.status]);
    const isDraft = (supplyRequest.raw_status ?? supplyRequest.status).toUpperCase() === 'DRAFT';

    const handleSubmit = () => {
        submitForm.post(route('office.supply-requests.submit', supplyRequest.supply_request_id));
    };

    const deleteDraft = () => {
        if (!isDraft) {
            return;
        }

        router.delete(route('office.supply-requests.destroy', supplyRequest.supply_request_id), {
            preserveScroll: true,
            onFinish: () => setConfirmingDelete(false),
        });
    };

    const handleIssueSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        issueForm.post(route('office.claims.store'), {
            forceFormData: true,
            onSuccess: () => {
                setReportIssueOpen(false);
                issueForm.reset('remarks', 'attachment_title', 'attachment_file');
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Request Details</h2>
                </div>
            }
        >
            <Head title="Request Details" />

            <div className="space-y-6 py-4">
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
                    <>
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    {supplyRequest.follow_up_context?.parent && (
                        <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-300 bg-amber-50/90 p-4 text-amber-950 shadow-xs">
                            <div className="flex items-center gap-3.5">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white shadow">
                                    <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h4 className="text-sm font-extrabold uppercase tracking-wide text-amber-900">
                                            Follow-Up Requisition
                                        </h4>
                                        <span className="rounded bg-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-900">
                                            {supplyRequest.follow_up_context.label}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-amber-800">
                                        This requisition was generated to replenish deficit / discrepancy items from parent requisition{' '}
                                        <span className="font-bold">{supplyRequest.follow_up_context.parent.request_code}</span>.
                                        Distributions completed for this request automatically contribute to the 100% total receipt of the parent requisition.
                                    </p>
                                </div>
                            </div>
                            <Link
                                href={route('office.supply-requests.show', supplyRequest.follow_up_context.parent.supply_request_id)}
                                className="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-amber-400 bg-white px-3 py-1.5 text-xs font-bold text-amber-900 shadow-2xs hover:bg-amber-100/50"
                            >
                                <span>View Parent ({supplyRequest.follow_up_context.parent.request_code})</span>
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </Link>
                        </div>
                    )}

                    {supplyRequest.follow_up_context?.children && supplyRequest.follow_up_context.children.length > 0 && (
                        <div className="mb-6 rounded-xl border border-blue-300 bg-blue-50/90 p-4 text-blue-950 shadow-xs">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="flex items-start gap-3.5">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow">
                                        <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h4 className="text-sm font-extrabold uppercase tracking-wide text-blue-900">
                                                Linked Follow-Up Requisitions
                                            </h4>
                                            <span className="rounded bg-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-900">
                                                {supplyRequest.follow_up_context.children.length} Batch{supplyRequest.follow_up_context.children.length === 1 ? '' : 'es'}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-blue-800">
                                            Partial delivery discrepancies resulted in follow-up replenishment requisition(s).
                                            Once distributions for all batches are received by your office, this parent requisition will automatically reflect 100% full receipt.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-2 border-t border-blue-200/70 pt-3">
                                {supplyRequest.follow_up_context.children.map((child) => (
                                    <Link
                                        key={child.supply_request_id}
                                        href={route('office.supply-requests.show', child.supply_request_id)}
                                        className="inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-blue-900 shadow-2xs hover:bg-blue-100/50"
                                    >
                                        <span>{child.request_code}</span>
                                        <span className="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">
                                            {child.status.replaceAll('_', ' ')}
                                        </span>
                                        <svg className="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}

                    {supplyRequest.priority_level?.toUpperCase() === 'URGENT' && (
                        <div className="mb-6 flex items-center gap-3.5 rounded-xl border-2 border-red-500 bg-red-50 p-4 text-red-900 shadow-xs">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-600 text-white shadow">
                                <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h4 className="text-base font-extrabold uppercase tracking-wide text-red-900">URGENT PRIORITY REQUEST</h4>
                                    <span className="rounded bg-red-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-white shadow-2xs">PRIORITIZED</span>
                                </div>
                                <p className="mt-0.5 text-xs text-red-700">This request is flagged as an urgent operational requirement and is expedited across inventory and procurement reviews.</p>
                            </div>
                        </div>
                    )}
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm font-medium text-slate-500">Request ID</p>
                            <h3 className="text-2xl font-bold text-red-700">{supplyRequest.request_code}</h3>
                            <p className="mt-2 text-sm text-slate-600">Submitted: {formatDate(supplyRequest.request_date)}</p>
                            <p className="text-sm text-slate-600">Needed by: {formatDate(supplyRequest.needed_by_date)}</p>
                        </div>
                        <StatusBadge status={supplyRequest.status} />
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <MetaItem label="PPMP" value={supplyRequest.ppmp_id ? `PPMP #${supplyRequest.ppmp_id}` : '-'} />
                        <MetaItem label="Category" value={supplyRequest.request_category} />
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Priority</p>
                            <div className="mt-1">
                                {supplyRequest.priority_level?.toUpperCase() === 'URGENT' ? (
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-red-300 bg-red-100 px-3 py-1 text-xs font-bold text-red-800 shadow-2xs">
                                        <span className="h-1.5 w-1.5 rounded-full bg-red-600"></span> URGENT (Priority Need)
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                        Normal
                                    </span>
                                )}
                            </div>
                        </div>
                        <MetaItem label="Purpose" value={supplyRequest.purpose} />
                        <MetaItem label="Review Notes" value={supplyRequest.review_notes ?? '-'} />
                    </div>

                    {procurementDecision ? (
                        <div className={`mt-5 rounded-xl border px-4 py-3 text-sm ${procurementDecision.status === 'REJECTED' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-amber-300 bg-amber-50 text-amber-900'}`}>
                            <p className="font-semibold">
                                Procurement decision: {procurementDecision.status.replaceAll('_', ' ')}
                            </p>
                            <p className="mt-1">
                                <span className="font-semibold">Procurement Unit Reason:</span> {procurementDecision.remarks?.trim() || 'No reason recorded.'}
                            </p>
                            <p className="mt-1 text-xs">
                                {procurementDecision.status === 'REJECTED'
                                    ? 'This procurement request was rejected. Create a new supply request if the office still needs these items.'
                                    : 'This procurement request was returned for revision. Review the Procurement Unit reason and coordinate with GSO/Procurement Unit before resubmission.'}
                            </p>
                        </div>
                    ) : null}
                </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href={route('office.supply-requests.index')}
                                    className="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                                >
                                    Back to Tracking
                                </Link>

                                {canEdit ? (
                                    <Link
                                        href={route('office.supply-requests.edit', supplyRequest.supply_request_id)}
                                        className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                                    >
                                        Edit Request
                                    </Link>
                                ) : null}

                                {isDraft ? (
                                    <button
                                        type="button"
                                        onClick={() => setConfirmingDelete(true)}
                                        className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                                    >
                                        Delete Request
                                    </button>
                                ) : null}

                                {canSubmit ? (
                                    <button
                                        onClick={handleSubmit}
                                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                    >
                                        Submit Request
                                    </button>
                                ) : null}
                            </div>
                        </section>
                    </>
                ) : null}

                {activeTab === 'status' ? (
                    <div className="space-y-4">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                            {/* Header */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div>
                                    <h4 className="text-lg sm:text-xl font-bold text-slate-900">Processing Timeline & Accountable Officers</h4>
                                    <p className="mt-0.5 text-xs sm:text-sm text-slate-500">
                                        Operational lifecycle tracking designated offices, accountable officers, and completion timestamps.
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                        Route: <strong className="text-slate-900">{supplyRequest.processing_timeline.mode === 'PROCUREMENT' ? 'Procurement Required' : 'Direct Stock Release'}</strong>
                                    </span>
                                    {supplyRequest.processing_timeline.workflow_status === 'COMPLETED' ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-300">
                                            <span>✓</span> Workflow Completed
                                        </span>
                                    ) : supplyRequest.processing_timeline.workflow_status === 'REJECTED' ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800 ring-1 ring-rose-300">
                                            <span>✕</span> Workflow Rejected
                                        </span>
                                    ) : supplyRequest.processing_timeline.workflow_status === 'CANCELLED' ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700 ring-1 ring-slate-300">
                                            <span>✕</span> Workflow Cancelled
                                        </span>
                                    ) : supplyRequest.processing_timeline.workflow_status === 'RETURNED' ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-300">
                                            <span>↺</span> Returned for Revision
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800 ring-1 ring-indigo-300">
                                            <span className="h-2 w-2 rounded-full bg-indigo-600 animate-pulse"></span> In Progress
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Stepper Progress Bar (Visual Lifecycle Ribbon) */}
                            <div className="mt-4 mb-5 overflow-x-auto pb-1">
                                <div className="flex items-center min-w-[620px] justify-between">
                                    {supplyRequest.processing_timeline.stages.map((stage, sIdx) => {
                                        const isCurrent = stage.is_current;
                                        const isCompleted = stage.is_completed ?? (stage.reached && !isCurrent && !stage.is_rejected);
                                        const isRejected = stage.is_rejected ?? false;
                                        const isCancelled = stage.is_cancelled ?? false;
                                        const isDiscontinued = stage.is_discontinued ?? false;
                                        const isLast = sIdx === supplyRequest.processing_timeline.stages.length - 1;

                                        return (
                                            <div key={`stepper-${stage.key}`} className="flex items-center flex-1 last:flex-none">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-all ${
                                                            isRejected
                                                                ? 'bg-rose-100 text-rose-700 border-2 border-rose-500 ring-2 ring-rose-100'
                                                                : isCancelled
                                                                ? 'bg-slate-200 text-slate-700 border border-slate-400'
                                                                : isCurrent
                                                                ? 'bg-indigo-600 text-white border-2 border-indigo-200 ring-4 ring-indigo-100 animate-pulse'
                                                                : isCompleted
                                                                ? 'bg-emerald-600 text-white border-2 border-emerald-200'
                                                                : isDiscontinued
                                                                ? 'bg-slate-100 text-slate-400 border border-slate-200'
                                                                : 'bg-slate-100 text-slate-500 border border-slate-300'
                                                        }`}
                                                    >
                                                        {isRejected ? '✕' : isCompleted ? '✓' : sIdx + 1}
                                                    </span>
                                                    <div className="flex flex-col">
                                                        <span
                                                            className={`text-xs font-semibold whitespace-nowrap ${
                                                                isCurrent
                                                                    ? 'text-indigo-900 font-bold'
                                                                    : isCompleted
                                                                    ? 'text-slate-800'
                                                                    : isRejected
                                                                    ? 'text-rose-800 font-bold'
                                                                    : isDiscontinued
                                                                    ? 'text-slate-400 line-through'
                                                                    : 'text-slate-500'
                                                            }`}
                                                        >
                                                            {stage.label.replace(/^Stage \d+:\s*/i, '')}
                                                        </span>
                                                        <span className="text-[10px] text-slate-400 whitespace-nowrap">
                                                            {isCurrent
                                                                ? 'Active'
                                                                : isCompleted
                                                                ? 'Done'
                                                                : isRejected
                                                                ? 'Rejected'
                                                                : isDiscontinued
                                                                ? 'Skipped'
                                                                : 'Upcoming'}
                                                        </span>
                                                    </div>
                                                </div>
                                                {!isLast && (
                                                    <div
                                                        className={`h-0.5 flex-1 mx-2 transition-all ${
                                                            isCompleted
                                                                ? 'bg-emerald-500'
                                                                : isCurrent
                                                                ? 'bg-gradient-to-r from-indigo-500 to-slate-200'
                                                                : isRejected
                                                                ? 'bg-rose-300'
                                                                : 'bg-slate-200'
                                                        }`}
                                                    />
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Responsive 3-Column Stage Cards Grid */}
                            <div className="grid gap-3 sm:gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                                {supplyRequest.processing_timeline.stages.map((stage, idx) => {
                                    const isCurrent = stage.is_current;
                                    const isCompleted = stage.is_completed ?? (stage.reached && !isCurrent && !stage.is_rejected);
                                    const isRejected = stage.is_rejected ?? false;
                                    const isCancelled = stage.is_cancelled ?? false;
                                    const isDiscontinued = stage.is_discontinued ?? false;
                                    const isReturned = stage.is_returned ?? false;

                                    return (
                                        <div
                                            key={stage.key}
                                            className={`flex flex-col justify-between rounded-xl border p-3.5 sm:p-4 transition ${
                                                isRejected
                                                    ? 'border-rose-300 bg-rose-50/40 shadow-xs ring-1 ring-rose-200'
                                                    : isCancelled
                                                    ? 'border-slate-300 bg-slate-100/60 shadow-xs'
                                                    : isReturned
                                                    ? 'border-amber-300 bg-amber-50/40 shadow-xs ring-1 ring-amber-200'
                                                    : isCurrent
                                                    ? 'border-indigo-400 bg-indigo-50/40 shadow-sm ring-2 ring-indigo-100'
                                                    : isCompleted
                                                    ? 'border-emerald-200 bg-emerald-50/20 shadow-xs'
                                                    : isDiscontinued
                                                    ? 'border-slate-200 bg-slate-50/40 opacity-50'
                                                    : 'border-slate-200 bg-white/80 opacity-80'
                                            }`}
                                        >
                                            <div>
                                                {/* Header: Stage number + label + status badge */}
                                                <div className="flex items-start justify-between gap-1.5">
                                                    <div className="flex items-center gap-1.5 min-w-0">
                                                        <span className={`flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md text-[10px] font-bold ${
                                                            isRejected
                                                                ? 'bg-rose-100 text-rose-800'
                                                                : isCurrent
                                                                ? 'bg-indigo-600 text-white'
                                                                : isCompleted
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : isDiscontinued
                                                                ? 'bg-slate-200 text-slate-500'
                                                                : 'bg-slate-100 text-slate-600'
                                                        }`}>
                                                            {idx + 1}
                                                        </span>
                                                        <h5 className={`text-xs sm:text-sm font-bold truncate ${
                                                            isRejected
                                                                ? 'text-rose-950'
                                                                : isCancelled
                                                                ? 'text-slate-700'
                                                                : isReturned
                                                                ? 'text-amber-950'
                                                                : isCurrent
                                                                ? 'text-indigo-950'
                                                                : isCompleted
                                                                ? 'text-slate-900'
                                                                : isDiscontinued
                                                                ? 'text-slate-400 line-through'
                                                                : 'text-slate-600'
                                                        }`}>
                                                            {stage.label.replace(/^Stage \d+:\s*/i, '')}
                                                        </h5>
                                                    </div>

                                                    {isRejected ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 ring-1 ring-rose-300 whitespace-nowrap flex-shrink-0">
                                                            <span>✕</span> Rejected
                                                        </span>
                                                    ) : isCancelled ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700 whitespace-nowrap flex-shrink-0">
                                                            <span>✕</span> Cancelled
                                                        </span>
                                                    ) : isReturned ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 ring-1 ring-amber-300 whitespace-nowrap flex-shrink-0">
                                                            <span>↺</span> Returned
                                                        </span>
                                                    ) : isCurrent ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] font-bold text-indigo-800 ring-1 ring-indigo-300 whitespace-nowrap flex-shrink-0">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-indigo-600 animate-ping"></span>
                                                            Active Stage
                                                        </span>
                                                    ) : isCompleted ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 whitespace-nowrap flex-shrink-0">
                                                            <span>✓</span> Completed
                                                        </span>
                                                    ) : isDiscontinued ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-200/60 px-2 py-0.5 text-[10px] font-medium text-slate-500 whitespace-nowrap flex-shrink-0">
                                                            Discontinued
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500 whitespace-nowrap flex-shrink-0">
                                                            <span>⏳</span> Pending
                                                        </span>
                                                    )}
                                                </div>

                                                {/* Accountable Officer & Timestamp */}
                                                <div className="mt-2.5 space-y-1">
                                                    {isRejected ? (
                                                        <div>
                                                            <div className="flex items-center justify-between text-[11px]">
                                                                <span className="font-semibold text-rose-900">Rejected by:</span>
                                                                {stage.completed_at ? (
                                                                    <span className="text-slate-500 font-medium">
                                                                        🕒 {formatTimelineDateTime(stage.completed_at)}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            <div className="mt-0.5 flex flex-wrap items-center gap-1">
                                                                <span className="text-xs font-bold text-slate-900">{stage.actor_name || stage.owner}</span>
                                                                {stage.actor_role ? (
                                                                    <span className="rounded bg-rose-50 border border-rose-200 px-1 py-0.2 text-[10px] font-semibold text-rose-800">
                                                                        {stage.actor_role}
                                                                    </span>
                                                                ) : null}
                                                                {stage.actor_office ? (
                                                                    <span className="text-[10px] text-slate-500 truncate max-w-[140px]">• {stage.actor_office}</span>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    ) : isCompleted ? (
                                                        <div>
                                                            <div className="flex items-center justify-between text-[11px]">
                                                                <span className="font-semibold text-emerald-900">{stage.action_label || 'Executed by:'}</span>
                                                                <span className="text-slate-500 font-medium">
                                                                    🕒 {formatTimelineDateTime(stage.completed_at)}
                                                                </span>
                                                            </div>
                                                            <div className="mt-0.5 flex flex-wrap items-center gap-1">
                                                                <span className="text-xs font-bold text-slate-900">{stage.actor_name || 'Authorized Officer'}</span>
                                                                {stage.actor_role ? (
                                                                    <span className="rounded bg-emerald-50 border border-emerald-200 px-1 py-0.2 text-[10px] font-semibold text-emerald-800">
                                                                        {stage.actor_role}
                                                                    </span>
                                                                ) : null}
                                                                {stage.actor_office ? (
                                                                    <span className="text-[10px] text-slate-500 truncate max-w-[140px]">• {stage.actor_office}</span>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    ) : isCurrent ? (
                                                        <div>
                                                            <div className="flex items-center justify-between text-[11px]">
                                                                <span className="font-semibold text-indigo-900">{stage.action_label || 'Handled by:'}</span>
                                                                {stage.completed_at ? (
                                                                    <span className="text-indigo-600 font-medium">
                                                                        🕒 Since {formatTimelineDateTime(stage.completed_at)}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            <div className="mt-0.5 flex flex-wrap items-center gap-1">
                                                                <span className="text-xs font-bold text-indigo-950">{stage.actor_name || stage.owner}</span>
                                                                {stage.actor_role ? (
                                                                    <span className="rounded bg-indigo-50 border border-indigo-200 px-1 py-0.2 text-[10px] font-semibold text-indigo-800">
                                                                        {stage.actor_role}
                                                                    </span>
                                                                ) : (
                                                                    <span className="rounded bg-slate-100 border border-slate-200 px-1 py-0.2 text-[10px] font-semibold text-slate-700">
                                                                        {stage.owner}
                                                                    </span>
                                                                )}
                                                                {stage.actor_office ? (
                                                                    <span className="text-[10px] text-indigo-700 truncate max-w-[140px]">• {stage.actor_office}</span>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    ) : isDiscontinued ? (
                                                        <div>
                                                            <div className="flex items-center justify-between text-[11px] text-slate-400">
                                                                <span className="font-semibold text-slate-500">Terminated</span>
                                                                <span>Not Reached</span>
                                                            </div>
                                                            <p className="mt-0.5 text-xs text-slate-400 line-through">{stage.owner}</p>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <div className="flex items-center justify-between text-[11px] text-slate-500">
                                                                <span className="font-semibold text-slate-600">Designated Unit:</span>
                                                                <span className="text-[10px]">Pending</span>
                                                            </div>
                                                            <p className="mt-0.5 text-xs font-semibold text-slate-700 truncate">{stage.owner}</p>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Action Summary / Note */}
                                            {stage.action_summary ? (
                                                <div className={`mt-2.5 rounded-lg border p-2 text-[11px] leading-snug ${
                                                    isRejected
                                                        ? 'border-rose-200 bg-rose-50/60 text-rose-900 font-medium'
                                                        : isCurrent
                                                        ? 'border-indigo-100 bg-indigo-50/40 text-indigo-950 font-medium'
                                                        : isDiscontinued
                                                        ? 'border-slate-200 bg-slate-50 text-slate-400 italic'
                                                        : 'border-slate-100 bg-slate-50/60 text-slate-600'
                                                }`}>
                                                    <span className="line-clamp-2 hover:line-clamp-none transition-all">
                                                        {isRejected ? <strong>Reason: </strong> : null}
                                                        {stage.action_summary}
                                                    </span>
                                                </div>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>

                            {supplyRequest.processing_timeline.is_rejected ? (
                                <div className="mt-4 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 shadow-xs">
                                    <strong className="font-bold">Request Returned / Disapproved:</strong> This request was flagged during evaluation. Review the rejection remarks above or inspect the detailed audit trail below.
                                </div>
                            ) : null}
                        </section>

                        {/* Collapsible System Audit Trail (Collapsed by default so everything above fits in one screen!) */}
                        <WorkflowHistoryTimeline
                            statusHistory={statusHistory}
                            approvalHistory={approvalHistory}
                            title="Detailed Operational & Audit Trail"
                            subtitle="Chronological immutable record of low-level status transitions, database events, and accountable officers."
                            collapsible={true}
                            defaultOpen={false}
                            compact={true}
                        />
                    </div>
                ) : null}

                {activeTab === 'items' ? (
                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-xl font-semibold text-slate-800">Requested Items</h4>
                        <p className="mt-1 text-sm text-slate-600">Track each requested item against release/receipt progress and PPMP allocation context.</p>
                    </div>

                    {supplyRequest.items.length === 0 ? (
                        <div className="px-6 py-8 text-sm text-slate-600">No items attached.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Specification</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">PPMP Item</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Requested</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Released</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Received</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Planned</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Already Requested</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-700">Remaining</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {supplyRequest.items.map((item) => {
                                        const piecesPerBox = item.pieces_per_box ?? 1;
                                        const unit = item.default_unit || 'box';
                                        const subUnit = item.sub_unit || 'piece';

                                        return (
                                        <tr key={item.supply_request_item_id}>
                                            <td className="px-4 py-3 text-slate-700 font-medium">{item.item_name_snapshot ?? '-'}</td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {item.item_specification ? (
                                                    <span className="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                                        {item.item_specification}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">#{item.ppmp_item_id}</td>
                                            <td className="px-4 py-3 text-right font-semibold text-slate-800">
                                                <span>{formatNumber(item.quantity_requested)} {unit}</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-xs font-semibold text-indigo-700">
                                                        ({(item.quantity_requested * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                <span>{formatNumber(item.quantity_released)} {unit}</span>
                                                {piecesPerBox > 1 && (item.quantity_released ?? 0) > 0 ? (
                                                    <span className="block text-xs text-slate-500">
                                                        ({((item.quantity_released ?? 0) * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                <span>{formatNumber(item.quantity_received)} {unit}</span>
                                                {piecesPerBox > 1 && (item.quantity_received ?? 0) > 0 ? (
                                                    <span className="block text-xs text-slate-500">
                                                        ({((item.quantity_received ?? 0) * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                <span>{formatNumber(item.planned_quantity)} {unit}</span>
                                                {piecesPerBox > 1 && item.planned_quantity != null ? (
                                                    <span className="block text-xs text-slate-500">
                                                        ({(item.planned_quantity * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                <span>{formatNumber(item.already_requested_quantity)} {unit}</span>
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700 font-medium">
                                                <span>{formatNumber(item.remaining_quantity)} {unit}</span>
                                                {piecesPerBox > 1 && item.remaining_quantity != null ? (
                                                    <span className="block text-xs text-slate-500">
                                                        ({(item.remaining_quantity * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                        </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                    </section>
                ) : null}

                {activeTab === 'related' ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h4 className="text-xl font-semibold text-slate-800">Related Records</h4>
                        <div className="mt-4 space-y-5">
                            <div>
                                <h5 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Purchase Requests</h5>
                                {(supplyRequest.purchase_requests ?? []).length === 0 ? (
                                    <p className="mt-2 text-sm text-slate-600">No related purchase requests yet.</p>
                                ) : (
                                    <div className="mt-3 space-y-2">
                                        {(supplyRequest.purchase_requests ?? []).map((record) => (
                                            <div key={record.purchase_request_id} className="rounded-lg border border-slate-200 px-3 py-2">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <p className="text-sm font-medium text-slate-800">Purchase Request #{record.purchase_request_id}</p>
                                                    <StatusBadge status={record.status} />
                                                </div>
                                                {['RETURNED_FOR_REVISION', 'REJECTED'].includes(record.status.toUpperCase()) ? (
                                                    <p className="mt-2 text-sm text-slate-700">
                                                        <span className="font-semibold">Procurement Unit Reason:</span> {record.remarks?.trim() || 'No reason recorded.'}
                                                    </p>
                                                ) : record.remarks ? (
                                                    <p className="mt-2 text-sm text-slate-600">{record.remarks}</p>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div>
                                <h5 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Distribution Records</h5>
                                {distributionRecords.length === 0 ? (
                                    <p className="mt-2 text-sm text-slate-600">No related distribution records yet.</p>
                                ) : (
                                    <div className="mt-3 space-y-2">
                                        {distributionRecords.map((record) => (
                                            <div key={record.distribution_record_id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                                                <p className="text-sm font-medium text-slate-800">Distribution #{record.distribution_record_id}</p>
                                                <p className="text-xs text-slate-600">{record.status.replaceAll('_', ' ')}</p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                ) : null}

                {activeTab === 'notes' ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h4 className="text-xl font-semibold text-slate-800">Notes / Attachments</h4>
                        <p className="mt-2 text-sm text-slate-600">Review notes and issue reporting are available here for released/completed requests.</p>
                        <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <p><span className="font-semibold">Review Notes:</span> {supplyRequest.review_notes ?? '-'}</p>
                        </div>
                        {isReleasedOrCompleted ? (
                            <button
                                onClick={() => setReportIssueOpen(true)}
                                className="mt-4 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                            >
                                Report Issue
                            </button>
                        ) : null}
                    </section>
                ) : null}


            </div>

            {reportIssueOpen ? (
                <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/40 p-4">
                    <div className="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <h4 className="text-lg font-semibold text-slate-800">Report Issue</h4>
                            <button
                                type="button"
                                onClick={() => setReportIssueOpen(false)}
                                className="rounded border border-slate-300 px-3 py-1 text-sm font-semibold text-slate-700"
                            >
                                Close
                            </button>
                        </div>

                        <form onSubmit={handleIssueSubmit} className="space-y-4 px-6 py-5">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Issue Type</label>
                                <select
                                    value={issueForm.data.claim_type}
                                    onChange={(event) => issueForm.setData('claim_type', event.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >
                                    <option value="MISSING_ITEM">Missing Item</option>
                                    <option value="DAMAGED_ITEM">Damaged Item</option>
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Release Record</label>
                                <select
                                    value={issueForm.data.distribution_record_id}
                                    onChange={(event) => issueForm.setData('distribution_record_id', event.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    required
                                >
                                    {distributionRecords.length === 0 ? <option value="">No release records found</option> : null}
                                    {distributionRecords.map((record) => (
                                        <option key={record.distribution_record_id} value={String(record.distribution_record_id)}>
                                            Record #{record.distribution_record_id} - {record.status}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Issue Description</label>
                                <textarea
                                    value={issueForm.data.remarks}
                                    onChange={(event) => issueForm.setData('remarks', event.target.value)}
                                    className="h-28 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    placeholder="Describe the issue in detail."
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Attachment Title (Optional)</label>
                                <input
                                    value={issueForm.data.attachment_title}
                                    onChange={(event) => issueForm.setData('attachment_title', event.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Attachment (Optional)</label>
                                <input
                                    type="file"
                                    onChange={(event) => issueForm.setData('attachment_file', event.target.files?.[0] ?? null)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                />
                            </div>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setReportIssueOpen(false)}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={issueForm.processing || distributionRecords.length === 0}
                                    className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                                >
                                    Submit Issue Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}

            <Modal show={confirmingDelete} maxWidth="md" onClose={() => setConfirmingDelete(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Delete draft supply request?</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Delete draft supply request {supplyRequest.request_code}? This will remove it from your request list.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setConfirmingDelete(false)}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={deleteDraft}
                            className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                        >
                            Delete Request
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function MetaItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm text-slate-800">{value}</p>
        </div>
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function formatTimelineDateTime(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function formatNumber(value?: number | null): string {
    if (value == null) {
        return '-';
    }

    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED' || normalized === 'COMPLETED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'IN_REVIEW' || normalized === 'PROCESSING' || normalized === 'RELEASED' || normalized === 'PARTIALLY_RELEASED' || normalized === 'PROCUREMENT_PO_DRAFT' || normalized === 'PROCUREMENT_PLACED'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'REJECTED' || normalized === 'RETURNED_FOR_REVISION'
                    ? 'bg-rose-100 text-rose-700'
                    : normalized === 'PARTIALLY_RECEIVED'
                        ? 'bg-orange-100 text-orange-700'
                    : 'bg-amber-100 text-amber-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}
