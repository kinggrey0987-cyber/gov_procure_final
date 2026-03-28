import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type CategoryOption = {
    item_category_id: number;
    category_name: string;
};

type Props = {
    categories: CategoryOption[];
};

export default function CreateItem({ categories }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        item_name: '',
        item_category_id: categories[0]?.item_category_id ? String(categories[0].item_category_id) : '',
        default_unit: '',
        description: '',
        status: 'active',
    });

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
                            <label className="mb-1 block text-sm font-medium text-slate-700">Default Unit</label>
                            <input
                                value={data.default_unit}
                                onChange={(event) => setData('default_unit', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <InputError message={errors.default_unit} className="mt-1" />
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
