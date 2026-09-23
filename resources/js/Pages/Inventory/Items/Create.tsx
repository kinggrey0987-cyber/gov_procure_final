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

type CloneItem = {
    item_id: number;
    item_name: string;
    item_category_id: number;
    default_unit: string;
    pieces_per_box: number;
    sub_unit: string;
    description: string;
    specification: string;
};

type Props = {
    categories: CategoryOption[];
    units: UnitOption[];
    cloneItem?: CloneItem | null;
};

export default function CreateItem({ categories, units, cloneItem }: Props) {
    const isCloning = Boolean(cloneItem);

    const { data, setData, post, processing, errors } = useForm({
        item_name: cloneItem ? `${cloneItem.item_name} - ` : '',
        item_category_id: cloneItem ? String(cloneItem.item_category_id) : (categories[0]?.item_category_id ? String(categories[0].item_category_id) : ''),
        default_unit: cloneItem ? cloneItem.default_unit : (units[0]?.name ?? 'box'),
        pieces_per_box: cloneItem ? String(cloneItem.pieces_per_box) : '1',
        sub_unit: cloneItem ? cloneItem.sub_unit : 'piece',
        description: cloneItem ? cloneItem.description : '',
        status: 'active',
        specification: cloneItem ? cloneItem.specification : '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('inventory.items.store'));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">{isCloning ? 'Add Item Variant SKU' : 'Create Catalog Item'}</h2>}
        >
            <Head title={isCloning ? 'Add Item Variant SKU' : 'Create Catalog Item'} />

            <div className="py-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    {isCloning ? (
                        <div className="mb-6 rounded-lg border border-indigo-200 bg-indigo-50/80 p-4 text-sm text-indigo-900 flex items-start gap-3">
                            <span className="text-xl">📦</span>
                            <div>
                                <p className="font-semibold">Creating a Variant SKU from: <span className="underline">{cloneItem?.item_name}</span></p>
                                <p className="mt-0.5 text-xs text-indigo-700">
                                    Category, packaging units, and descriptions have been pre-filled. Adjust the variant name and technical specification below. Its warehouse inventory lane will be automatically created with 0 initial stock upon saving.
                                </p>
                            </div>
                        </div>
                    ) : null}

                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Item Name <span className="text-rose-600">*</span>
                            </label>
                            <input
                                value={data.item_name}
                                onChange={(event) => setData('item_name', event.target.value)}
                                placeholder="e.g. Utility Cutter (Heavy Duty) or Bond Paper, A4 (80 GSM)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                required
                            />
                            <p className="mt-1 text-xs text-slate-500">Include the physical variant, size, or color in the name so requesting offices can easily distinguish it in PPMP and Supply Requests.</p>
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
                                    <option key={unit.item_unit_id} value={unit.name}>
                                        {unit.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.default_unit} className="mt-1" />
                        </div>

                        {/* Dual-Unit Packaging Hierarchy Configuration */}
                        <div className="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4 space-y-3">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-indigo-900">
                                    Pieces per Packaging Unit (Conversion Ratio)
                                </label>
                                <p className="text-[11px] text-slate-500 mt-0.5">
                                    How many individual pieces/sub-units are inside 1 {data.default_unit || 'box'}? (Set to 1 if unit cannot be broken down)
                                </p>
                            </div>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={data.pieces_per_box}
                                onChange={(event) => setData('pieces_per_box', event.target.value)}
                                placeholder="e.g. 5 (for 5 reams/box) or 50 (for 50 pens/box)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white"
                                required
                            />
                            <InputError message={errors.pieces_per_box} className="mt-1" />
                        </div>

                        <div className="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4 space-y-3">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-indigo-900">
                                    Sub-Unit Name (Inner Smallest Issue Unit)
                                </label>
                                <p className="text-[11px] text-slate-500 mt-0.5">
                                    The unit offices consume individually (e.g. piece, ream, roll, pair, bottle).
                                </p>
                            </div>
                            <input
                                type="text"
                                value={data.sub_unit}
                                onChange={(event) => setData('sub_unit', event.target.value)}
                                placeholder="e.g. ream, piece, roll, bottle"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white"
                                required
                            />
                            <InputError message={errors.sub_unit} className="mt-1" />
                        </div>

                        {/* Packaging Ratio Visual Summary */}
                        <div className="md:col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-600">
                            <strong>Packaging Specification Preview:</strong> 1 {data.default_unit || 'box'} contains {data.pieces_per_box || '1'} {data.sub_unit || 'piece'}(s)
                            <span className="ml-1 text-blue-600">({data.default_unit || 'box'} [{data.pieces_per_box || '1'} {data.sub_unit || 'piece'}s/{data.default_unit || 'box'}])</span>
                        </div>

                        {/* Standard Item Specification (Single Approved Technical Sheet) */}
                        <div className="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-2">
                            <label className="text-sm font-semibold text-slate-800">
                                Standard Technical Specification <span className="text-rose-600">*</span>
                            </label>
                            <p className="text-xs text-slate-500">
                                Official GSO/PhilGEPS technical compliance specifications for this discrete item (dimensions, packaging details, certifications, grammage).
                            </p>
                            <input
                                type="text"
                                value={data.specification}
                                onChange={(e) => setData('specification', e.target.value)}
                                placeholder="e.g. Heavy-duty retractable cutter with auto-lock carbon steel blade (Box of 12 pcs)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                required
                            />
                            <p className="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1 leading-snug">
                                ℹ️ <strong>Warehouse Stock Tracking:</strong> Each catalog item represents a distinct physical product with its own warehouse stock tracking. An inventory record with 0 initial stock is automatically initialized upon creation.
                            </p>
                            <InputError message={errors.specification} className="mt-1" />
                        </div>

                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                            <textarea
                                rows={3}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                placeholder="General item usage context or storage guidelines..."
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <InputError message={errors.description} className="mt-1" />
                        </div>

                        <div className="md:col-span-2 flex items-center justify-end gap-3 pt-2">
                            <Link href={route('inventory.items.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : (isCloning ? 'Create Variant SKU' : 'Create Catalog Item')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
