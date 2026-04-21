import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import ApprovalHistoryTimeline from '@/Components/ApprovalHistoryTimeline';
import StatusHistoryTimeline from '@/Components/StatusHistoryTimeline';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link } from '@inertiajs/react';

type Props = {
    claim: {
        claims_id: number;
        claim_code: string;
        status: string;
        claim_type: string;
        claim_date: string;
        claim_location: string;
        remarks?: string | null;
    };
    linkedDistribution?: {
        distribution_record_id: number;
        distribution_code: string;
        status: string;
        release_date?: string | null;
        office_name: string;
        request_code: string;
        items: Array<{
            distribution_item_id: number;
            item_name: string;
            category_name: string;
            quantity_released: number;
            unit: string;
        }>;
    } | null;
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

export default function ClaimShow({ claim, linkedDistribution, attachments, attachmentPermissions, statusHistory, approvalHistory }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Claim Detail</h2>
                    <Link href={route('inventory.claims.index')} className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to Claims and Adjustments
                    </Link>
                </div>
            }
        >
            <Head title="Claim Detail" />
            <div className="space-y-4 py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Claim Summary</h3>
                    <div className="mt-3 grid gap-4 md:grid-cols-2">
                        <Info label="Claim Code" value={claim.claim_code} mono />
                        <Info label="Status" value={displayClaimStatus(claim.status)} />
                        <Info label="Issue Type" value={displayClaimType(claim.claim_type)} />
                        <Info label="Date Observed" value={claim.claim_date} />
                        <Info label="Issue Location" value={claim.claim_location} />
                        <Info label="Remarks" value={claim.remarks ?? 'No remarks provided.'} />
                    </div>
                </div>

                {linkedDistribution ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-600">Linked Distribution Record</h3>
                        <div className="mt-3 grid gap-4 md:grid-cols-2">
                            <Info label="Distribution Code" value={linkedDistribution.distribution_code} mono />
                            <Info label="Distribution Status" value={linkedDistribution.status} />
                            <Info label="Office" value={linkedDistribution.office_name} />
                            <Info label="Office Request" value={linkedDistribution.request_code} mono />
                            <Info label="Release Date" value={linkedDistribution.release_date ?? '-'} />
                            <Info label="Record ID" value={String(linkedDistribution.distribution_record_id)} />
                        </div>

                        <div className="mt-4 overflow-hidden rounded-lg border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Category</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Released Qty</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Unit</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {linkedDistribution.items.map((item) => (
                                        <tr key={item.distribution_item_id} className="hover:bg-slate-50/60">
                                            <td className="px-4 py-2 text-slate-700">{item.item_name}</td>
                                            <td className="px-4 py-2 text-slate-600">{item.category_name || '-'}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-slate-700">{item.quantity_released.toFixed(2)}</td>
                                            <td className="px-4 py-2 text-slate-700">{item.unit}</td>
                                        </tr>
                                    ))}
                                    {linkedDistribution.items.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-4 py-6 text-center text-sm text-slate-500">
                                                No released item lines available for this distribution record.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                <AttachmentsPanel
                    title="Claim Attachments"
                    attachments={attachments}
                    uploadRoute={route('documents.store', {
                        related_module: 'claims',
                        related_reference_id: claim.claims_id,
                    })}
                    canUpload={attachmentPermissions.upload}
                    canDelete={attachmentPermissions.delete}
                    downloadRouteFor={(documentId: number) => route('documents.show', documentId)}
                    deleteRouteFor={(documentId: number) => route('documents.destroy', documentId)}
                />

                <StatusHistoryTimeline entries={statusHistory} />

                <ApprovalHistoryTimeline entries={approvalHistory} />
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-1 text-sm text-slate-800 ${mono ? 'font-mono text-xs' : ''}`}>{value}</p>
        </div>
    );
}

function displayClaimType(type: string): string {
    const map: Record<string, string> = {
        MISSING_ITEM: 'Missing Item',
        DAMAGED_ITEM: 'Damaged Item',
        QUANTITY_MISMATCH: 'Quantity Mismatch',
        WRONG_ITEM_RECEIVED: 'Wrong Item Received',
        SHORTAGE: 'Shortage',
    };

    return map[type] ?? humanizeEnum(type);
}

function displayClaimStatus(status: string): string {
    const map: Record<string, string> = {
        OPEN: 'Pending Review',
        IN_REVIEW: 'In Claim Review',
        RESOLVED: 'Resolved',
        REJECTED: 'Rejected',
    };

    return map[status] ?? humanizeEnum(status);
}
