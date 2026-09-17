import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type ItemRow = {
    item_id: number;
    item_name: string;
    description?: string | null;
    default_unit?: string | null;
    pieces_per_box?: number | null;
    sub_unit?: string | null;
    status: string;
    category?: {
        item_category_id: number;
        category_name: string;
    };
};

type CategoryOption = {
    item_category_id: number;
    category_name: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    items: {
        data: ItemRow[];
        links: PaginationLink[];
    };
    categories: CategoryOption[];
    filters: {
        q?: string;
        status?: string;
        category_id?: string | number;
    };
    canManage: boolean;
};

type PageProps = Props & {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

export default function ItemCatalogIndex({ items, categories, filters, canManage }: Props) {
    const { flash } = usePage().props as unknown as PageProps;
    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['items', 'categories', 'filters', 'canManage'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [categoryId, setCategoryId] = useState(String(filters.category_id ?? ''));
    const debouncedQ = useDebouncedValue(q, 450);

    useEffect(() => {
        setQ(filters.q ?? '');
        setStatus(filters.status ?? '');
        setCategoryId(String(filters.category_id ?? ''));
    }, [filters.category_id, filters.q, filters.status]);

    const activeFilters = useMemo(
        () => ({
            q: debouncedQ,
            status,
            category_id: categoryId,
        }),
        [categoryId, debouncedQ, status],
    );

    useEffect(() => {
        router.get(route('inventory.items.index'), activeFilters, {
            preserveState: true,
            replace: true,
        });
    }, [activeFilters]);

    const toggleStatus = (itemId: number) => {
        router.patch(route('inventory.items.toggle-status', itemId));
    };

    return (
        <AuthenticatedLayout
            header="Item Catalog"
            headerActions={
                canManage ? (
                    <Link
                        href={route('inventory.items.create')}
                        className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 shadow-sm transition"
                    >
                        <svg className="mr-1.5 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                        </svg>
                        Add Item
                    </Link>
                ) : undefined
            }
        >
            <Head title="Item Catalog" />

            <div className="space-y-3">
                {flash?.success ? (
                    <div className="shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                ) : null}

                {flash?.error ? (
                    <div className="shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700">
                        {flash.error}
                    </div>
                ) : null}

                {/* Compact Filter Bar */}
                <div className="shrink-0 rounded-xl border border-slate-200 bg-white p-2 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Search item name or code..."
                            className="h-8 flex-1 min-w-[180px] rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        />
                        <select
                            value={categoryId}
                            onChange={(event) => setCategoryId(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.item_category_id} value={category.item_category_id}>
                                    {category.category_name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 pl-2.5 pr-8 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <Link
                            href={route('inventory.items.index')}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                        >
                            Reset
                        </Link>
                        <button
                            type="button"
                            onClick={() => void refreshNow()}
                            disabled={isRefreshing}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs disabled:opacity-60"
                        >
                            {isRefreshing ? 'Refreshing...' : 'Refresh List'}
                        </button>
                    </div>
                </div>

                {/* Snug Catalog Table with Pagination Attached Directly Below Records */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Item Name</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Unit</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <tr key={item.item_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-medium text-slate-900">{item.item_name}</td>
                                        <td className="px-3.5 py-2 text-slate-600">{item.category?.category_name || '-'}</td>
                                        <td className="px-3.5 py-2 text-slate-700">
                                            <span className="capitalize">{item.default_unit || '-'}</span>
                                            {item.pieces_per_box && item.pieces_per_box > 1 ? (
                                                <span className="ml-1.5 text-[11px] text-slate-500">
                                                    ({item.pieces_per_box} {item.sub_unit || 'piece'}s/{item.default_unit || 'box'})
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-3.5 py-2">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${item.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                                {item.status === 'active' ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2 text-right">
                                            <div className="flex justify-end gap-2 text-xs">
                                                <Link href={route('inventory.items.show', item.item_id)} className="font-semibold text-red-600 hover:text-red-800">
                                                    View
                                                </Link>
                                                {canManage ? (
                                                    <>
                                                        <Link href={route('inventory.items.edit', item.item_id)} className="font-semibold text-slate-600 hover:text-slate-900">
                                                            Edit
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => toggleStatus(item.item_id)}
                                                            className="font-semibold text-amber-600 hover:text-amber-800"
                                                        >
                                                            {item.status === 'active' ? 'Deactivate' : 'Activate'}
                                                        </button>
                                                    </>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No catalog items found. Add an item once a category exists.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Showing <span className="font-semibold text-slate-700">{items.data.length}</span> catalog items
                        </p>
                        <PaginationNav links={items.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





