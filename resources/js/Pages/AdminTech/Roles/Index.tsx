import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type RoleRow = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    users_count: number;
    is_active: boolean;
};

type TelemetryData = {
    db_latency_ms: number;
    memory_usage_mb: number;
    disk_used_percent: number;
    disk_free_gb: number;
    disk_total_gb: number;
    php_version: string;
    environment: string;
};

type Props = {
    roles: RoleRow[];
    user_counts?: {
        total: number;
        active: number;
        inactive: number;
    };
    telemetry?: TelemetryData;
};

export default function RoleIndex({ roles, user_counts, telemetry }: Props) {
    const totalRoles = roles.length;
    const assignedUsers = roles.reduce((sum, role) => sum + role.users_count, 0);

    return (
        <AuthenticatedLayout header="System Monitoring">
            <Head title="System Monitoring" />

            <div className="space-y-4">
                {/* Live System Diagnostics & Server Telemetry */}
                {telemetry && (
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-2.5">
                            <div className="flex items-center gap-2">
                                <span className="relative flex h-2.5 w-2.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                </span>
                                <div>
                                    <h2 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                                        Live System Diagnostics & Server Telemetry
                                    </h2>
                                    <p className="text-[10px] text-slate-500">Real-time health, server performance, and active runtime telemetry</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    System Healthy
                                </span>
                                <span className="text-[11px] font-mono text-slate-500">
                                    PHP {telemetry.php_version} ({telemetry.environment})
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100 p-3 gap-3 sm:gap-0">
                            {/* DB Latency */}
                            <div className="flex items-start gap-3 px-3 py-1">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <ellipse cx="12" cy="5" rx="9" ry="3" />
                                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                                    </svg>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Database Latency</p>
                                    <p className="text-xl font-black text-slate-900 leading-tight">
                                        {telemetry.db_latency_ms} <span className="text-xs font-semibold text-slate-500">ms</span>
                                    </p>
                                    <p className={`mt-0.5 text-[10px] font-medium ${telemetry.db_latency_ms < 50 ? 'text-emerald-700' : 'text-amber-600'}`}>
                                        {telemetry.db_latency_ms < 50 ? 'Optimal response time' : 'Acceptable latency'}
                                    </p>
                                </div>
                            </div>

                            {/* Memory RAM */}
                            <div className="flex items-start gap-3 px-3 py-1">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                                        <line x1="6" y1="6" x2="6.01" y2="6" />
                                        <line x1="6" y1="18" x2="6.01" y2="18" />
                                    </svg>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Memory Allocation</p>
                                    <p className="text-xl font-black text-slate-900 leading-tight">
                                        {telemetry.memory_usage_mb} <span className="text-xs font-semibold text-slate-500">MB</span>
                                    </p>
                                    <p className="mt-0.5 text-[10px] font-medium text-slate-500">PHP process heap</p>
                                </div>
                            </div>

                            {/* Storage Capacity */}
                            <div className="flex items-start gap-3 px-3 py-1">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <rect x="4" y="4" width="16" height="16" rx="2" />
                                        <rect x="9" y="9" width="6" height="6" />
                                        <line x1="9" y1="1" x2="9" y2="4" />
                                        <line x1="15" y1="1" x2="15" y2="4" />
                                        <line x1="9" y1="20" x2="9" y2="23" />
                                        <line x1="15" y1="20" x2="15" y2="23" />
                                    </svg>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Disk Storage</p>
                                    <p className="text-xl font-black text-slate-900 leading-tight">
                                        {telemetry.disk_free_gb} <span className="text-xs font-semibold text-slate-500">GB Free</span>
                                    </p>
                                    <p className="mt-0.5 text-[10px] font-medium text-slate-500">
                                        {(100 - telemetry.disk_used_percent).toFixed(1)}% available capacity
                                    </p>
                                </div>
                            </div>

                            {/* Runtime Environment */}
                            <div className="flex items-start gap-3 px-3 py-1">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <path d="M12 2L2 7l10 5 10-5-10-5z" />
                                        <path d="M2 17l10 5 10-5" />
                                        <path d="M2 12l10 5 10-5" />
                                    </svg>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Environment</p>
                                    <p className="text-xl font-black text-slate-900 leading-tight uppercase">
                                        {telemetry.environment}
                                    </p>
                                    <p className="mt-0.5 text-[10px] font-medium text-slate-500">
                                        PHP {telemetry.php_version} Runtime
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {/* Access Control & User Account Statistics */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">System Roles</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{totalRoles}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-blue-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-blue-700">Assigned Users</p>
                                <p className="text-lg font-extrabold text-blue-900 leading-tight">{user_counts?.total ?? assignedUsers}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Active Accounts</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{user_counts?.active ?? assignedUsers}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-amber-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Inactive Accounts</p>
                                <p className="text-lg font-extrabold text-slate-700 leading-tight">{user_counts?.inactive ?? 0}</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Access Control Registry */}
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="shrink-0 flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-3.5 py-2">
                        <div>
                            <h3 className="text-xs font-bold text-slate-800">Access Control Registry</h3>
                            <p className="text-[10px] text-slate-500">Live role definitions, descriptions, and user allocation</p>
                        </div>
                        <span className="text-[11px] text-slate-500 font-medium">Showing {roles.length} roles</span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Role</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Code</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Description</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Assigned Users</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {roles.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No role records available.
                                        </td>
                                    </tr>
                                )}
                                {roles.map((role) => (
                                    <tr key={role.id} className="hover:bg-slate-50/80 transition-colors">
                                        <td className="px-3.5 py-2.5 font-semibold text-slate-800 whitespace-nowrap">{role.name}</td>
                                        <td className="px-3.5 py-2.5 whitespace-nowrap">
                                            <span className="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700 font-mono">
                                                {role.code}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2.5 text-xs text-slate-600 max-w-md">
                                            {role.description ?? 'System role definition'}
                                        </td>
                                        <td className="px-3.5 py-2.5 whitespace-nowrap">
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 border border-blue-200/60">
                                                {role.users_count} {role.users_count === 1 ? 'user' : 'users'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}





