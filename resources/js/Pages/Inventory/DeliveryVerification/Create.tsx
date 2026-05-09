import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    actual_delivery_date?: string | null;
    expected_arrival_date?: string | null;
    purchase_order?: {
        po_number?: string | null;
    } | null;
    supplier?: {
        supplier_name?: string | null;
    } | null;
    items?: Array<{
        delivery_item_id: number;
        item_name: string;
        unit?: string | null;
        expected_quantity: number;
        received_quantity: number;
        damaged_quantity: number;
        missing_quantity: number;
        accepted_quantity: number;
        item_condition: string;
        has_discrepancy: boolean;
        remarks?: string | null;
    }>;
    verification_summary?: {
        item_count: number;
        has_discrepancy: boolean;
        recommended_status: 'APPROVED' | 'REJECTED';
        message: string;
    };
};

type VerificationForm = {
    verification_date: string;
    verification_status: '' | 'APPROVED' | 'REJECTED';
    discrepancy_notes: string;
    remarks: string;
};

type Props = {
    delivery: Delivery;
};

const todayDate = (): string => {
    const date = new Date();
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return offsetDate.toISOString().split('T')[0];
};

export default function DeliveryVerificationCreate({ delivery }: Props) {
    const minimumVerificationDate = delivery.actual_delivery_date ?? undefined;

    const { data, setData, post, processing, errors } = useForm<VerificationForm>({
        verification_date: todayDate(),
        verification_status: delivery.verification_summary?.recommended_status ?? '',
        discrepancy_notes: '',
        remarks: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('inventory.delivery-verifications.store', delivery.delivery_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Verify Delivery {delivery.delivery_code}</h2>}>
            <Head title={`Verify ${delivery.delivery_code}`} />

            <div className="space-y-6 py-8">
                <Link
                    href={route('inventory.deliveries.show', delivery.delivery_id)}
                    className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                >
                    Back to Delivery Details
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-4">
                        <Info label="Delivery Code" value={delivery.delivery_code} />
                        <Info label="Purchase Order" value={delivery.purchase_order?.po_number ?? '-'} />
                        <Info label="Supplier" value={delivery.supplier?.supplier_name ?? '-'} />
                        <Info label="Actual Delivery Date" value={delivery.actual_delivery_date ?? '-'} />
                    </div>
                    {delivery.verification_summary ? (
                        <div className={`mt-4 rounded-lg border px-4 py-3 text-sm ${delivery.verification_summary.has_discrepancy ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
                            <p className="font-semibold">Recommended Status: {humanizeEnum(delivery.verification_summary.recommended_status)}</p>
                            <p className="mt-1">{delivery.verification_summary.message}</p>
                        </div>
                    ) : null}
                </section>

                {delivery.items && delivery.items.length > 0 ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-1 text-sm font-semibold text-slate-900">Item Reconciliation</h3>
                        <p className="mb-3 text-sm text-slate-600">Compare purchase order quantity against actual received, damaged, and missing quantities before verification.</p>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Expected</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Received</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Damaged</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Missing</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Accepted</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Decision Signal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {delivery.items.map((item) => (
                                        <tr key={item.delivery_item_id} className={item.has_discrepancy ? 'bg-amber-50/60' : ''}>
                                            <td className="px-4 py-2 text-slate-700">{item.item_name}</td>
                                            <td className="px-4 py-2 text-right text-slate-700">{formatQty(item.expected_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right text-slate-800">{formatQty(item.received_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right text-rose-700">{formatQty(item.damaged_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right text-amber-700">{formatQty(item.missing_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-slate-800">{formatQty(item.accepted_quantity, item.unit)}</td>
                                            <td className="px-4 py-2">
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.has_discrepancy ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700'}`}>
                                                    {item.has_discrepancy ? 'Discrepancy' : 'Matches PO'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}

                <div className="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField label="Verification Date" required error={errors.verification_date}>
                                <input
                                    type="date"
                                    min={minimumVerificationDate}
                                    max={todayDate()}
                                    value={data.verification_date}
                                    onChange={(e) => setData('verification_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <p className="mt-1 text-xs text-slate-500">Verification date cannot be before the actual delivery date and cannot be future-dated.</p>
                            </FormField>

                            <FormField label="Verification Status" required error={errors.verification_status}>
                                <select
                                    value={data.verification_status}
                                    onChange={(e) => setData('verification_status', e.target.value as VerificationForm['verification_status'])}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                >
                                    <option value="">Select status</option>
                                    <option value="APPROVED">Approved</option>
                                    <option value="REJECTED">Rejected</option>
                                </select>
                                <p className="mt-1 text-xs text-slate-500">Use Approved only when all item lines match. Use Rejected when damaged or missing quantities need BAC/procurement follow-up.</p>
                            </FormField>
                        </div>

                        <FormField label="Discrepancy Notes" error={errors.discrepancy_notes}>
                            <textarea
                                rows={4}
                                value={data.discrepancy_notes}
                                onChange={(e) => setData('discrepancy_notes', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                placeholder="Add discrepancy details if found."
                            />
                        </FormField>

                        <FormField label="Remarks" error={errors.remarks}>
                            <textarea
                                rows={3}
                                value={data.remarks}
                                onChange={(e) => setData('remarks', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            />
                        </FormField>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Verifying...' : 'Verify Delivery'}
                            </button>
                            <Link href={route('inventory.deliveries.show', delivery.delivery_id)} className="rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-semibold text-slate-800">{value}</p>
        </div>
    );
}

function formatQty(value: number, unit?: string | null): string {
    return `${Number(value).toFixed(2)}${unit ? ` ${unit}` : ''}`;
}

function FormField({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-semibold text-slate-700">
                {label}
                {required ? <span className="ml-1 text-rose-600">*</span> : null}
            </label>
            {children}
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}





