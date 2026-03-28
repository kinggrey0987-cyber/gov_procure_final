import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

type OfficeRow = {
    id: number;
    name: string;
    code: string;
    users_count: number;
    is_active: boolean;
};

type Props = {
    offices: {
        data: OfficeRow[];
    };
};

export default function OfficeIndex({ offices }: Props) {
    const page = usePage().props as {
        errors?: {
            delete?: string;
        };
    };

    const deleteError = page.errors?.delete;

    const deleteOffice = (officeId: number, officeName: string) => {
        if (!window.confirm(`Delete office ${officeName}? This action cannot be undone.`)) {
            return;
        }

        router.delete(route('admin-tech.offices.destroy', officeId), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.delete) {
                    window.alert(errors.delete);
                }
            },
        });
    };

    return (
        <AuthenticatedLayout header="Office Management">
            <Head title="Office Management" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <h3 className="text-3xl font-semibold text-slate-800">Office Management</h3>
                        <p className="mt-1 text-lg text-slate-500">Manage municipal offices and departmental access context</p>
                    </div>
                    <span className="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-slate-100 px-4 text-sm font-semibold text-slate-500">
                        Add Office (Disabled)
                    </span>
                </header>

                {deleteError && (
                    <div className="border-b border-rose-200 bg-rose-50 px-6 py-3 text-sm font-medium text-rose-700">
                        {deleteError}
                    </div>
                )}

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Office Name</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Code</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Active Users</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                                <th className="px-5 py-3 text-left text-sm font-semibold text-slate-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 bg-white">
                            {offices.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-10 text-center text-slate-500">
                                        No office records available.
                                    </td>
                                </tr>
                            )}
                            {offices.data.map((office) => (
                                <tr key={office.id} className="hover:bg-slate-50/80">
                                    <td className="px-5 py-4 text-base font-semibold text-slate-800">{office.name}</td>
                                    <td className="px-5 py-4">
                                        <span className="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                            {office.code}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-slate-700">{office.users_count}</td>
                                    <td className="px-5 py-4">
                                        <span className={`inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold ${office.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'}`}>
                                            {office.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route('admin-tech.offices.edit', office.id)}
                                                className="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => deleteOffice(office.id, office.name)}
                                                className="inline-flex h-8 items-center rounded-lg border border-rose-300 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
