import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: { code: string; name: string } | null;
    office: { code: string; name: string } | null;
    can_delete: boolean;
    delete_reason: string | null;
};

type Props = {
    users: {
        data: UserRow[];
    };
};

export default function UserIndex({ users }: Props) {
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
            </section>
        </AuthenticatedLayout>
    );
}
