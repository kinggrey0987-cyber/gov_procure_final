import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PaginationNav from '@/Components/PaginationNav';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type InventoryRow = {
    inventory_id: number;
    item_id: number;
    reserved_quantity: string;
    available_stock: string;
    stock_on_hand: string;
    released_quantity: string;
    low_stock_threshold: string;
    item?: {
        item_name?: string;
        default_unit?: string;
        pieces_per_box?: number;
        sub_unit?: string;
        category?: {
            category_name?: string;
        };
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    inventories: {
        data: InventoryRow[];
        links: PaginationLink[];
    };
    categories: Array<{
        item_category_id: number;
        category_name: string;
    }>;
    filters: {
        q?: string;
        category_id?: number | null;
        stock_status?: string;
    };
};

export default function InventoryIndex({ inventories, categories, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [categoryId, setCategoryId] = useState(String(filters.category_id ?? ''));
    const [stockStatus, setStockStatus] = useState(filters.stock_status ?? '');
    const debouncedQ = useDebouncedValue(q, 450);
    const hasMountedRef = useRef(false);

    useEffect(() => {
        setQ(filters.q ?? '');
        setCategoryId(String(filters.category_id ?? ''));
        setStockStatus(filters.stock_status ?? '');
    }, [filters.category_id, filters.q, filters.stock_status]);

    const activeFilters = useMemo(
        () => ({
            q: debouncedQ,
            category_id: categoryId,
            stock_status: stockStatus,
        }),
        [debouncedQ, categoryId, stockStatus],
    );

    useEffect(() => {
        if (!hasMountedRef.current) {
            hasMountedRef.current = true;

            return;
        }

        router.get(route('inventory.inventory.index'), activeFilters, {
            preserveState: true,
            replace: true,
            only: ['inventories', 'filters', 'categories'],
        });
    }, [activeFilters]);

    return (
        <AuthenticatedLayout
            header="Inventory Monitoring"
            headerActions={
                <Link
                    href={route('inventory.inventory.index')}
                    className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs transition"
                >
                    Refresh
                </Link>
            }
        >
            <Head title="Inventory" />

            <div className="space-y-2 lg:h-[calc(100vh-8.5rem)] flex flex-col justify-between overflow-y-auto lg:overflow-hidden">
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
                            className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.item_category_id} value={category.item_category_id}>
                                    {category.category_name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={stockStatus}
                            onChange={(event) => setStockStatus(event.target.value)}
                            className="h-8 rounded-lg border border-slate-300 px-2.5 text-xs text-slate-700 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        >
                            <option value="">All stock statuses</option>
                            <option value="HEALTHY">Healthy Stock</option>
                            <option value="AT_REORDER_POINT">At Reorder Point</option>
                            <option value="LOW">Low Stock</option>
                            <option value="CRITICAL">Critical Stock</option>
                        </select>
                        <Link
                            href={route('inventory.inventory.index')}
                            className="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                        >
                            Reset
                        </Link>
                    </div>
                </div>

                {/* High-Density Stock Ledger Table with Sticky Headers and Internal Scroll */}
                <div className="flex-1 min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs flex flex-col">
                    <div className="flex-1 overflow-y-auto min-h-0">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="sticky top-0 z-10 bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Item</th>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-3 py-2 text-right font-semibold text-slate-700">Available</th>
                                    <th className="px-3 py-2 text-right font-semibold text-slate-700">On Hand</th>
                                    <th className="px-3 py-2 text-right font-semibold text-slate-700">Reserved</th>
                                    <th className="px-3 py-2 text-right font-semibold text-slate-700">Released</th>
                                    <th className="px-3 py-2 text-right font-semibold text-slate-700">Reorder Point</th>
                                    <th className="px-3 py-2 text-left font-semibold text-slate-700">Stock Signal</th>
                                    <th className="px-3 py-2 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {inventories.data.map((row) => {
                                    const available = Number(row.available_stock);
                                    const onHand = Number(row.stock_on_hand);
                                    const reserved = Number(row.reserved_quantity);
                                    const released = Number(row.released_quantity);
                                    const threshold = Number(row.low_stock_threshold);
                                    const deficit = Math.max(threshold - available, 0);
                                    const isCritical = available <= 0;
                                    const isLow = available < threshold && available > 0;
                                    const isAtReorder = available === threshold;
                                    const piecesPerBox = row.item?.pieces_per_box ?? 1;
                                    const unit = row.item?.default_unit || 'box';
                                    const subUnit = row.item?.sub_unit || 'piece';

                                    const signalClass = isCritical
                                        ? 'bg-rose-100 text-rose-700'
                                        : isLow
                                        ? 'bg-amber-100 text-amber-700'
                                        : isAtReorder
                                        ? 'bg-blue-100 text-blue-700'
                                        : 'bg-emerald-100 text-emerald-700';

                                    const signalLabel = isCritical
                                        ? 'Critical Stock'
                                        : isLow
                                        ? 'Low Stock'
                                        : isAtReorder
                                        ? 'At Reorder Point'
                                        : 'Healthy Stock';

                                    return (
                                        <tr key={row.inventory_id} className="hover:bg-slate-50/60">
                                            <td className="px-3 py-2 font-medium text-slate-900">{row.item?.item_name || `Item #${row.item_id}`}</td>
                                            <td className="px-3 py-2 text-slate-600">{row.item?.category?.category_name || '-'}</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-900">
                                                <span>{available.toFixed(2)} {unit}s</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-[11px] text-slate-500">
                                                        ({(available * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-right text-slate-700">
                                                <span>{onHand.toFixed(2)} {unit}s</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-[11px] text-slate-500">
                                                        ({(onHand * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-right text-slate-700">
                                                <span>{reserved.toFixed(2)} {unit}s</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-[11px] text-slate-500">
                                                        ({(reserved * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-right text-slate-700">
                                                <span>{released.toFixed(2)} {unit}s</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-[11px] text-slate-500">
                                                        ({(released * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-right text-slate-700">
                                                <span>{threshold.toFixed(2)} {unit}s</span>
                                                {piecesPerBox > 1 ? (
                                                    <span className="block text-[11px] text-slate-500">
                                                        ({(threshold * piecesPerBox).toLocaleString()} {subUnit}s)
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-slate-700">
                                                <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${signalClass}`}>
                                                    {signalLabel}
                                                </span>
                                                {deficit > 0 ? (
                                                    <p className="mt-0.5 text-[11px] text-rose-600 font-medium">
                                                        Deficit: {deficit.toFixed(2)} {unit}s
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <Link href={route('inventory.inventory.show', row.inventory_id)} className="font-semibold text-xs text-red-600 hover:text-red-800">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {inventories.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No inventory records available.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="shrink-0 border-t border-slate-200 bg-slate-50/70 px-3 py-1.5">
                        <PaginationNav links={inventories.links} preserveState={false} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}



