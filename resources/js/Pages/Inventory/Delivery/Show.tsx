import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

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
    permissions?: {
        can_create_follow_up_purchase_request?: boolean;
    };
    discrepancy_summary?: {
        has_discrepancy: boolean;
        total_missing_quantity: number;
        total_damaged_quantity: number;
        affected_item_count: number;
    };
    follow_up_purchase_request?: {
        purchase_request_id: number;
        status: string;
        source_type?: string | null;
        purchase_order_count?: number;
    } | null;
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
    const canCreateFollowUpPurchaseRequest = delivery.permissions?.can_create_follow_up_purchase_request === true;
    const { post, processing } = useForm({ remarks: '' });
    const [activeTab, setActiveTab] = useState<'overview' | 'items' | 'status' | 'related' | 'notes'>('overview');

    const createFollowUpPurchaseRequest = () => {
        post(route('inventory.deliveries.follow-up-purchase-request.store', delivery.delivery_id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">Delivery {delivery.delivery_code}</h2>
                        <div className="flex flex-wrap gap-2">
                            {canVerify ? (
                                <Link href={route('inventory.delivery-verifications.create', delivery.delivery_id)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Verify Delivery
                                </Link>
                            ) : null}
                            {canCreateFollowUpPurchaseRequest ? (
                                <button
                                    type="button"
                                    onClick={createFollowUpPurchaseRequest}
                                    disabled={processing}
                                    className="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing ? 'Creating Follow-up PR...' : 'Create Follow-up PR'}
                                </button>
                            ) : null}
                        </div>
                    </div>
                    <Link
                        href={route('inventory.deliveries.index')}
                        className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        Back to Delivery Monitoring
                    </Link>
                </div>
            }
        >
            <Head title={`Delivery ${delivery.delivery_code}`} />

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
                    <>
                        {delivery.receipt_status === 'PARTIALLY_RECEIVED' ? (
                            <section className="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                                <h3 className="text-sm font-semibold text-amber-900">Partially Received Delivery</h3>
                                <p className="mt-2 text-sm text-amber-800">
                                    This delivery was already verified with discrepancies. Re-verification is not allowed. Missing or damaged quantities should be endorsed through a linked follow-up purchase request for BAC/procurement processing.
                                </p>

                                {delivery.discrepancy_summary?.has_discrepancy ? (
                                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                        <InfoCell label="Affected Items" value={delivery.discrepancy_summary.affected_item_count} />
                                        <InfoCell label="Missing Qty" value={delivery.discrepancy_summary.total_missing_quantity} />
                                        <InfoCell label="Damaged Qty" value={delivery.discrepancy_summary.total_damaged_quantity} />
                                    </div>
                                ) : null}

                                {delivery.follow_up_purchase_request ? (
                                    <div className="mt-4 rounded-lg border border-amber-300 bg-white p-4 text-sm text-slate-700">
                                        <p className="font-semibold text-slate-900">
                                            Linked Follow-up PR: PR-{String(delivery.follow_up_purchase_request.purchase_request_id).padStart(6, '0')}
                                        </p>
                                        <p className="mt-1">Status: {humanizeEnum(delivery.follow_up_purchase_request.status)}</p>
                                        <p className="mt-1">Linked POs: {delivery.follow_up_purchase_request.purchase_order_count ?? 0}</p>
                                    </div>
                                ) : (
                                    <p className="mt-4 text-sm text-amber-900">
                                        No follow-up purchase request has been created yet for this discrepancy record.
                                    </p>
                                )}
                            </section>
                        ) : null}

                        <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4">
                            <InfoCell label="Delivery Code" value={delivery.delivery_code} mono />
                            <InfoCell label="Status" value={<span className={statusClass(delivery.receipt_status)}>{humanizeEnum(delivery.receipt_status)}</span>} />
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
                                    <MetaRow label="Status" value={delivery.purchase_order?.status ? humanizeEnum(delivery.purchase_order.status) : '-'} />
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
                    </>
                ) : null}

                {activeTab === 'items' ? (
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
                ) : null}

                {activeTab === 'status' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-3 text-sm font-semibold text-slate-900">Verifications</h3>
                        {delivery.verifications.length === 0 ? (
                            <p className="text-sm text-slate-500">No verification records yet for this delivery.</p>
                        ) : (
                            <div className="space-y-3">
                                {delivery.verifications.map((verification) => (
                                    <article key={verification.delivery_verification_id} className="rounded-lg border border-slate-200 p-4">
                                        <div className="mb-1 flex items-center justify-between gap-3">
                                            <p className="text-sm font-semibold text-slate-800">{verification.verifier?.full_name ?? verification.verifier?.name ?? '-'}</p>
                                            <span className={verificationClass(verification.verification_status)}>{humanizeEnum(verification.verification_status)}</span>
                                        </div>
                                        <p className="text-xs text-slate-500">{new Date(verification.verification_date).toLocaleDateString()}</p>
                                        {verification.discrepancy_notes ? <p className="mt-2 text-sm text-slate-700">{verification.discrepancy_notes}</p> : null}
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                ) : null}

                {activeTab === 'related' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-900">Related Records</h3>
                        <dl className="mt-3 space-y-2 text-sm">
                            <MetaRow label="Linked Purchase Order" value={delivery.purchase_order?.po_number ?? '-'} />
                            <MetaRow label="Supplier" value={delivery.supplier?.supplier_name ?? '-'} />
                        </dl>
                    </section>
                ) : null}

                {activeTab === 'notes' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-900">Notes / Attachments</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            Delivery attachments and discrepancy notes are recorded through verification and supporting document modules.
                        </p>
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





