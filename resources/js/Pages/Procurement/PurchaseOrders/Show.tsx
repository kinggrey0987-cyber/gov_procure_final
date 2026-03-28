import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import ApprovalHistoryTimeline from '@/Components/ApprovalHistoryTimeline';
import StatusHistoryTimeline from '@/Components/StatusHistoryTimeline';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PurchaseOrder = {
    purchase_order_id: number;
    po_number: string;
    status: string;
    total_amount: string;
    remarks: string | null;
};

type Props = {
    purchaseOrder: PurchaseOrder;
    attachments: Array<{
        document_id: number;
        title: string;
        file_name: string;
        uploaded_at?: string | null;
        uploader_name?: string | null;
    }>;
    attachmentPermissions: {
        upload: boolean;
        delete: boolean;
    };
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

export default function PurchaseOrdersShow({ purchaseOrder, attachments, attachmentPermissions, statusHistory, approvalHistory }: Props) {
    const placeForm = useForm({ remarks: '' });

    const placeOrder = (event: FormEvent) => {
        event.preventDefault();
        placeForm.post(route('procurement.purchase-orders.place', purchaseOrder.purchase_order_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Purchase Order</h2>}>
            <Head title="Purchase Order" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-6 text-sm">
                        <p><strong>ID:</strong> {purchaseOrder.purchase_order_id}</p>
                        <p><strong>PO Number:</strong> {purchaseOrder.po_number}</p>
                        <p><strong>Status:</strong> {purchaseOrder.status}</p>
                        <p><strong>Total Amount:</strong> {purchaseOrder.total_amount}</p>
                        <p><strong>Remarks:</strong> {purchaseOrder.remarks ?? '-'}</p>
                    </div>

                    <div className="flex gap-3">
                        <Link href={route('procurement.purchase-orders.edit', purchaseOrder.purchase_order_id)} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Edit
                        </Link>
                        <Link href={route('procurement.purchase-orders.items.index', purchaseOrder.purchase_order_id)} className="rounded bg-emerald-600 px-4 py-2 text-sm text-white">
                            Manage Items
                        </Link>
                    </div>

                    {purchaseOrder.status === 'DRAFT' && (
                        <form onSubmit={placeOrder} className="space-y-3 rounded-lg border bg-white p-6">
                            <label className="mb-1 block text-sm text-gray-700">Placement Remarks</label>
                            <textarea
                                value={placeForm.data.remarks}
                                onChange={(event) => placeForm.setData('remarks', event.target.value)}
                                className="w-full rounded border-gray-300 text-sm"
                                rows={3}
                            />
                            <button type="submit" className="rounded bg-amber-600 px-4 py-2 text-sm text-white">
                                Place Purchase Order
                            </button>
                        </form>
                    )}

                    <AttachmentsPanel
                        title="Purchase Order Attachments"
                        attachments={attachments}
                        uploadRoute={route('documents.store', {
                            related_module: 'purchase_orders',
                            related_reference_id: purchaseOrder.purchase_order_id,
                        })}
                        canUpload={attachmentPermissions.upload}
                        canDelete={attachmentPermissions.delete}
                        downloadRouteFor={(documentId: number) => route('documents.show', documentId)}
                        deleteRouteFor={(documentId: number) => route('documents.destroy', documentId)}
                    />

                    <StatusHistoryTimeline entries={statusHistory} />
                    <ApprovalHistoryTimeline entries={approvalHistory} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
