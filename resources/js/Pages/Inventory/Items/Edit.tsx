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
    sub_unit?: string | null;
    status: string;
    specifications?: string[];
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
        default_unit: item.default_unit ?? 'box',
        pieces_per_box: String(item.pieces_per_box ?? 1),
        sub_unit: item.sub_unit ?? 'piece',
        description: item.description ?? '',
        status: item.status,
        specification: item.specifications && item.specifications.length > 0 ? item.specifications[0] : '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('inventory.items.update', item.item_id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Catalog Item</h2>
                    <Link
                        href={route('inventory.items.create', { clone_item_id: item.item_id })}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition shadow-2xs"
                    >
                        <svg className="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Variant
                    </Link>
                </div>
            }
        >
            <Head title={`Edit ${item.item_name}`} />

            <div className="py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Item Name <span className="text-rose-600">*</span>
                            </label>
                            <input
                                value={data.item_name}
                                onChange={(event) => setData('item_name', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                            <InputError message={errors.item_name} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Category <span className="text-rose-600">*</span></label>
                            <select
                                value={data.item_category_id}
                                onChange={(event) => setData('item_category_id', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
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
                            <label className="mb-1 block text-sm font-medium text-slate-700">Packaging Unit (Outer Container / Unit of Issue) <span className="text-rose-600">*</span></label>
                            <select
                                value={data.default_unit}
                                onChange={(event) => setData('default_unit', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            >
                                <option value="">Select unit</option>
                                {units.map((unit) => (
                                    <option key={unit.item_unit_id} value={unit.name} disabled={unit.status !== 'active'}>
                                        {unit.name}{unit.status !== 'active' ? ' (inactive)' : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.default_unit} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Count / Pieces per Package <span className="text-rose-600">*</span></label>
                            <input
                                type="number"
                                min="1"
                                value={data.pieces_per_box}
                                onChange={(event) => setData('pieces_per_box', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="e.g. 5, 50, 100, 4, 12, 1"
                                required
                            />
                            <InputError message={errors.pieces_per_box} className="mt-1" />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Content Sub-Unit (Inner Item Type) <span className="text-rose-600">*</span></label>
                            <select
                                value={data.sub_unit}
                                onChange={(event) => setData('sub_unit', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            >
                                <option value="piece">piece / pcs</option>
                                <option value="ream">ream</option>
                                <option value="gallon">gallon</option>
                                <option value="pair">pair</option>
                                <option value="book">book</option>
                                <option value="roll">roll</option>
                                <option value="pack">pack</option>
                                <option value="cartridge">cartridge</option>
                                <option value="bottle">bottle</option>
                                <option value="meter">meter</option>
                                <option value="liter">liter</option>
                                <option value="kilogram">kilogram</option>
                                <option value="set">set</option>
                                <option value="unit">unit</option>
                            </select>
                            <InputError message={errors.sub_unit} className="mt-1" />
                        </div>

                        <div className="md:col-span-2 rounded-lg border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-800">
                            <strong>Packaging Specification Preview:</strong> 1 {data.default_unit || 'box'} contains {data.pieces_per_box || '1'} {data.sub_unit || 'piece'}(s)
                            <span className="ml-1 text-blue-600">({data.default_unit || 'box'} [{data.pieces_per_box || '1'} {data.sub_unit || 'piece'}s/{data.default_unit || 'box'}])</span>
                        </div>

                        <div className="md:col-span-2">
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

                        {/* Standard Item Specification (Single Approved Technical Sheet) */}
                        <div className="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-2">
                            <div>
                                <label className="text-sm font-semibold text-slate-800">
                                    Standard Technical Specification <span className="text-rose-600">*</span>
                                </label>
                                <p className="text-xs text-slate-500">
                                    Official technical compliance specification sheet for this discrete item.
                                </p>
                            </div>
                            <input
                                type="text"
                                value={data.specification}
                                onChange={(e) => setData('specification', e.target.value)}
                                placeholder="e.g. Heavy-duty retractable cutter with auto-lock blade (Box of 12 pcs)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                required
                            />
                            <p className="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded px-2.5 py-1.5 leading-snug">
                                ℹ️ <strong>Item Variants:</strong> If this item comes in different sizes, colors, or specifications, click <strong>"Add Variant"</strong> in the top header to automatically create an item with the same name and a distinct specification.
                            </p>
                            <InputError message={errors.specification} className="mt-1" />
                        </div>

                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                            <textarea
                                rows={3}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <InputError message={errors.description} className="mt-1" />
                        </div>

                        <div className="md:col-span-2 flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60"
                            >
                                {processing ? 'Saving…' : 'Update Item'}
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
