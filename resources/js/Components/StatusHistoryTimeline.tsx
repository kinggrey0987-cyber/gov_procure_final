type StatusItem = {
    id: number;
    old_status?: string | null;
    new_status: string;
    remarks?: string | null;
    changed_at?: string | null;
    changed_by?: { id: number; name: string } | null;
};

type Props = {
    entries: StatusItem[];
};

export default function StatusHistoryTimeline({ entries }: Props) {
    return (
        <section className="rounded-lg border bg-white p-6">
            <h3 className="text-base font-semibold text-slate-900">Status Timeline</h3>
            {entries.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">No status history yet.</p>
            ) : (
                <ol className="mt-4 space-y-3">
                    {entries.map((entry) => (
                        <li key={entry.id} className="border-l-2 border-slate-200 pl-3">
                            <p className="text-sm font-medium text-slate-900">
                                {entry.old_status ? `${entry.old_status} -> ` : ''}
                                {entry.new_status}
                            </p>
                            <p className="text-xs text-slate-600">By: {entry.changed_by?.name ?? '-'}</p>
                            <p className="text-xs text-slate-600">At: {entry.changed_at ? new Date(entry.changed_at).toLocaleString() : '-'}</p>
                            <p className="text-xs text-slate-600">Remarks: {entry.remarks ?? '-'}</p>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
