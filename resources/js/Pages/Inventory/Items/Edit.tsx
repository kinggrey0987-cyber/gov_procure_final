import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type CategoryOption = {
    item_category_id: number;
    category_name: string;
};

type UnitOption = {
    item_unit_id: number;
    name: string;
    status: string;
};

type Item = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    description?: string | null;
    default_unit?: string | null;
    pieces_per_box?: number | null;
    status: string;
};

type Props = {
    item: Item;
    categories: CategoryOption[];
    units: UnitOption[];
};

export default function EditItem({ item, categories, units }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        item_name: item.item_name,
        item_category_id: String(item.item_category_id),
        default_unit: item.default_unit ?? '',
        pieces_per_box: String(item.pieces_per_box ?? 1),
        description: item.description ?? '',
        status: item.status,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('inventory.items.update', item.item_id));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Catalog Item</h2>}
        >
            <Head title="Edit Catalog Item" />

            <div className="py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">Item Name</label>
                            <input
                                value={data.item_name}
                                onChange={(event) => setData('item_name', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <InputError message={errors.item_name} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Category</label>
                            <select
                                value={data.item_category_id}
                                onChange={(event) => setData('item_category_id', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Select category</option>
                                {categories.map((category) => (
                                    <option key={category.item_category_id} value={category.item_category_id}>
                                        {category.category_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.item_category_id} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Packaging / Default Unit</label>
                            <select
                                value={data.default_unit}
                                onChange={(event) => setData('default_unit', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Select unit</option>
                                {units.map((unit) => (
                                    <option key={unit.item_unit_id} value={unit.name} disabled={unit.status !== 'active'}>
                                        {unit.name}{unit.status !== 'active' ? ' (inactive)' : ''}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-slate-500">Missing a unit? Add it first in Item Units.</p>
                            <InputError message={errors.default_unit} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Pieces / Items per Box or Package</label>
                            <input
                                type="number"
                                min="1"
                                value={data.pieces_per_box}
                                onChange={(event) => setData('pieces_per_box', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="e.g. 50 pcs/box, 100 pcs/pack, 1 pc/unit"
                                required
                            />
                            <p className="mt-1 text-xs text-slate-500">Total individual pieces contained inside 1 unit/box (used for PPMP & requisition calculations).</p>
                            <InputError message={errors.pieces_per_box} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <select
                                value={data.status}
                                onChange={(event) => setData('status', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <InputError message={errors.status} className="mt-1" />
                        </div>

                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                            <textarea
                                rows={4}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <InputError message={errors.description} className="mt-1" />
                        </div>

                        <div className="md:col-span-2 flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60"
                            >
                                Update Item
                            </button>
                            <Link href={route('inventory.items.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





