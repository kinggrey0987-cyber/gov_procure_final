import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
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
            is_rejected: boolean;
            stages: Array<{
                key: string;
                label: string;
                owner: string;
                reached: boolean;
                is_current: boolean;
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
        changed_by?: { id: number; name: string } | null;
    }>;
    approvalHistory: Array<{
        id: number;
        status: string;
        remarks?: string | null;
        approved_at?: string | null;
        approver?: { id: number; name: string } | null;
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
                        <MetaItem label="Priority" value={supplyRequest.priority_level} />
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
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Processing Timeline</h4>
                    <p className="mt-1 text-sm text-slate-600">
                        Track each stage and responsible office. Route: {supplyRequest.processing_timeline.mode === 'PROCUREMENT' ? 'Procurement Required' : 'Direct Stock Release'}
                    </p>

                    <div className="mt-4 grid gap-3 lg:grid-cols-2">
                        {supplyRequest.processing_timeline.stages.map((stage) => {
                            return (
                                <div
                                    key={stage.key}
                                    className={`rounded-xl border px-4 py-3 ${stage.reached ? 'border-emerald-300 bg-emerald-50/70' : 'border-slate-200 bg-slate-50'} ${stage.is_current ? 'ring-1 ring-emerald-400' : ''}`}
                                >
                                    <p className="text-sm font-semibold text-slate-800">{stage.label}</p>
                                    <p className="text-xs text-slate-600">Responsible: {stage.owner}</p>
                                </div>
                            );
                        })}
                    </div>

                    {supplyRequest.processing_timeline.is_rejected ? (
                        <div className="mt-3 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                            Request was rejected in validation/procurement. Please review remarks in status history.
                        </div>
                    ) : null}
                    </section>
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
                                            <td className="px-4 py-3 text-slate-700">{item.item_specification || '-'}</td>
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

                {activeTab === 'status' ? (
                    <>
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h4 className="text-xl font-semibold text-slate-800">Workflow History</h4>
                            <p className="mt-2 text-sm text-slate-600">
                                Detailed status and approval history is intentionally kept out of the page layout to reduce visual noise.
                            </p>
                            <p className="mt-3 text-sm text-slate-600">
                                Use the current request state at the top of the page as the primary on-screen status indicator.
                            </p>
                        </section>
                    </>
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
