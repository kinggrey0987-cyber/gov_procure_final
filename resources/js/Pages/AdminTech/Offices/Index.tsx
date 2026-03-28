import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

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
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Office Management</h2>}
        >
            <Head title="Office Management" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Users</th>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {offices.data.map((office) => (
                                    <tr key={office.id}>
                                        <td className="px-4 py-3">{office.code}</td>
                                        <td className="px-4 py-3">{office.name}</td>
                                        <td className="px-4 py-3">{office.users_count}</td>
                                        <td className="px-4 py-3">{office.is_active ? 'Active' : 'Inactive'}</td>
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
