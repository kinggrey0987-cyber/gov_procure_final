import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: { code: string; name: string } | null;
    office: { code: string; name: string } | null;
    can_delete: boolean;
    delete_reason: string | null;
};

type Role = {
    id: number;
    code: string;
    name: string;
};

type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        from: number;
        to: number;
        total: number;
    };
    filters: {
        search: string | null;
        role_id: number | null;
    };
    roles: Role[];
};

export default function UserIndex({ users, filters, roles }: Props) {
    const page = usePage().props as {
        auth: {
            user: {
                id: number;
            };
        };
        flash?: {
            success?: string;
        };
        errors?: {
            delete?: string;
        };
    };

    const [formData, setFormData] = useState({
        search: filters.search ?? '',
        role_id: filters.role_id ? String(filters.role_id) : '',
    });

    const deleteError = page.errors?.delete;
    const successMessage = page.flash?.success;

    const deleteUser = (userId: number, userName: string) => {
        if (!window.confirm(`Delete user account for ${userName}? This action cannot be undone.`)) {
            return;
        }

        router.delete(route('admin-tech.users.destroy', userId), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.delete) {
                    window.alert(errors.delete);
                }
            },
        });
    };

    const handleFilterSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        router.get(route('admin-tech.users.index'), {
            search: formData.search || undefined,
            role_id: formData.role_id || undefined,
        }, {
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setFormData({ search: '', role_id: '' });
        router.get(route('admin-tech.users.index'), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout header="User Management">
            <Head title="User Management" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <h3 className="text-3xl font-semibold text-slate-800">User Management</h3>
                        <p className="mt-1 text-lg text-slate-500">Manage internal system users and their technical access roles</p>
                    </div>
                    <Link
                        href={route('admin-tech.users.create')}
                        className="inline-flex h-10 items-center justify-center rounded-xl border border-red-300 bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Add User
                    </Link>
                </header>

                {successMessage && (
                    <div className="border-b border-emerald-200 bg-emerald-50 px-6 py-3 text-sm font-medium text-emerald-700">
                        {successMessage}
                    </div>
                )}

                {deleteError && (
                    <div className="border-b border-rose-200 bg-rose-50 px-6 py-3 text-sm font-medium text-rose-700">
                        {deleteError}
                    </div>
                )}

                <form onSubmit={handleFilterSubmit} className="border-b border-slate-200 bg-slate-50 px-6 py-4">
                    <div className="flex gap-3">
                        <div className="flex-1">
                            <label htmlFor="search-input" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Search
                            </label>
                            <input
                                id="search-input"
                                type="text"
                                value={formData.search}
                                onChange={(e) => setFormData(prev => ({ ...prev, search: e.target.value }))}
                                placeholder="Search by name or email..."
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                        <div className="w-48">
                            <label htmlFor="role-select" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Role
                            </label>
                            <select
                                id="role-select"
                                value={formData.role_id}
                                onChange={(e) => setFormData(prev => ({ ...prev, role_id: e.target.value }))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="">All Roles</option>
                                {roles.map(role => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end gap-2">
                            <button
                                type="submit"
                                className="rounded-lg border border-blue-300 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Name</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Email</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Role</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Office</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 bg-white">
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-10 text-center text-slate-500">
                                        No user records available.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-slate-50/80">
                                    <td className="px-5 py-4 text-base font-semibold text-slate-800">{user.name}</td>
                                    <td className="px-5 py-4 text-slate-600">{user.email}</td>
                                    <td className="px-5 py-4">
                                        <span className="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                            {user.role?.name ?? 'Unassigned'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                            {user.office?.code ?? '-'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold ${user.role ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                            {user.role ? 'Assigned' : 'Pending Role'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route('admin-tech.users.edit', user.id)}
                                                className="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => deleteUser(user.id, user.name)}
                                                disabled={!user.can_delete}
                                                className="inline-flex h-8 items-center rounded-lg border border-rose-300 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                                                title={user.can_delete ? 'Delete this account' : user.delete_reason ?? 'Delete is currently unavailable.'}
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <div className="mb-4 flex items-center justify-between">
                        <p className="text-sm text-slate-600">
                            Showing <span className="font-semibold">{users.from}</span> to <span className="font-semibold">{users.to}</span> of <span className="font-semibold">{users.total}</span> user(s)
                        </p>
                        <p className="text-sm text-slate-600">
                            Page <span className="font-semibold">{users.current_page}</span> of <span className="font-semibold">{users.last_page}</span>
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {users.prev_page_url ? (
                            <Link
                                href={users.prev_page_url}
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
                        {users.next_page_url ? (
                            <Link
                                href={users.next_page_url}
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
            </section>
        </AuthenticatedLayout>
    );
}
