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

type Props = {
    categories: CategoryOption[];
    units: UnitOption[];
};

export default function CreateItem({ categories, units }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        item_name: '',
        item_category_id: categories[0]?.item_category_id ? String(categories[0].item_category_id) : '',
        default_unit: units[0]?.name ?? 'box',
        pieces_per_box: '1',
        sub_unit: 'piece',
        description: '',
        status: 'active',
        specifications: [''],
    });

    const addSpecification = () => {
        setData('specifications', [...data.specifications, '']);
    };

    const updateSpecification = (index: number, value: string) => {
        const next = [...data.specifications];
        next[index] = value;
        setData('specifications', next);
    };

    const removeSpecification = (index: number) => {
        if (data.specifications.length <= 1) {
            setData('specifications', ['']);
            return;
        }
        setData('specifications', data.specifications.filter((_, i) => i !== index));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('inventory.items.store'));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Catalog Item</h2>}
        >
            <Head title="Create Catalog Item" />

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
                            <label className="mb-1 block text-sm font-medium text-slate-700">Packaging Unit (Outer Container / Unit of Issue)</label>
                            <select
                                value={data.default_unit}
                                onChange={(event) => setData('default_unit', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
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

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Count / Pieces per Package</label>
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
                            <label className="mb-1 block text-sm font-medium text-slate-700">Content Sub-Unit (Inner Item Type)</label>
                            <select
                                value={data.sub_unit}
                                onChange={(event) => setData('sub_unit', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
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
                            <div className="flex h-[38px] items-center">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Active (Default)
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-slate-500">New catalog items are always created as active. You can deactivate them later from the edit screen.</p>
                        </div>

                        {/* Standard Item Specifications */}
                        <div className="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <label className="text-sm font-semibold text-slate-800">Standard Item Specifications</label>
                                    <p className="text-xs text-slate-500">
                                        Define variants and specifications for this item (e.g. ink color, paper size, storage capacity).
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={addSpecification}
                                    className="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white hover:bg-red-700 transition shadow-xs"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Specification
                                </button>
                            </div>

                            <div className="space-y-2">
                                {data.specifications.map((spec, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        <span className="text-xs font-semibold text-slate-400 w-6 text-center">#{index + 1}</span>
                                        <input
                                            type="text"
                                            value={spec}
                                            onChange={(e) => updateSpecification(index, e.target.value)}
                                            placeholder="e.g. Black (0.5mm), A4 80gsm, 500ml Ethyl 70%"
                                            className="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm bg-white focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                        />
                                        {data.specifications.length > 1 ? (
                                            <button
                                                type="button"
                                                onClick={() => removeSpecification(index)}
                                                className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition"
                                                title="Remove specification"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
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
                                Save Item
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





