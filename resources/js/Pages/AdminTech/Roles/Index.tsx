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

            <div className="space-y-6">
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-3xl font-semibold text-slate-800">System Health Monitor</h3>
                        <p className="mt-1 text-lg text-slate-500">Technical access-control health snapshot based on live role assignments</p>
                    </header>
                    <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-4">
                        <article className="rounded-xl border border-slate-200 p-4">
                            <p className="text-lg text-slate-500">Total Roles</p>
                            <p className="mt-2 text-4xl font-bold text-slate-800">{totalRoles}</p>
                        </article>
                        <article className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <p className="text-lg text-emerald-700">Active Roles</p>
                            <p className="mt-2 text-4xl font-bold text-emerald-700">{activeRoles}</p>
                        </article>
                        <article className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <p className="text-lg text-blue-700">Assigned Users</p>
                            <p className="mt-2 text-4xl font-bold text-blue-700">{assignedUsers}</p>
                        </article>
                        <article className="rounded-xl border border-slate-200 p-4">
                            <p className="text-lg text-slate-500">Inactive Roles</p>
                            <p className="mt-2 text-4xl font-bold text-slate-700">{inactiveRoles}</p>
                        </article>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-3xl font-semibold text-slate-800">Access Control Registry</h3>
                    </header>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Role</th>
                                    <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Code</th>
                                    <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Assigned Users</th>
                                    <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {roles.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-10 text-center text-slate-500">
                                            No role records available.
                                        </td>
                                    </tr>
                                )}
                                {roles.map((role) => (
                                    <tr key={role.id} className="hover:bg-slate-50/80">
                                        <td className="px-5 py-4 text-base font-semibold text-slate-800">{role.name}</td>
                                        <td className="px-5 py-4">
                                            <span className="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                {role.code}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-slate-700">{role.users_count}</td>
                                        <td className="px-5 py-4">
                                            <span className={`inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold ${role.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
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





