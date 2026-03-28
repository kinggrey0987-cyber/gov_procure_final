import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AttachmentsPanel from '@/Components/AttachmentsPanel';
import ApprovalHistoryTimeline from '@/Components/ApprovalHistoryTimeline';
import StatusHistoryTimeline from '@/Components/StatusHistoryTimeline';
import { Head } from '@inertiajs/react';

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

export default function ClaimShow({ claim, attachments, attachmentPermissions, statusHistory, approvalHistory }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Claim Detail</h2>}>
            <Head title="Claim Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm">
                        <p><strong>ID:</strong> {claim.claims_id}</p>
                        <p><strong>Claim Code:</strong> {claim.claim_code}</p>
                        <p><strong>Status:</strong> {claim.status}</p>
                        <p><strong>Type:</strong> {claim.claim_type}</p>
                        <p><strong>Date:</strong> {claim.claim_date}</p>
                        <p><strong>Location:</strong> {claim.claim_location}</p>
                        <p><strong>Remarks:</strong> {claim.remarks ?? '-'}</p>
                    </div>

                    <div className="mt-4">
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
                    </div>

                    <div className="mt-4">
                        <StatusHistoryTimeline entries={statusHistory} />
                    </div>

                    <div className="mt-4">
                        <ApprovalHistoryTimeline entries={approvalHistory} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
