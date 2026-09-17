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

type Props = {
    roles: RoleRow[];
};

export default function RoleIndex({ roles }: Props) {
    const totalRoles = roles.length;
    const activeRoles = roles.filter((role) => role.is_active).length;
    const inactiveRoles = totalRoles - activeRoles;
    const assignedUsers = roles.reduce((sum, role) => sum + role.users_count, 0);

    return (
        <AuthenticatedLayout header="System Monitoring">
            <Head title="System Monitoring" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                {/* High-Density System Health Telemetry Cockpit */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-400" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total Roles</p>
                                <p className="text-lg font-extrabold text-slate-900 leading-tight">{totalRoles}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Active Roles</p>
                                <p className="text-lg font-extrabold text-emerald-900 leading-tight">{activeRoles}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-blue-500" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-blue-700">Assigned Users</p>
                                <p className="text-lg font-extrabold text-blue-900 leading-tight">{assignedUsers}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5 px-3 py-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-full bg-slate-300" />
                            <div className="min-w-0">
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Inactive Roles</p>
                                <p className="text-lg font-extrabold text-slate-700 leading-tight">{inactiveRoles}</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Access Control Registry with Sticky Header & Internal Scroll */}
                <section className="flex-1 min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col">
                    <div className="shrink-0 flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-3.5 py-2">
                        <div>
                            <h3 className="text-xs font-bold text-slate-800">Access Control Registry</h3>
                            <p className="text-[10px] text-slate-500">Live role definitions, access permissions, and user allocation</p>
                        </div>
                        <span className="text-[11px] text-slate-500 font-medium">Showing {roles.length} roles</span>
                    </div>

                    <div className="flex-1 min-h-0 overflow-y-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs sm:text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Role</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Code</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Assigned Users</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
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
                                        <td className="px-3.5 py-2.5 font-semibold text-slate-800">{role.name}</td>
                                        <td className="px-3.5 py-2.5">
                                            <span className="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700 font-mono">
                                                {role.code}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2.5 text-slate-700 font-medium">{role.users_count}</td>
                                        <td className="px-3.5 py-2.5">
                                            <span className={`inline-flex rounded px-2 py-0.5 text-xs font-semibold ${role.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                                                {role.is_active ? 'Active' : 'Inactive'}
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





