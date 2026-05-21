import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type UnitRow = {
    item_unit_id: number;
    name: string;
    status: string;
    created_at?: string | null;
};

type Props = {
    units: UnitRow[];
    canManage: boolean;
};

export default function ItemUnitIndex({ units, canManage }: Props) {
    const form = useForm({ name: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(route('inventory.item-units.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    const toggleStatus = (unitId: number) => {
        router.patch(route('inventory.item-units.toggle-status', unitId), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Item Units</h2>}>
            <Head title="Item Units" />

            <div className="space-y-5 py-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="max-w-2xl">
                        <h3 className="text-lg font-semibold text-slate-900">Allowed Default Units</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Maintain the controlled unit list used by item catalog, PPMP planning, requests, delivery, and reports.
                        </p>
                    </div>

                    {canManage ? (
                        <form onSubmit={submit} className="mt-5 grid gap-3 md:grid-cols-[1fr_auto]">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">New Unit</label>
                                <input
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    placeholder="Example: bundle"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                                <InputError message={form.errors.name} className="mt-1" />
                            </div>
                            <div className="flex items-end">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60"
                                >
                                    Add Unit
                                </button>
                            </div>
                        </form>
                    ) : null}
                </section>

                <section className="responsive-table-wrap rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Unit</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {units.map((unit) => (
                                <tr key={unit.item_unit_id} className="hover:bg-slate-50/60">
                                    <td className="px-4 py-3 font-semibold text-slate-800">{unit.name}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${unit.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                            {unit.status === 'active' ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManage ? (
                                            <button
                                                type="button"
                                                onClick={() => toggleStatus(unit.item_unit_id)}
                                                className="text-sm font-semibold text-amber-700 hover:text-amber-900"
                                            >
                                                {unit.status === 'active' ? 'Deactivate' : 'Activate'}
                                            </button>
                                        ) : (
                                            <span className="text-slate-400">-</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {units.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="px-4 py-10 text-center text-sm text-slate-500">
                                        No units are configured yet.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

