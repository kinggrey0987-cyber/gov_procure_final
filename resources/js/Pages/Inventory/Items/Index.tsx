import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useSmartRefresh from '@/hooks/useSmartRefresh';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type ItemRow = {
    item_id: number;
    item_name: string;
    description?: string | null;
    default_unit?: string | null;
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

export default function ItemCatalogIndex({ items, categories, filters, canManage }: Props) {
    const { isRefreshing, refreshNow } = useSmartRefresh({
        only: ['items', 'categories', 'filters', 'canManage'],
        refreshOnFocus: false,
        refreshOnMount: false,
    });

    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [categoryId, setCategoryId] = useState(String(filters.category_id ?? ''));

    useEffect(() => {
        setQ(filters.q ?? '');
        setStatus(filters.status ?? '');
        setCategoryId(String(filters.category_id ?? ''));
    }, [filters.category_id, filters.q, filters.status]);

    const submitFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('inventory.items.index'), { q, status, category_id: categoryId }, { preserveState: true, replace: true });
    };

    const toggleStatus = (itemId: number) => {
        router.patch(route('inventory.items.toggle-status', itemId));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Item Catalog</h2>
                    {canManage ? (
                        <Link href={route('inventory.items.create')} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Add Item
                        </Link>
                    ) : null}
                </div>
            }
        >
            <Head title="Item Catalog" />

            <div className="space-y-4 py-8">
                <form onSubmit={submitFilters} className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Search item"
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <select value={categoryId} onChange={(event) => setCategoryId(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.item_category_id} value={category.item_category_id}>
                                {category.category_name}
                            </option>
                        ))}
                    </select>
                    <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Apply
                    </button>
                    <Link href={route('inventory.items.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </Link>
                </form>
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

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Item Name</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.map((item) => (
                                <tr key={item.item_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 text-slate-800">{item.item_name}</td>
                                    <td className="px-4 py-3 text-slate-700">{item.category?.category_name || '-'}</td>
                                    <td className="px-4 py-3 text-slate-700">{item.default_unit || '-'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${item.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                            {item.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link href={route('inventory.items.show', item.item_id)} className="text-sm font-semibold text-red-700 hover:text-red-900">
                                                View
                                            </Link>
                                            {canManage ? (
                                                <>
                                                    <Link href={route('inventory.items.edit', item.item_id)} className="text-sm font-semibold text-slate-700 hover:text-slate-900">
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleStatus(item.item_id)}
                                                        className="text-sm font-semibold text-amber-700 hover:text-amber-900"
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
                                    <td colSpan={5} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No catalog items found. Add an item once a category exists.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-2">
                    {items.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            className={`rounded-md px-3 py-2 text-sm ${link.active ? 'bg-red-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
