import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ApprovalHistoryTimeline from '@/Components/ApprovalHistoryTimeline';
import StatusHistoryTimeline from '@/Components/StatusHistoryTimeline';
import { Head } from '@inertiajs/react';

type Props = {
    disclosure: {
        disclosure_id: number;
        title: string;
        type: string;
        status: string;
        description?: string | null;
        file_path?: string | null;
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

export default function DisclosureShow({ disclosure, statusHistory, approvalHistory }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Disclosure Detail</h2>}>
            <Head title="Disclosure Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <p><strong>ID:</strong> {disclosure.disclosure_id}</p>
                        <p><strong>Title:</strong> {disclosure.title}</p>
                        <p><strong>Type:</strong> {disclosure.type}</p>
                        <p><strong>Status:</strong> {disclosure.status}</p>
                        <p><strong>Description:</strong> {disclosure.description ?? '-'}</p>
                        <p><strong>File:</strong> {disclosure.file_path ?? '-'}</p>
                    </div>

                    <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        This view is read-only for executive monitoring.
                    </div>

                    <StatusHistoryTimeline entries={statusHistory} />
                    <ApprovalHistoryTimeline entries={approvalHistory} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
