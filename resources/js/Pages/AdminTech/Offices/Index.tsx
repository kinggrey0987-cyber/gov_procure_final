import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type OfficeRow = {
    id: number;
    name: string;
    code: string;
    users_count: number;
    is_active: boolean;
    can_hard_delete: boolean;
    can_delete_action: boolean;
    delete_reason: string | null;
};

type Props = {
    offices: {
        data: OfficeRow[];
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
        status: string | null;
    };
};

export default function OfficeIndex({ offices, filters }: Props) {
    const page = usePage().props as {
        flash?: {
            success?: string;
        };
        errors?: {
            delete?: string;
        };
    };

    const [formData, setFormData] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const deleteError = page.errors?.delete;
    const successMessage = page.flash?.success;

    const deleteOffice = (office: OfficeRow) => {
        if (!office.can_delete_action) {
            return;
        }

        const actionLabel = office.can_hard_delete ? 'delete' : 'deactivate';
        const confirmationMessage = office.can_hard_delete
            ? `Delete office ${office.name}? This action cannot be undone.`
            : `Office ${office.name} has linked records. Continue to safely deactivate it?`;

        if (!window.confirm(confirmationMessage)) {
            return;
        }

        router.delete(route('admin-tech.offices.destroy', office.id), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.delete) {
                    window.alert(errors.delete);
                }
            },
            onSuccess: () => {
                if (!office.can_hard_delete) {
                    window.alert(`Office ${office.name} has been ${actionLabel}d safely.`);
                }
            },
        });
    };

    const handleFilterSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        router.get(route('admin-tech.offices.index'), {
            search: formData.search || undefined,
            status: formData.status || undefined,
        }, {
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setFormData({ search: '', status: '' });
        router.get(route('admin-tech.offices.index'), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout header="Office Management">
            <Head title="Office Management" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <h3 className="text-3xl font-semibold text-slate-800">Office Management</h3>
                        <p className="mt-1 text-lg text-slate-500">Manage municipal offices and departmental access context</p>
                    </div>
                    <Link
                        href={route('admin-tech.offices.create')}
                        className="inline-flex h-10 items-center justify-center rounded-xl border border-red-300 bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Add Office
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
                                placeholder="Search by name or code..."
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                        <div className="w-48">
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
                                <option value="inactive">Inactive</option>
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
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Office Name</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Code</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Active Users</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 bg-white">
                            {offices.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-10 text-center text-slate-500">
                                        No office records available.
                                    </td>
                                </tr>
                            )}
                            {offices.data.map((office) => (
                                <tr key={office.id} className="hover:bg-slate-50/80">
                                    <td className="px-5 py-4 text-base font-semibold text-slate-800">{office.name}</td>
                                    <td className="px-5 py-4">
                                        <span className="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                            {office.code}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-slate-700">{office.users_count}</td>
                                    <td className="px-5 py-4">
                                        <span className={`inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold ${office.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                            {office.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route('admin-tech.offices.edit', office.id)}
                                                className="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => deleteOffice(office)}
                                                disabled={!office.can_delete_action}
                                                className="inline-flex h-8 items-center rounded-lg border border-rose-300 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                                                title={office.can_delete_action ? (office.can_hard_delete ? 'Delete this office' : 'Safely deactivate this office') : office.delete_reason ?? 'Delete is currently unavailable.'}
                                            >
                                                {office.can_hard_delete ? 'Delete' : 'Deactivate'}
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
                            Showing <span className="font-semibold">{offices.from}</span> to <span className="font-semibold">{offices.to}</span> of <span className="font-semibold">{offices.total}</span> office(s)
                        </p>
                        <p className="text-sm text-slate-600">
                            Page <span className="font-semibold">{offices.current_page}</span> of <span className="font-semibold">{offices.last_page}</span>
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {offices.prev_page_url ? (
                            <Link
                                href={offices.prev_page_url}
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
                        {offices.next_page_url ? (
                            <Link
                                href={offices.next_page_url}
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
