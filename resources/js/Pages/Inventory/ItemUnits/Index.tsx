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
        <AuthenticatedLayout header="Item Units">
            <Head title="Item Units" />

            <div className="space-y-3">
                {/* Compact Unit Creation Bar */}
                <section className="shrink-0 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="text-xs font-bold text-slate-900">Allowed Default Units</h3>
                        <p className="text-[11px] text-slate-500">
                            Controlled measurement units for item catalog, PPMP planning, requests, and delivery.
                        </p>
                    </div>

                    {canManage ? (
                        <form onSubmit={submit} className="flex items-center gap-2">
                            <div className="relative">
                                <input
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    placeholder="New unit (e.g., bundle, box)..."
                                    className="h-8 w-56 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                />
                                {form.errors.name ? <InputError message={form.errors.name} className="absolute -bottom-4 left-0 text-[10px]" /> : null}
                            </div>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex h-8 items-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700 transition shadow-2xs disabled:opacity-60"
                            >
                                Add Unit
                            </button>
                        </form>
                    ) : null}
                </section>

                {/* Snug Units Table */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-xs">
                            <thead className="bg-slate-50 shadow-xs">
                                <tr>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Unit</th>
                                    <th className="px-3.5 py-2 text-left font-semibold text-slate-700">Status</th>
                                    <th className="px-3.5 py-2 text-right font-semibold text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {units.map((unit) => (
                                    <tr key={unit.item_unit_id} className="hover:bg-slate-50/60">
                                        <td className="px-3.5 py-2 font-semibold text-slate-800 capitalize">{unit.name}</td>
                                        <td className="px-3.5 py-2">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${unit.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                                {unit.status === 'active' ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-3.5 py-2 text-right">
                                            {canManage ? (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleStatus(unit.item_unit_id)}
                                                    className="font-semibold text-xs text-amber-600 hover:text-amber-800"
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
                                        <td colSpan={3} className="px-4 py-8 text-center text-xs text-slate-500">
                                            No units are configured yet.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/70 px-3.5 py-2">
                        <p className="text-xs text-slate-500 font-medium">
                            Total allowed units: <span className="font-semibold text-slate-700">{units.length}</span>
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

