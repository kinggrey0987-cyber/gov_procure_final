type AuditLogItem = {
    id: number;
    log_domain: string;
    module_name: string;
    action_type: string;
    reference_type: string;
    reference_id: number;
    created_at: string;
    user?: { id: number; name: string } | null;
    office?: { id: number; name: string } | null;
};

type Props = {
    logs: AuditLogItem[];
};

export default function AuditLogTable({ logs }: Props) {
    return (
        <div className="rounded-lg border bg-white p-4">
            {logs.length === 0 ? (
                <div className="py-6 text-sm text-slate-500">No audit logs found for the selected filters.</div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-xs uppercase tracking-wide text-slate-500">
                                <th className="py-2 pr-3">When</th>
                                <th className="py-2 pr-3">Domain</th>
                                <th className="py-2 pr-3">Module</th>
                                <th className="py-2 pr-3">Action</th>
                                <th className="py-2 pr-3">Actor</th>
                                <th className="py-2 pr-3">Office</th>
                                <th className="py-2 pr-3">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.map((log) => (
                                <tr key={log.id} className="border-b align-top">
                                    <td className="py-2 pr-3 text-xs text-slate-600">{new Date(log.created_at).toLocaleString()}</td>
                                    <td className="py-2 pr-3">{log.log_domain}</td>
                                    <td className="py-2 pr-3">{log.module_name}</td>
                                    <td className="py-2 pr-3">{log.action_type}</td>
                                    <td className="py-2 pr-3">{log.user?.name ?? '-'}</td>
                                    <td className="py-2 pr-3">{log.office?.name ?? '-'}</td>
                                    <td className="py-2 pr-3 text-xs text-slate-600">{log.reference_type} #{log.reference_id}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}





