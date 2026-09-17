import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type CategoryRow = {
    item_category_id: number;
    category_name: string;
    standard_delivery_days: number;
    description?: string | null;
    status: string;
    items_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    categories: {
        data: CategoryRow[];
        links: PaginationLink[];
    };
    filters: {
        q?: string;
        status?: string;
    };
    canManage: boolean;
};

export default function ItemCategoryIndex({ categories, filters, canManage }: Props) {
    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['categories', 'filters', 'canManage'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const debouncedQ = useDebouncedValue(q, 450);

    useEffect(() => {
        setQ(filters.q ?? '');
        setStatus(filters.status ?? '');
    }, [filters.q, filters.status]);

    const activeFilters = useMemo(
        () => ({
            q: debouncedQ,
            status,
        }),
        [debouncedQ, status],
    );

    useEffect(() => {
        router.get(route('inventory.item-categories.index'), activeFilters, { preserveState: true, replace: true });
    }, [activeFilters]);

    const toggleStatus = (categoryId: number) => {
        router.patch(route('inventory.item-categories.toggle-status', categoryId));
    };

    return (
        <AuthenticatedLayout
            header="Item Categories"
            headerActions={
                canManage ? (
                    <Link
                        href={route('inventory.item-categories.create')}
                        className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                        </svg>
                        Add Category
                    </Link>
                ) : undefined
            }
        >
            <Head title="Item Categories" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
                {/* Compact Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Search category name..."
                            className="h-8 flex-1 min-w-[200px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        />
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <Link
                            href={route('inventory.item-categories.index')}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                        >
                            Reset
                        </Link>
                        <button
                            type="button"
                            onClick={() => void refreshNow()}
                            disabled={isRefreshing}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition disabled:opacity-60"
                        >
                            {isRefreshing ? 'Refreshing...' : 'Refresh List'}
                        </button>
                    </div>
                </div>

                {/* High-Density Categories Table with Sticky Headers and Internal Scroll */}
                <div className="flex-1 min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs flex flex-col">
                    <div className="flex-1 overflow-y-auto min-h-0">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Category Name</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Standard Delivery Days</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Description</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {categories.data.map((category) => (
                                    <tr key={category.item_category_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-medium text-slate-900">{category.category_name}</td>
                                        <td className="px-3.5 py-2 text-slate-600">{category.standard_delivery_days} days</td>
                                        <td className="px-3.5 py-2 text-slate-500 max-w-xs truncate">{category.description || '-'}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${category.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                                {category.status === 'active' ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2 text-right">
                                            <div className="flex justify-end gap-2 text-xs">
                                                <Link href={route('inventory.item-categories.show', category.item_category_id)} className="font-semibold text-red-600 hover:text-red-800">
                                                    View
                                                </Link>
                                                {canManage ? (
                                                    <>
                                                        <Link href={route('inventory.item-categories.edit', category.item_category_id)} className="font-semibold text-slate-600 hover:text-slate-900">
                                                            Edit
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => toggleStatus(category.item_category_id)}
                                                            className="font-semibold text-amber-600 hover:text-amber-800"
                                                        >
                                                            {category.status === 'active' ? 'Deactivate' : 'Activate'}
                                                        </button>
                                                    </>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {categories.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No item categories found. Add your first category to start managing the catalog.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="shrink-0 border-t border-slate-200 bg-slate-50/70 px-3 py-1.5">
                        <PaginationNav links={categories.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





