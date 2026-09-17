import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationModal from '@/Components/ConfirmationModal';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type UserRow = {
    id: number;
    name: string;
    email: string;
    emp_id: string | null;
    is_active: boolean;
    role: { code: string; name: string } | null;
    office: { code: string; name: string } | null;
    can_toggle_active: boolean;
    toggle_active_reason: string | null;
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
        status: string | null;
    };
    roles: Role[];
};

type ModalState = {
    type: 'toggle' | 'delete';
    user: UserRow;
} | null;

export default function UserIndex({ users, filters, roles }: Props) {
    const page = usePage().props as {
        auth: {
            user: {
                id: number;
            };
        };
        flash?: {
            success?: string;
            error?: string;
        };
        errors?: {
            delete?: string;
            toggle_active?: string;
        };
    };

    const [formData, setFormData] = useState({
        search: filters.search ?? '',
        role_id: filters.role_id ? String(filters.role_id) : '',
        status: filters.status ?? '',
    });
    const debouncedSearch = useDebouncedValue(formData.search, 400);

    const [modalState, setModalState] = useState<ModalState>(null);
    const [isProcessing, setIsProcessing] = useState(false);

    const deleteError = page.errors?.delete;
    const toggleError = page.errors?.toggle_active;
    const successMessage = page.flash?.success;
    const flashErrorMessage = page.flash?.error;

    const handleConfirmAction = () => {
        if (!modalState) return;

        setIsProcessing(true);

        if (modalState.type === 'toggle') {
            router.patch(
                route('admin-tech.users.toggle-status', modalState.user.id),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => {
                        setIsProcessing(false);
                        setModalState(null);
                    },
                }
            );
        } else if (modalState.type === 'delete') {
            router.delete(
                route('admin-tech.users.destroy', modalState.user.id),
                {
                    preserveScroll: true,
                    onFinish: () => {
                        setIsProcessing(false);
                        setModalState(null);
                    },
                }
            );
        }
    };

    const activeFilters = useMemo(() => ({
        search: debouncedSearch || undefined,
        role_id: formData.role_id || undefined,
        status: formData.status || undefined,
    }), [debouncedSearch, formData.role_id, formData.status]);

    const activeFiltersKey = useMemo(() => JSON.stringify(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => JSON.stringify({
        search: filters.search || undefined,
        role_id: filters.role_id ? String(filters.role_id) : undefined,
        status: filters.status || undefined,
    }), [filters.role_id, filters.search, filters.status]);

    useEffect(() => {
        setFormData({
            search: filters.search ?? '',
            role_id: filters.role_id ? String(filters.role_id) : '',
            status: filters.status ?? '',
        });
    }, [filters.role_id, filters.search, filters.status]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('admin-tech.users.index'), activeFilters, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

    const handleClearFilters = () => {
        setFormData({ search: '', role_id: '', status: '' });
        router.get(route('admin-tech.users.index'), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header="User Management"
            headerActions={
                <Link
                    href={route('admin-tech.users.create')}
                    className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3.5 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700"
                >
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                    Add User
                </Link>
            }
        >
            <Head title="User Management" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

                {successMessage && (
                    <div className="flex items-center gap-2 border-b border-emerald-200 bg-emerald-50 px-6 py-3 text-sm font-medium text-emerald-700">
                        <svg className="h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
                        </svg>
                        <span>{successMessage}</span>
                    </div>
                )}

                {(deleteError || toggleError || flashErrorMessage) && (
                    <div className="flex items-center gap-2 border-b border-rose-200 bg-rose-50 px-6 py-3 text-sm font-medium text-rose-700">
                        <svg className="h-5 w-5 shrink-0 text-rose-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                        </svg>
                        <span>{deleteError || toggleError || flashErrorMessage}</span>
                    </div>
                )}

                <div className="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_11rem_auto] md:items-end">
                        <div>
                            <label htmlFor="search-input" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Search
                            </label>
                            <input
                                id="search-input"
                                type="text"
                                value={formData.search}
                                onChange={(e) => setFormData(prev => ({ ...prev, search: e.target.value }))}
                                placeholder="Search by name, email, or EMP ID..."
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                        <div>
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
                        <div>
                            <label htmlFor="status-select" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Status
                            </label>
                            <select
                                id="status-select"
                                value={formData.status}
                                onChange={(e) => setFormData(prev => ({ ...prev, status: e.target.value }))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Deactivated</option>
                            </select>
                        </div>
                        <div className="grid gap-2 sm:flex sm:items-end">
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-[860px] divide-y divide-slate-200 text-sm md:min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Name & ID</th>
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
                                        No user records match your criteria.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((user) => (
                                <tr key={user.id} className={`hover:bg-slate-50/80 transition ${!user.is_active ? 'bg-slate-50/40 opacity-80' : ''}`}>
                                    <td className="px-5 py-4">
                                        <div className="font-semibold text-slate-800">{user.name}</div>
                                        {user.emp_id && (
                                            <span className="mt-0.5 inline-block text-xs font-mono text-slate-500">
                                                ID: {user.emp_id}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 break-all text-slate-600">{user.email}</td>
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
                                        {user.is_active ? (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-600" />
                                                Active
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-500/20">
                                                <span className="h-1.5 w-1.5 rounded-full bg-slate-400" />
                                                Deactivated
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={route('admin-tech.users.edit', user.id)}
                                                className="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                            >
                                                Edit
                                            </Link>

                                            {user.is_active ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setModalState({ type: 'toggle', user })}
                                                    disabled={!user.can_toggle_active}
                                                    className="inline-flex h-8 items-center rounded-lg border border-amber-300 bg-amber-50/50 px-3 text-xs font-semibold text-amber-700 hover:bg-amber-100/70 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-transparent disabled:text-slate-400 transition"
                                                    title={user.can_toggle_active ? 'Deactivate this user account' : user.toggle_active_reason ?? 'Deactivation unavailable.'}
                                                >
                                                    Deactivate
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => setModalState({ type: 'toggle', user })}
                                                    disabled={!user.can_toggle_active}
                                                    className="inline-flex h-8 items-center rounded-lg border border-emerald-300 bg-emerald-50/50 px-3 text-xs font-semibold text-emerald-700 hover:bg-emerald-100/70 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-transparent disabled:text-slate-400 transition"
                                                    title={user.can_toggle_active ? 'Reactivate this user account' : user.toggle_active_reason ?? 'Reactivation unavailable.'}
                                                >
                                                    Activate
                                                </button>
                                            )}

                                            <button
                                                type="button"
                                                onClick={() => setModalState({ type: 'delete', user })}
                                                disabled={!user.can_delete}
                                                className="inline-flex h-8 items-center rounded-lg border border-rose-300 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400 transition"
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

                <div className="border-t border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
                    <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-slate-600">
                            Showing <span className="font-semibold">{users.from ?? 0}</span> to <span className="font-semibold">{users.to ?? 0}</span> of <span className="font-semibold">{users.total}</span> user(s)
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

            {modalState && (
                <ConfirmationModal
                    show={Boolean(modalState)}
                    onClose={() => !isProcessing && setModalState(null)}
                    processing={isProcessing}
                    variant={
                        modalState.type === 'delete'
                            ? 'danger'
                            : modalState.user.is_active
                            ? 'warning'
                            : 'success'
                    }
                    title={
                        modalState.type === 'delete'
                            ? 'Delete User Account'
                            : modalState.user.is_active
                            ? 'Deactivate User Account'
                            : 'Reactivate User Account'
                    }
                    confirmText={
                        modalState.type === 'delete'
                            ? 'Delete Permanently'
                            : modalState.user.is_active
                            ? 'Deactivate Account'
                            : 'Reactivate Account'
                    }
                    message={
                        modalState.type === 'delete' ? (
                            <p>
                                Are you sure you want to permanently delete the account for{' '}
                                <strong className="text-slate-800">{modalState.user.name}</strong> ({modalState.user.email})?
                                This action cannot be undone.
                            </p>
                        ) : modalState.user.is_active ? (
                            <p>
                                Are you sure you want to deactivate the account for{' '}
                                <strong className="text-slate-800">{modalState.user.name}</strong> ({modalState.user.email})?
                                This will immediately revoke login access, while preserving all historical audit logs,
                                approvals, and procurement records.
                            </p>
                        ) : (
                            <p>
                                Are you sure you want to reactivate the account for{' '}
                                <strong className="text-slate-800">{modalState.user.name}</strong> ({modalState.user.email})?
                                The user will be able to log in and access the system with their assigned role.
                            </p>
                        )
                    }
                    onConfirm={handleConfirmAction}
                />
            )}
        </AuthenticatedLayout>
    );
}
