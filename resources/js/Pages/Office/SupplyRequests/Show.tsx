import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ApprovalHistoryTimeline from '@/Components/ApprovalHistoryTimeline';
import StatusHistoryTimeline from '@/Components/StatusHistoryTimeline';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Props = {
    supplyRequest: {
        supply_request_id: number;
        office_id: number;
        ppmp_id?: number | null;
        request_code: string;
        status: string;
        request_date?: string | null;
        needed_by_date?: string | null;
        request_category: string;
        purpose: string;
        priority_level: string;
        review_notes: string | null;
        items: Array<{
            supply_request_item_id: number;
            ppmp_item_id: number;
            item_name_snapshot?: string | null;
            quantity_requested: number;
            planned_quantity?: number | null;
            already_requested_quantity?: number | null;
            remaining_quantity?: number | null;
        }>;
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

const PROCESS_STAGES = [
    { key: 'DRAFT', label: 'Stage 1: Draft Request', owner: 'Office User' },
    { key: 'SUBMITTED', label: 'Stage 2: Submitted to GSO', owner: 'GSO Officer' },
    { key: 'IN_REVIEW', label: 'Stage 3: GSO Validation', owner: 'GSO Officer' },
    { key: 'PROCESSING', label: 'Stage 4: BAC / Procurement', owner: 'BAC Officer' },
    { key: 'APPROVED', label: 'Stage 5: Executive Approval', owner: 'Executive' },
    { key: 'RELEASED', label: 'Stage 6: Released for Office', owner: 'GSO Officer' },
    { key: 'COMPLETED', label: 'Stage 7: Completed', owner: 'Office User' },
];

export default function SupplyRequestShow({
    supplyRequest,
    distributionRecords,
    canEdit,
    canSubmit,
    statusHistory,
    approvalHistory,
}: Props) {
    const [reportIssueOpen, setReportIssueOpen] = useState(false);

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
        return status === 'RELEASED' || status === 'COMPLETED';
    }, [supplyRequest.status]);

    const stageIndex = PROCESS_STAGES.findIndex((stage) => stage.key === supplyRequest.status.toUpperCase());

    const handleSubmit = () => {
        submitForm.post(route('office.supply-requests.submit', supplyRequest.supply_request_id));
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Request Details</h2>}>
            <Head title="Request Details" />

            <div className="space-y-6 py-4">
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
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h4 className="text-xl font-semibold text-slate-800">Processing Timeline</h4>
                    <p className="mt-1 text-sm text-slate-600">Track each stage and responsible office.</p>

                    <div className="mt-4 grid gap-3 lg:grid-cols-2">
                        {PROCESS_STAGES.map((stage, index) => {
                            const reached = stageIndex >= index;
                            return (
                                <div
                                    key={stage.key}
                                    className={`rounded-xl border px-4 py-3 ${reached ? 'border-emerald-300 bg-emerald-50/70' : 'border-slate-200 bg-slate-50'}`}
                                >
                                    <p className="text-sm font-semibold text-slate-800">{stage.label}</p>
                                    <p className="text-xs text-slate-600">Responsible: {stage.owner}</p>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-xl font-semibold text-slate-800">Requested Items</h4>
                        <p className="mt-1 text-sm text-slate-600">Pricing here is planning context only via PPMP allocation. Actual procurement prices are recorded on purchase orders.</p>
                    </div>

                    {supplyRequest.items.length === 0 ? (
                        <div className="px-6 py-8 text-sm text-slate-600">No items attached.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">PPMP Item</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Planned</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Already Requested</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Remaining</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {supplyRequest.items.map((item) => (
                                        <tr key={item.supply_request_item_id}>
                                            <td className="px-4 py-3 text-slate-700">{item.item_name_snapshot ?? '-'}</td>
                                            <td className="px-4 py-3 text-slate-700">#{item.ppmp_item_id}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatNumber(item.planned_quantity)}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatNumber(item.already_requested_quantity)}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatNumber(item.remaining_quantity)}</td>
                                            <td className="px-4 py-3 text-slate-700">{item.quantity_requested}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href={route('office.supply-requests.index')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Back to Tracking
                        </Link>

                        {canEdit ? (
                            <Link
                                href={route('office.supply-requests.edit', supplyRequest.supply_request_id)}
                                className="rounded-lg border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"
                            >
                                Edit Request
                            </Link>
                        ) : null}

                        {canSubmit ? (
                            <button
                                onClick={handleSubmit}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                            >
                                Submit Request
                            </button>
                        ) : null}

                        {isReleasedOrCompleted ? (
                            <button
                                onClick={() => setReportIssueOpen(true)}
                                className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                            >
                                Report Issue
                            </button>
                        ) : null}
                    </div>
                </section>

                <StatusHistoryTimeline entries={statusHistory} />
                <ApprovalHistoryTimeline entries={approvalHistory} />
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
                                    <option value="WRONG_ITEM">Wrong Item</option>
                                    <option value="OTHER">Other</option>
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
            : normalized === 'IN_REVIEW' || normalized === 'PROCESSING' || normalized === 'RELEASED'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'REJECTED'
                    ? 'bg-rose-100 text-rose-700'
                    : 'bg-amber-100 text-amber-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}
