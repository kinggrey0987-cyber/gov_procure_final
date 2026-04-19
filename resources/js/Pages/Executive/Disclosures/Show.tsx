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
        <AuthenticatedLayout header="Disclosure Detail">
            <Head title="Disclosure Detail" />
            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white px-6 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Disclosure Governance</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">Disclosure Record Detail</h3>
                    <p className="mt-1 text-sm text-slate-600">Detailed disclosure record context for executive monitoring and accountability checks.</p>
                </section>

                <div className="space-y-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm space-y-1 shadow-sm">
                        <p><strong>ID:</strong> {disclosure.disclosure_id}</p>
                        <p><strong>Title:</strong> {disclosure.title}</p>
                        <p><strong>Type:</strong> {disclosure.type}</p>
                        <p><strong>Status:</strong> {disclosure.status}</p>
                        <p><strong>Description:</strong> {disclosure.description ?? '-'}</p>
                        <p><strong>File:</strong> {disclosure.file_path ?? '-'}</p>
                    </div>

                    <div className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        This view is read-only for executive monitoring.
                    </div>

                    <StatusHistoryTimeline entries={statusHistory} />
                    <ApprovalHistoryTimeline entries={approvalHistory} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
