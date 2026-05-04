import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { getFundSourceLabel } from '@/constants/fundSources';

type PpmpItem = {
    ppmp_item_id: number;
    quantity: number;
    unit_cost: number;
    procurement_schedule: string;
    remarks?: string | null;
    item?: { item_name?: string | null } | null;
};

type Props = {
    ppmp: {
        ppmp_id: number;
        fiscal_year: number;
        fund_source: string;
        status: string;
        date_prepared: string;
        remarks: string | null;
        items: PpmpItem[];
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

    const totalBudget = ppmp.items.reduce((total, item) => total + item.quantity * item.unit_cost, 0);

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
                            <h3 className="text-2xl font-bold text-red-700">PPMP-{ppmp.fiscal_year}-{String(ppmp.ppmp_id).padStart(4, '0')}</h3>
                            <p className="mt-2 text-sm text-slate-600">Date prepared: {ppmp.date_prepared}</p>
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

                    <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</p>
                        <p className="mt-1 text-sm text-slate-700">{ppmp.remarks ?? '-'}</p>
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
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {ppmp.items.map((item) => (
                                        <tr key={item.ppmp_item_id}>
                                            <td className="px-4 py-3 text-slate-700">{item.item?.item_name ?? `Item #${item.ppmp_item_id}`}</td>
                                            <td className="px-4 py-3 text-slate-700">{item.quantity}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatMoney(item.unit_cost)}</td>
                                            <td className="px-4 py-3 text-slate-700">{formatMoney(item.quantity * item.unit_cost)}</td>
                                            <td className="px-4 py-3 text-slate-700">{item.procurement_schedule}</td>
                                            <td className="px-4 py-3"><span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800">{getFundSourceLabel(ppmp.fund_source)}</span></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap gap-3">
                        <Link href={route('office.ppmp.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to PPMP List</Link>
                        {canEdit ? (
                            <Link href={route('office.ppmp.edit', ppmp.ppmp_id)} className="rounded-lg border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700">Edit PPMP</Link>
                        ) : null}
                        {canSubmit ? (
                            <button onClick={() => submitForm.post(route('office.ppmp.submit', ppmp.ppmp_id))} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">
                                Submit PPMP
                            </button>
                        ) : null}
                    </div>
                </section>

            </div>
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
