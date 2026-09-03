import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { getFundSourceLabel } from '@/constants/fundSources';
import { useState } from 'react';

type PpmpItem = {
    ppmp_item_id: number;
    quantity: number;
    unit_cost: number;
    procurement_schedule: string;
    remarks?: string | null;
    item?: { item_name?: string | null; default_unit?: string | null; pieces_per_box?: number | null; sub_unit?: string | null } | null;
};

type Props = {
    ppmp: {
        ppmp_id: number;
        fiscal_year: number;
        fund_source: string;
        status: string;
        date_prepared: string;
        date_approved?: string | null;
        remarks: string | null;
        items: PpmpItem[];
        approver?: { id: number; name: string } | null;
        preparer?: { id: number; name: string } | null;
    };
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

export default function PpmpShow({ ppmp, canEdit, canSubmit, statusHistory, approvalHistory }: Props) {
    const submitForm = useForm({ remarks: ppmp.remarks ?? '' });
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [confirmingSubmit, setConfirmingSubmit] = useState(false);

    const totalBudget = ppmp.items.reduce((total, item) => total + item.quantity * item.unit_cost, 0);
    const isDraft = ppmp.status.toUpperCase() === 'DRAFT';
    const ppmpCode = `PPMP-${ppmp.fiscal_year}-${String(ppmp.ppmp_id).padStart(4, '0')}`;

    const deleteDraft = () => {
        if (!isDraft) {
            return;
        }

        router.delete(route('office.ppmp.destroy', ppmp.ppmp_id), {
            preserveScroll: true,
            onFinish: () => setConfirmingDelete(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">PPMP Details</h2>
                    <Link href={route('office.ppmp.index')} className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to PPMP List
                    </Link>
                </div>
            }
        >
            <Head title="PPMP Details" />

            <div className="space-y-6 py-4">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm font-medium text-slate-500">PPMP ID</p>
                            <h3 className="text-2xl font-bold text-red-700">{ppmpCode}</h3>
                            <p className="mt-2 text-sm text-slate-600">Date prepared: {formatDate(ppmp.date_prepared)}</p>
                        </div>
                        <StatusBadge status={ppmp.status} />
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-3">
                        <Card label="Fiscal Year" value={String(ppmp.fiscal_year)} />
                        <Card label="Items" value={String(ppmp.items.length)} />
                        <Card label="Total Budget" value={formatMoney(totalBudget)} />
                    </div>

                    <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Fund Source</p>
                        <p className="mt-1 text-sm text-slate-700">{getFundSourceLabel(ppmp.fund_source) || '-'}</p>
                    </div>

                    {ppmp.status === 'APPROVED' && (ppmp.approver || approvalHistory?.[0]?.approver) ? (
                        <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Approved By</p>
                            <p className="mt-1 text-sm font-semibold text-emerald-900">{ppmp.approver?.name || approvalHistory?.[0]?.approver?.name}</p>
                            {ppmp.date_approved ? (
                                <p className="mt-0.5 text-xs text-emerald-700">Date approved: {formatDate(ppmp.date_approved)}</p>
                            ) : null}
                        </div>
                    ) : null}

                    {ppmp.status === 'REJECTED' && (ppmp.approver || approvalHistory?.[0]?.approver) ? (
                        <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50/60 px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-rose-700">Rejected By</p>
                            <p className="mt-1 text-sm font-semibold text-rose-900">{ppmp.approver?.name || approvalHistory?.[0]?.approver?.name}</p>
                            {approvalHistory?.[0]?.approved_at ? (
                                <p className="mt-0.5 text-xs text-rose-700">Date rejected: {formatDate(approvalHistory[0].approved_at)}</p>
                            ) : null}
                            {(approvalHistory?.[0]?.remarks || ppmp.remarks) ? (
                                <p className="mt-1.5 text-xs text-rose-800">
                                    <span className="font-semibold">Rejection Reason:</span> {approvalHistory?.[0]?.remarks || ppmp.remarks}
                                </p>
                            ) : null}
                        </div>
                    ) : null}

                    {ppmp.status === 'RETURNED_FOR_REVISION' && (ppmp.approver || approvalHistory?.[0]?.approver) ? (
                        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">Returned for Revision By</p>
                            <p className="mt-1 text-sm font-semibold text-amber-900">{ppmp.approver?.name || approvalHistory?.[0]?.approver?.name}</p>
                            {approvalHistory?.[0]?.approved_at ? (
                                <p className="mt-0.5 text-xs text-amber-700">Date returned: {formatDate(approvalHistory[0].approved_at)}</p>
                            ) : null}
                            {(approvalHistory?.[0]?.remarks || ppmp.remarks) ? (
                                <p className="mt-1.5 text-xs text-amber-800">
                                    <span className="font-semibold">Revision Instructions:</span> {approvalHistory?.[0]?.remarks || ppmp.remarks}
                                </p>
                            ) : null}
                        </div>
                    ) : null}

                    {ppmp.preparer ? (
                        <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Prepared By</p>
                            <p className="mt-1 text-sm text-slate-700">{ppmp.preparer.name}</p>
                        </div>
                    ) : null}

                    <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</p>
                        <p className="mt-1 text-sm text-slate-700">{approvalHistory?.[0]?.remarks || ppmp.remarks || '-'}</p>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h4 className="text-xl font-semibold text-slate-800">Planned Items</h4>
                    </div>

                    {ppmp.items.length === 0 ? (
                        <div className="px-6 py-8 text-sm text-slate-600">No PPMP items yet.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Qty</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit Cost</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Subtotal</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Schedule</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Fund Source</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-700">Specification</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {ppmp.items.map((item) => {
                                        const piecesPerBox = item.item?.pieces_per_box ?? 1;
                                        const subUnit = item.item?.sub_unit ?? 'piece';
                                        const totalPieces = item.quantity * piecesPerBox;

                                        return (
                                        <tr key={item.ppmp_item_id}>
                                            <td className="px-4 py-3 text-slate-700 font-medium">{item.item?.item_name ?? `Item #${item.ppmp_item_id}`}</td>
                                            <td className="px-4 py-3 text-slate-700">
                                                <span>{formatQuantity(item.quantity, item.item?.default_unit)}</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-xs font-semibold text-indigo-700">
                                                        ({totalPieces.toLocaleString()} {subUnit}s total)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {formatMoney(item.unit_cost)}
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-xs text-slate-500">
                                                        ≈ ₱{(item.unit_cost / piecesPerBox).toFixed(2)}/{subUnit}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-800">{formatMoney(item.quantity * item.unit_cost)}</td>
                                            <td className="px-4 py-3 text-slate-700">{item.procurement_schedule}</td>
                                            <td className="px-4 py-3"><span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800">{getFundSourceLabel(ppmp.fund_source)}</span></td>
                                            <td className="px-4 py-3 text-slate-700">{item.remarks || '-'}</td>
                                        </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap gap-3">
                        <Link href={route('office.ppmp.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to PPMP List</Link>
                        {canEdit ? (
                            <Link href={route('office.ppmp.edit', ppmp.ppmp_id)} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Edit PPMP</Link>
                        ) : null}
                        {isDraft ? (
                            <button
                                type="button"
                                onClick={() => setConfirmingDelete(true)}
                                className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                            >
                                Delete PPMP
                            </button>
                        ) : null}
                        {canSubmit ? (
                            <button
                                type="button"
                                onClick={() => setConfirmingSubmit(true)}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                            >
                                Submit PPMP for Approval
                            </button>
                        ) : null}
                    </div>
                </section>

            </div>

            {/* Submit Confirmation Modal */}
            <Modal show={confirmingSubmit} maxWidth="md" onClose={() => setConfirmingSubmit(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Submit PPMP for Executive Approval?</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Are you sure you want to submit <strong className="font-semibold text-slate-800">{ppmpCode}</strong> for executive review?
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                        Once submitted, editing will be locked while the Municipal Mayor / Executive Office conducts the annual budget authorization review.
                    </p>

                    <div className="mt-4">
                        <label className="block text-xs font-semibold uppercase tracking-wide text-slate-700">
                            Submission Remarks (Optional)
                        </label>
                        <textarea
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-red-600 focus:outline-none focus:ring-1 focus:ring-red-600"
                            rows={3}
                            placeholder="e.g. Submitted for FY 2026 Annual Budget appropriation..."
                            value={submitForm.data.remarks}
                            onChange={(e) => submitForm.setData('remarks', e.target.value)}
                        />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setConfirmingSubmit(false)}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            disabled={submitForm.processing}
                            onClick={() => {
                                submitForm.post(route('office.ppmp.submit', ppmp.ppmp_id), {
                                    onSuccess: () => setConfirmingSubmit(false),
                                });
                            }}
                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                        >
                            {submitForm.processing ? 'Submitting...' : 'Confirm Submission'}
                        </button>
                    </div>
                </div>
            </Modal>

            {/* Delete Draft Modal */}
            <Modal show={confirmingDelete} maxWidth="md" onClose={() => setConfirmingDelete(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Delete draft PPMP?</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Delete draft {ppmpCode}? This will remove it from your PPMP list.
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
                            Delete PPMP
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function Card({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-lg font-semibold text-slate-800">{value}</p>
        </div>
    );
}

function formatMoney(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

function formatQuantity(value: number, unit?: string | null): string {
    const quantity = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(value);

    return unit ? `${quantity} ${unit}` : quantity;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toUpperCase();
    const style =
        normalized === 'APPROVED'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'SUBMITTED'
                ? 'bg-blue-100 text-blue-700'
                : normalized === 'RETURNED_FOR_REVISION'
                    ? 'bg-amber-100 text-amber-700'
                    : normalized === 'REJECTED'
                        ? 'bg-rose-100 text-rose-700'
                        : 'bg-slate-100 text-slate-700';

    return <span className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${style}`}>{status.replaceAll('_', ' ')}</span>;
}



