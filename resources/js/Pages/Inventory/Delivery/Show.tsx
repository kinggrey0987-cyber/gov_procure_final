import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type DeliveryItem = {
    delivery_item_id: number;
    expected_quantity: number | string;
    received_quantity: number | string;
    damaged_quantity: number | string;
    missing_quantity: number | string;
    item_condition?: string | null;
    item?: {
        item_name?: string;
    };
};

type DeliveryVerification = {
    delivery_verification_id: number;
    verification_status: string;
    verification_date: string;
    discrepancy_notes?: string | null;
    verifier?: {
        full_name?: string;
        name?: string;
    };
};

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    created_at: string;
    purchase_order?: {
        purchase_order_id: number;
        po_number?: string;
        status?: string;
    };
    supplier?: {
        supplier_name?: string;
        contact_person?: string | null;
    };
    receiver?: {
        name?: string;
    };
    items: DeliveryItem[];
    verifications: DeliveryVerification[];
};

type Props = {
    delivery: Delivery;
};

export default function DeliveryShow({ delivery }: Props) {
    const canVerify = delivery.receipt_status === 'RECEIVED';
    const canEdit = delivery.receipt_status !== 'VERIFIED';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Delivery {delivery.delivery_code}</h2>
                    <div className="flex flex-wrap gap-2">
                        {canVerify ? (
                            <Link href={route('inventory.delivery-verifications.create', delivery.delivery_id)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Verify Delivery
                            </Link>
                        ) : null}
                        {canEdit ? (
                            <Link href={route('inventory.deliveries.edit', delivery.delivery_id)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Edit
                            </Link>
                        ) : null}
                    </div>
                </div>
            }
        >
            <Head title={`Delivery ${delivery.delivery_code}`} />

            <div className="space-y-6 py-8">
                <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4">
                    <InfoCell label="Delivery Code" value={delivery.delivery_code} mono />
                    <InfoCell label="Status" value={<span className={statusClass(delivery.receipt_status)}>{delivery.receipt_status}</span>} />
                    <InfoCell label="Received By" value={delivery.receiver?.name ?? '-'} />
                    <InfoCell label="Created" value={new Date(delivery.created_at).toLocaleDateString()} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-900">Purchase Order</h3>
                        <dl className="mt-3 space-y-2 text-sm">
                            <MetaRow
                                label="PO Number"
                                value={delivery.purchase_order?.po_number ?? '-'}
                            />
                            <MetaRow label="Status" value={delivery.purchase_order?.status ?? '-'} />
                        </dl>
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-900">Supplier</h3>
                        <dl className="mt-3 space-y-2 text-sm">
                            <MetaRow label="Name" value={delivery.supplier?.supplier_name ?? '-'} />
                            <MetaRow label="Contact" value={delivery.supplier?.contact_person ?? 'N/A'} />
                        </dl>
                    </section>
                </div>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="mb-3 text-sm font-semibold text-slate-900">Delivery Items</h3>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                    <th className="px-4 py-2 text-center font-semibold text-slate-700">Expected</th>
                                    <th className="px-4 py-2 text-center font-semibold text-slate-700">Received</th>
                                    <th className="px-4 py-2 text-center font-semibold text-slate-700">Damaged</th>
                                    <th className="px-4 py-2 text-center font-semibold text-slate-700">Missing</th>
                                    <th className="px-4 py-2 text-left font-semibold text-slate-700">Condition</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {delivery.items.map((item) => (
                                    <tr key={item.delivery_item_id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-2 text-slate-700">{item.item?.item_name ?? '-'}</td>
                                        <td className="px-4 py-2 text-center text-slate-700">{item.expected_quantity}</td>
                                        <td className="px-4 py-2 text-center font-semibold text-slate-800">{item.received_quantity}</td>
                                        <td className="px-4 py-2 text-center text-rose-600">{item.damaged_quantity}</td>
                                        <td className="px-4 py-2 text-center text-amber-600">{item.missing_quantity}</td>
                                        <td className="px-4 py-2 text-slate-700">{item.item_condition || '-'}</td>
                                    </tr>
                                ))}
                                {delivery.items.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">
                                            No delivery items are linked to this record yet.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>

                {delivery.verifications.length > 0 ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-3 text-sm font-semibold text-slate-900">Verifications</h3>
                        <div className="space-y-3">
                            {delivery.verifications.map((verification) => (
                                <article key={verification.delivery_verification_id} className="rounded-lg border border-slate-200 p-4">
                                    <div className="mb-1 flex items-center justify-between gap-3">
                                        <p className="text-sm font-semibold text-slate-800">{verification.verifier?.full_name ?? verification.verifier?.name ?? '-'}</p>
                                        <span className={verificationClass(verification.verification_status)}>{verification.verification_status}</span>
                                    </div>
                                    <p className="text-xs text-slate-500">{new Date(verification.verification_date).toLocaleDateString()}</p>
                                    {verification.discrepancy_notes ? <p className="mt-2 text-sm text-slate-700">{verification.discrepancy_notes}</p> : null}
                                </article>
                            ))}
                        </div>
                    </section>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}

function InfoCell({
    label,
    value,
    mono,
}: {
    label: string;
    value: ReactNode;
    mono?: boolean;
}) {
    return (
        <div>
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`mt-1 text-sm ${mono ? 'font-mono text-xs text-slate-700' : 'text-slate-800'}`}>{value}</p>
        </div>
    );
}

function MetaRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-slate-500">{label}</dt>
            <dd className="text-right text-slate-700">{value}</dd>
        </div>
    );
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        PENDING: `${base} bg-amber-100 text-amber-700`,
        RECEIVED: `${base} bg-blue-100 text-blue-700`,
        VERIFIED: `${base} bg-emerald-100 text-emerald-700`,
        PARTIALLY_RECEIVED: `${base} bg-orange-100 text-orange-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}

function verificationClass(status: string) {
    const base = 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold';
    const map: Record<string, string> = {
        APPROVED: `${base} bg-emerald-100 text-emerald-700`,
        REJECTED: `${base} bg-rose-100 text-rose-700`,
    };

    return map[status] ?? `${base} bg-slate-100 text-slate-700`;
}
