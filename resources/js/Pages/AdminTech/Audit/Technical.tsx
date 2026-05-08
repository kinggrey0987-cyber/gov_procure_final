import AuditLogTable from '@/Components/AuditLogTable';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        from: number;
        to: number;
        total: number;
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
    const debouncedModuleName = useDebouncedValue(form.module_name, 400);
    const debouncedActionType = useDebouncedValue(form.action_type, 400);
    const debouncedUserId = useDebouncedValue(form.user_id, 400);

    useEffect(() => {
        setForm({
            module_name: filters.module_name ?? '',
            action_type: filters.action_type ?? '',
            user_id: filters.user_id ? String(filters.user_id) : '',
            from_date: filters.from_date ?? '',
            to_date: filters.to_date ?? '',
        });
    }, [filters.action_type, filters.from_date, filters.module_name, filters.to_date, filters.user_id]);

    const activeFilters = useMemo(() => ({
        module_name: debouncedModuleName || undefined,
        action_type: debouncedActionType || undefined,
        user_id: debouncedUserId || undefined,
        from_date: form.from_date || undefined,
        to_date: form.to_date || undefined,
    }), [debouncedActionType, debouncedModuleName, debouncedUserId, form.from_date, form.to_date]);

    const activeFiltersKey = useMemo(() => JSON.stringify(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => JSON.stringify({
        module_name: filters.module_name || undefined,
        action_type: filters.action_type || undefined,
        user_id: filters.user_id ? String(filters.user_id) : undefined,
        from_date: filters.from_date || undefined,
        to_date: filters.to_date || undefined,
    }), [filters.action_type, filters.from_date, filters.module_name, filters.to_date, filters.user_id]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('admin-tech.audit.technical'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['auditLogs', 'filters'],
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

    const handleClearFilters = () => {
        router.get(route('admin-tech.audit.technical'), {}, {
            preserveState: true,
            replace: true,
            only: ['auditLogs', 'filters'],
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Technical Audit Logs</h2>}>
            <Head title="Technical Audit Logs" />
            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5">
                        <input
                            className="rounded border-slate-300 text-sm"
                            placeholder="Module"
                            value={form.module_name}
                            onChange={(e) => setForm((v) => ({ ...v, module_name: e.target.value }))}
                        />
                        <input
                            className="rounded border-slate-300 text-sm"
                            placeholder="Action"
                            value={form.action_type}
                            onChange={(e) => setForm((v) => ({ ...v, action_type: e.target.value }))}
                        />
                        <input
                            className="rounded border-slate-300 text-sm"
                            placeholder="User ID"
                            value={form.user_id}
                            onChange={(e) => setForm((v) => ({ ...v, user_id: e.target.value }))}
                        />
                        <input
                            type="date"
                            className="rounded border-slate-300 text-sm"
                            value={form.from_date}
                            onChange={(e) => setForm((v) => ({ ...v, from_date: e.target.value }))}
                        />
                        <input
                            type="date"
                            className="rounded border-slate-300 text-sm"
                            value={form.to_date}
                            onChange={(e) => setForm((v) => ({ ...v, to_date: e.target.value }))}
                        />
                        <div className="md:col-span-5 flex gap-2">
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="rounded border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <AuditLogTable logs={auditLogs.data} />

                    {auditLogs.data.length > 0 && (
                        <div className="border-t border-slate-200 bg-white rounded-lg p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <p className="text-sm text-slate-600">
                                    Showing <span className="font-semibold">{auditLogs.from}</span> to <span className="font-semibold">{auditLogs.to}</span> of <span className="font-semibold">{auditLogs.total}</span> log(s)
                                </p>
                                <p className="text-sm text-slate-600">
                                    Page <span className="font-semibold">{auditLogs.current_page}</span> of <span className="font-semibold">{auditLogs.last_page}</span>
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {auditLogs.prev_page_url ? (
                                    <Link
                                        href={auditLogs.prev_page_url}
                                        className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <path d="M15 19l-7-7 7-7" />
                                        </svg>
                                        Previous
                                    </Link>
                                ) : (
                                    <button
                                        disabled
                                        className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed"
                                    >
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <path d="M15 19l-7-7 7-7" />
                                        </svg>
                                        Previous
                                    </button>
                                )}
                                {auditLogs.next_page_url ? (
                                    <Link
                                        href={auditLogs.next_page_url}
                                        className="ml-auto inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        Next
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <path d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                ) : (
                                    <button
                                        disabled
                                        className="ml-auto inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed"
                                    >
                                        Next
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <path d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





