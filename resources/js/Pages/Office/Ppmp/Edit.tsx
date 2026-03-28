import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Props = {
    ppmp: {
        ppmp_id: number;
        fiscal_year: number;
        date_prepared: string;
        remarks: string | null;
    };
};

export default function PpmpEdit({ ppmp }: Props) {
    const form = useForm({
        fiscal_year: ppmp.fiscal_year,
        date_prepared: ppmp.date_prepared,
        remarks: ppmp.remarks ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('office.ppmp.update', ppmp.ppmp_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit PPMP</h2>}>
            <Head title="Edit PPMP" />

            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-6">
                        <input
                            type="number"
                            value={form.data.fiscal_year}
                            onChange={(e) => form.setData('fiscal_year', Number(e.target.value))}
                            className="w-full rounded border px-3 py-2"
                        />
                        <input
                            type="date"
                            value={form.data.date_prepared}
                            onChange={(e) => form.setData('date_prepared', e.target.value)}
                            className="w-full rounded border px-3 py-2"
                        />
                        <textarea
                            value={form.data.remarks}
                            onChange={(e) => form.setData('remarks', e.target.value)}
                            className="w-full rounded border px-3 py-2"
                        />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-white">
                            Update
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
