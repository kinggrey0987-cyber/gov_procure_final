import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationModal from '@/Components/ConfirmationModal';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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
            error?: string;
        };
        errors?: {
            delete?: string;
        };
    };

    const [formData, setFormData] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });
    const debouncedSearch = useDebouncedValue(formData.search, 400);

    const [selectedOffice, setSelectedOffice] = useState<OfficeRow | null>(null);
    const [isProcessing, setIsProcessing] = useState(false);

    const deleteError = page.errors?.delete;
    const successMessage = page.flash?.success;
    const flashErrorMessage = page.flash?.error;

    const handleConfirmDelete = () => {
        if (!selectedOffice) return;

        setIsProcessing(true);
        router.delete(route('admin-tech.offices.destroy', selectedOffice.id), {
            preserveScroll: true,
            onFinish: () => {
                setIsProcessing(false);
                setSelectedOffice(null);
            },
        });
    };

    const activeFilters = useMemo(() => ({
        search: debouncedSearch || undefined,
        status: formData.status || undefined,
    }), [debouncedSearch, formData.status]);

    const activeFiltersKey = useMemo(() => JSON.stringify(activeFilters), [activeFilters]);
    const currentFiltersKey = useMemo(() => JSON.stringify({
        search: filters.search || undefined,
        status: filters.status || undefined,
    }), [filters.search, filters.status]);

    useEffect(() => {
        setFormData({
            search: filters.search ?? '',
            status: filters.status ?? '',
        });
    }, [filters.search, filters.status]);

    useEffect(() => {
        if (activeFiltersKey === currentFiltersKey) {
            return;
        }

        router.get(route('admin-tech.offices.index'), activeFilters, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }, [activeFilters, activeFiltersKey, currentFiltersKey]);

    const handleClearFilters = () => {
        setFormData({ search: '', status: '' });
        router.get(route('admin-tech.offices.index'), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header="Office Management"
            headerActions={
                <Link
                    href={route('admin-tech.offices.create')}
                    className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3.5 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700"
                >
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                    Add Office
                </Link>
            }
        >
            <Head title="Office Management" />

            <div className="space-y-3">
                {/* Compact Filter Card */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    {successMessage && (
                        <div className="mb-2 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700">
                            <svg className="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
                            </svg>
                            <span>{successMessage}</span>
                        </div>
                    )}

                    {(deleteError || flashErrorMessage) && (
                        <div className="mb-2 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700">
                            <svg className="h-4 w-4 shrink-0 text-rose-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                            </svg>
                            <span>{deleteError || flashErrorMessage}</span>
                        </div>
                    )}

                    <div className="grid gap-2 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center">
                        <div>
                            <input
                                id="search-input"
                                type="text"
                                value={formData.search}
                                onChange={(e) => setFormData(prev => ({ ...prev, search: e.target.value }))}
                                placeholder="Search by name or code..."
                                className="h-8 w-full rounded-lg border border-slate-300 px-2.5 text-xs shadow-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                            />
                        </div>
                        <div>
                            <select
                                id="status-select"
                                value={formData.status}
                                onChange={(e) => setFormData(prev => ({ ...prev, status: e.target.value }))}
                                className="h-8 w-full rounded-lg border border-slate-300 pl-2.5 pr-8 py-1 text-xs shadow-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="h-8 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </section>

                {/* Offices Table Card */}
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-[720px] divide-y divide-slate-200 text-xs md:min-w-full">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_2px_rgba(0,0,0,0.05)]">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Office Name</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Code</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Active Users</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {offices.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No office records available.
                                        </td>
                                    </tr>
                                )}
                                {offices.data.map((office) => (
                                    <tr key={office.id} className="hover:bg-slate-50/80 transition-colors">
                                        <td className="px-3.5 py-2 font-semibold text-slate-800">{office.name}</td>
                                        <td className="px-3.5 py-2">
                                            <span className="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                {office.code}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2 text-slate-700 font-medium">{office.users_count}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ${office.is_active
                                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                                                    : 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-500/20'
                                                }`}>
                                                <span className={`h-1.5 w-1.5 rounded-full ${office.is_active ? 'bg-emerald-600' : 'bg-slate-400'}`} />
                                                {office.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2 text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Link
                                                    href={route('admin-tech.offices.edit', office.id)}
                                                    className="inline-flex h-7 items-center rounded border border-slate-300 px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                                >
                                                    Edit
                                                </Link>

                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedOffice(office)}
                                                    disabled={!office.can_delete_action}
                                                    className={`inline-flex h-7 items-center rounded border px-2.5 text-xs font-semibold transition ${office.can_hard_delete
                                                            ? 'border-rose-300 text-rose-700 hover:bg-rose-50'
                                                            : 'border-amber-300 text-amber-700 hover:bg-amber-50'
                                                        }`}
                                                    title={
                                                        office.can_delete_action
                                                            ? (office.can_hard_delete ? 'Delete this office' : 'Safely deactivate this office')
                                                            : office.delete_reason ?? 'Delete is currently unavailable.'
                                                    }
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

                    <div className="shrink-0 border-t border-slate-200 bg-white px-3.5 py-2 flex items-center justify-between text-xs">
                        <p className="text-slate-500">
                            Showing <span className="font-semibold">{offices.from ?? 0}</span> to <span className="font-semibold">{offices.to ?? 0}</span> of <span className="font-semibold">{offices.total}</span> offices &bull; Page <span className="font-semibold">{offices.current_page}</span> of <span className="font-semibold">{offices.last_page}</span>
                        </p>
                        <div className="flex items-center gap-1.5">
                            {offices.prev_page_url ? (
                                <Link
                                    href={offices.prev_page_url}
                                    className="inline-flex items-center gap-1 rounded border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Previous
                                </Link>
                            ) : (
                                <button
                                    disabled
                                    className="inline-flex items-center gap-1 rounded border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-400 cursor-not-allowed"
                                >
                                    Previous
                                </button>
                            )}
                            {offices.next_page_url ? (
                                <Link
                                    href={offices.next_page_url}
                                    className="inline-flex items-center gap-1 rounded border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Next
                                </Link>
                            ) : (
                                <button
                                    disabled
                                    className="inline-flex items-center gap-1 rounded border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-400 cursor-not-allowed"
                                >
                                    Next
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            </div>

            {selectedOffice && (
                <ConfirmationModal
                    show={Boolean(selectedOffice)}
                    onClose={() => !isProcessing && setSelectedOffice(null)}
                    processing={isProcessing}
                    variant={selectedOffice.can_hard_delete ? 'danger' : 'warning'}
                    title={selectedOffice.can_hard_delete ? 'Delete Office' : 'Deactivate Office'}
                    confirmText={selectedOffice.can_hard_delete ? 'Delete Permanently' : 'Deactivate Office'}
                    message={
                        selectedOffice.can_hard_delete ? (
                            <p>
                                Are you sure you want to permanently delete the office{' '}
                                <strong className="text-slate-800">{selectedOffice.name}</strong> ({selectedOffice.code})?
                                This action cannot be undone.
                            </p>
                        ) : (
                            <p>
                                The office <strong className="text-slate-800">{selectedOffice.name}</strong> has linked records.
                                Deactivating it will safely preserve all existing procurement logs, PPMPs, and user histories
                                while marking it inactive.
                            </p>
                        )
                    }
                    onConfirm={handleConfirmDelete}
                />
            )}
        </AuthenticatedLayout>
    );
}
