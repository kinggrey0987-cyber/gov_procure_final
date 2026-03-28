import AuditLogTable from '@/Components/AuditLogTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Props = {
    auditLogs: {
        data: Array<{
            id: number;
            log_domain: string;
            module_name: string;
            action_type: string;
            reference_type: string;
            reference_id: number;
            created_at: string;
            user?: { id: number; name: string } | null;
            office?: { id: number; name: string } | null;
        }>;
    };
    filters: {
        module_name?: string;
        action_type?: string;
        user_id?: number | string;
        from_date?: string;
        to_date?: string;
    };
};

export default function TechnicalAudit({ auditLogs, filters }: Props) {
    const [form, setForm] = useState({
        module_name: filters.module_name ?? '',
        action_type: filters.action_type ?? '',
        user_id: filters.user_id ? String(filters.user_id) : '',
        from_date: filters.from_date ?? '',
        to_date: filters.to_date ?? '',
    });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('admin-tech.audit.technical'), form, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Technical Audit Logs</h2>}>
            <Head title="Technical Audit Logs" />
            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <form onSubmit={onSubmit} className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5">
                        <input className="rounded border-slate-300 text-sm" placeholder="Module" value={form.module_name} onChange={(e) => setForm((v) => ({ ...v, module_name: e.target.value }))} />
                        <input className="rounded border-slate-300 text-sm" placeholder="Action" value={form.action_type} onChange={(e) => setForm((v) => ({ ...v, action_type: e.target.value }))} />
                        <input className="rounded border-slate-300 text-sm" placeholder="User ID" value={form.user_id} onChange={(e) => setForm((v) => ({ ...v, user_id: e.target.value }))} />
                        <input type="date" className="rounded border-slate-300 text-sm" value={form.from_date} onChange={(e) => setForm((v) => ({ ...v, from_date: e.target.value }))} />
                        <input type="date" className="rounded border-slate-300 text-sm" value={form.to_date} onChange={(e) => setForm((v) => ({ ...v, to_date: e.target.value }))} />
                        <div className="md:col-span-5">
                            <button className="rounded bg-slate-900 px-4 py-2 text-xs font-semibold text-white" type="submit">Apply Filters</button>
                        </div>
                    </form>

                    <AuditLogTable logs={auditLogs.data} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
