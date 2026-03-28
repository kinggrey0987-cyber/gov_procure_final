type ApprovalItem = {
    id: number;
    status: string;
    remarks?: string | null;
    approved_at?: string | null;
    approver?: { id: number; name: string } | null;
};

type Props = {
    entries: ApprovalItem[];
};

export default function ApprovalHistoryTimeline({ entries }: Props) {
    return (
        <section className="rounded-lg border bg-white p-6">
            <h3 className="text-base font-semibold text-slate-900">Approval Timeline</h3>
            {entries.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">No approval history yet.</p>
            ) : (
                <ol className="mt-4 space-y-3">
                    {entries.map((entry) => (
                        <li key={entry.id} className="border-l-2 border-slate-200 pl-3">
                            <p className="text-sm font-medium text-slate-900">{entry.status}</p>
                            <p className="text-xs text-slate-600">By: {entry.approver?.name ?? '-'}</p>
                            <p className="text-xs text-slate-600">At: {entry.approved_at ? new Date(entry.approved_at).toLocaleString() : '-'}</p>
                            <p className="text-xs text-slate-600">Remarks: {entry.remarks ?? '-'}</p>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
