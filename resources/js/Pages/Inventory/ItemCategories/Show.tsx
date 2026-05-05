import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Category = {
    item_category_id: number;
    category_name: string;
    standard_delivery_days: number;
    description?: string | null;
    status: string;
    items_count: number;
};

type Props = {
    category: Category;
    canManage: boolean;
};

export default function ShowItemCategory({ category, canManage }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Item Category Details</h2>}
        >
            <Head title="Item Category Details" />

            <div className="space-y-4 py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <dl className="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Category Name</dt>
                            <dd className="mt-1 text-sm text-slate-900">{category.category_name}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                            <dd className="mt-1 text-sm text-slate-900">{category.status}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Standard Delivery Days</dt>
                            <dd className="mt-1 text-sm text-slate-900">{category.standard_delivery_days}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Items Count</dt>
                            <dd className="mt-1 text-sm text-slate-900">{category.items_count}</dd>
                        </div>
                        <div className="md:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                            <dd className="mt-1 text-sm text-slate-900">{category.description || '-'}</dd>
                        </div>
                    </dl>
                </div>

                <div className="flex items-center gap-3">
                    {canManage ? (
                        <Link href={route('inventory.item-categories.edit', category.item_category_id)} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Edit Category
                        </Link>
                    ) : null}
                    <Link href={route('inventory.item-categories.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Back to List
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





