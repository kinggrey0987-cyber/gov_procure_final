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
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Item Categories</h2>
                    {canManage ? (
                        <Link href={route('inventory.item-categories.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Add Category
                        </Link>
                    ) : null}
                </div>
            }
        >
            <Head title="Item Categories" />

            <div className="space-y-4 py-8">
                <div className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-3">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Search category"
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <Link href={route('inventory.item-categories.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </Link>
                </div>
                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={() => void refreshNow()}
                        disabled={isRefreshing}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    >
                        {isRefreshing ? 'Refreshing...' : 'Refresh List'}
                    </button>
                </div>

                <div className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category Name</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Standard Delivery Days</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Description</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {categories.data.map((category) => (
                                <tr key={category.item_category_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 text-slate-800">{category.category_name}</td>
                                    <td className="px-4 py-3 text-slate-700">{category.standard_delivery_days}</td>
                                    <td className="px-4 py-3 text-slate-600">{category.description || '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${category.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                            {category.status === 'active' ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link href={route('inventory.item-categories.show', category.item_category_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                                View
                                            </Link>
                                            {canManage ? (
                                                <>
                                                    <Link href={route('inventory.item-categories.edit', category.item_category_id)} className="text-sm font-semibold text-slate-700 hover:text-slate-900">
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleStatus(category.item_category_id)}
                                                        className="text-sm font-semibold text-amber-700 hover:text-amber-900"
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
                                    <td colSpan={5} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No item categories yet. Add your first category to start managing the catalog.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div>
                    <PaginationNav links={categories.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





