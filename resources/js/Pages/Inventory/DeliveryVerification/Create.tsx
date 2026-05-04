import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    items?: Array<{
        delivery_item_id: number;
        expected_quantity: number | string;
        received_quantity: number | string;
        damaged_quantity?: number | string;
        missing_quantity?: number | string;
        purchase_order_item?: {
            item?: {
                item_name?: string;
            } | null;
        } | null;
        item?: {
            item_name?: string;
        };
    }>;
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

export default function DeliveryVerificationCreate({ delivery }: Props) {
    const { data, setData, post, processing, errors } = useForm<VerificationForm>({
        verification_date: new Date().toISOString().split('T')[0] || '',
        verification_status: '',
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
                {delivery.items && delivery.items.length > 0 ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-3 text-sm font-semibold text-slate-900">Received Item Snapshot</h3>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Expected</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Received</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Damaged</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Missing</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {delivery.items.map((item) => (
                                        <tr key={item.delivery_item_id}>
                                            <td className="px-4 py-2 text-slate-700">{item.purchase_order_item?.item?.item_name ?? item.item?.item_name ?? '-'}</td>
                                            <td className="px-4 py-2 text-right text-slate-700">{item.expected_quantity}</td>
                                            <td className="px-4 py-2 text-right text-slate-800">{item.received_quantity}</td>
                                            <td className="px-4 py-2 text-right text-rose-700">{item.damaged_quantity ?? 0}</td>
                                            <td className="px-4 py-2 text-right text-amber-700">{item.missing_quantity ?? 0}</td>
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
                                    value={data.verification_date}
                                    onChange={(e) => setData('verification_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
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
