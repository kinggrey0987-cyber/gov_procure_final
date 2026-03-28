import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    ppmp: {
        ppmp_id: number;
        fiscal_year: number;
        status: string;
        date_prepared: string;
        remarks: string | null;
    };
};

export default function PpmpShow({ ppmp }: Props) {
    const submitForm = useForm({ remarks: ppmp.remarks ?? '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">PPMP View</h2>}>
            <Head title="PPMP View" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border bg-white p-6">
                        <p>ID: {ppmp.ppmp_id}</p>
                        <p>Fiscal Year: {ppmp.fiscal_year}</p>
                        <p>Status: {ppmp.status}</p>
                        <p>Date Prepared: {ppmp.date_prepared}</p>
                        <p>Remarks: {ppmp.remarks ?? '-'}</p>
                    </div>

                    <div className="flex gap-3">
                        <Link href={route('office.ppmp.edit', ppmp.ppmp_id)} className="rounded bg-slate-700 px-4 py-2 text-white">
                            Edit
                        </Link>
                        <Link
                            href={route('office.ppmp.items.index', ppmp.ppmp_id)}
                            className="rounded bg-indigo-600 px-4 py-2 text-white"
                        >
                            Manage Items
                        </Link>
                        <button
                            onClick={() => submitForm.post(route('office.ppmp.submit', ppmp.ppmp_id))}
                            className="rounded bg-emerald-600 px-4 py-2 text-white"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
