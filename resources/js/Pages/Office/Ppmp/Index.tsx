import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type PpmpRow = {
    ppmp_id: number;
    fiscal_year: number;
    status: string;
    date_prepared: string;
};

type Props = {
    ppmps: { data: PpmpRow[] };
};

export default function PpmpIndex({ ppmps }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">PPMP List</h2>}>
            <Head title="PPMP List" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('office.ppmp.create')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Create PPMP
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left">ID</th>
                                    <th className="px-4 py-3 text-left">Fiscal Year</th>
                                    <th className="px-4 py-3 text-left">Status</th>
                                    <th className="px-4 py-3 text-left">Date Prepared</th>
                                    <th className="px-4 py-3 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ppmps.data.map((ppmp) => (
                                    <tr key={ppmp.ppmp_id} className="border-t">
                                        <td className="px-4 py-3">{ppmp.ppmp_id}</td>
                                        <td className="px-4 py-3">{ppmp.fiscal_year}</td>
                                        <td className="px-4 py-3">{ppmp.status}</td>
                                        <td className="px-4 py-3">{ppmp.date_prepared}</td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={route('office.ppmp.show', ppmp.ppmp_id)}
                                                className="text-indigo-600 hover:underline"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
