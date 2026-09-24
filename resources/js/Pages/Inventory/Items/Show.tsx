import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

type Item = {
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
        standard_delivery_days: number;
    };
    specifications?: {
        item_specification_id: number;
        specification: string;
    }[];
};

type Props = {
    item: Item;
    canManage: boolean;
};

type PageProps = Props & {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

export default function ShowItem({ item, canManage }: Props) {
    const { flash } = usePage().props as unknown as PageProps;

    const toggleStatus = () => {
        router.patch(route('inventory.items.toggle-status', item.item_id), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Catalog Item Details</h2>}
        >
            <Head title="Catalog Item Details" />

            <div className="space-y-4 py-8">
                {flash?.success ? (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                ) : null}

                {flash?.error ? (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                        {flash.error}
                    </div>
                ) : null}

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <dl className="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Item Name</dt>
                            <dd className="mt-1 text-sm font-medium text-slate-900">{item.item_name}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                            <dd className="mt-1 text-sm text-slate-900">{item.status}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</dt>
                            <dd className="mt-1 text-sm text-slate-900">{item.category?.category_name || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Packaging Specification</dt>
                            <dd className="mt-1 text-sm text-slate-900">
                                <span className="font-medium capitalize">{item.default_unit || '-'}</span>
                                {item.pieces_per_box ? (
                                    <span className="ml-2 inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                        {item.pieces_per_box} {item.sub_unit || 'piece'}s / {item.default_unit || 'box'}
                                    </span>
                                ) : null}
                            </dd>
                        </div>
                        <div className="md:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Standard Specifications / Variants</dt>
                            <dd className="mt-2">
                                {item.specifications && item.specifications.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {item.specifications.map((spec) => (
                                            <span
                                                key={spec.item_specification_id}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800 shadow-2xs"
                                            >
                                                <span className="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                                {spec.specification}
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <span className="text-sm text-slate-500 italic">No specific variants recorded (Standard / General supply).</span>
                                )}
                            </dd>
                        </div>
                        <div className="md:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                            <dd className="mt-1 text-sm text-slate-900">{item.description || '-'}</dd>
                        </div>
                    </dl>
                </div>

                <div className="flex items-center gap-3">
                    {canManage ? (
                        <>
                            <Link href={route('inventory.items.edit', item.item_id)} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                Edit Item
                            </Link>
                            <Link
                                href={route('inventory.items.create', { clone_item_id: item.item_id })}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition shadow-2xs"
                            >
                                <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Variant
                            </Link>
                            <button
                                type="button"
                                onClick={toggleStatus}
                                className="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50"
                            >
                                {item.status === 'active' ? 'Deactivate Item' : 'Activate Item'}
                            </button>
                        </>
                    ) : null}
                    <Link href={route('inventory.items.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Back to List
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





