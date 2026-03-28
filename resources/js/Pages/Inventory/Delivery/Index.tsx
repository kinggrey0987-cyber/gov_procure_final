import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type DeliveryRow = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    created_at: string;
    purchase_order?: {
        po_number?: string;
    };
    supplier?: {
        supplier_name?: string;
    };
    receiver?: {
        full_name?: string;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    deliveries: {
        data: DeliveryRow[];
        links: PaginationLink[];
    };
};

export default function DeliveryIndex({ deliveries }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Delivery Monitoring</h2>}>
            <Head title="Deliveries" />

            <div className="space-y-4 py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-sm text-slate-600">
                        Delivery Monitoring is the main intake and status page. Verification remains a contextual action per delivery record.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Code</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">PO</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Supplier</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Receipt Status</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Received By</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {deliveries.data.map((delivery) => (
                                <tr key={delivery.delivery_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{delivery.delivery_code}</td>
                                    <td className="px-4 py-3 text-slate-700">{delivery.purchase_order?.po_number ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{delivery.supplier?.supplier_name ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={statusClass(delivery.receipt_status)}>{delivery.receipt_status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{delivery.receiver?.full_name ?? '-'}</td>
                                    <td className="px-4 py-3 text-slate-600">{new Date(delivery.created_at).toLocaleDateString()}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            {delivery.receipt_status === 'PENDING' && (
                                                <Link href={route('inventory.deliveries.edit', delivery.delivery_id)} className="text-sm font-semibold text-amber-700 hover:text-amber-900">
                                                    Receive
                                                </Link>
                                            )}
                                            {(delivery.receipt_status === 'RECEIVED' || delivery.receipt_status === 'PARTIALLY_RECEIVED') && (
                                                <Link
                                                    href={route('inventory.delivery-verifications.create', delivery.delivery_id)}
                                                    className="text-sm font-semibold text-emerald-700 hover:text-emerald-900"
                                                >
                                                    Verify
                                                </Link>
                                            )}
                                            <Link href={route('inventory.deliveries.show', delivery.delivery_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                                View
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-2">
                    {deliveries.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            className={`rounded-md px-3 py-2 text-sm ${
                                link.active ? 'bg-red-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
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
