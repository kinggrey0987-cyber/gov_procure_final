import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: { code: string; name: string } | null;
    office: { code: string; name: string } | null;
};

type Props = {
    users: {
        data: UserRow[];
    };
};

export default function UserIndex({ users }: Props) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">User Management</h2>}
        >
            <Head title="User Management" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Email</th>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Role</th>
                                    <th className="px-4 py-3 text-left font-semibold text-gray-700">Office</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-4 py-3">{user.name}</td>
                                        <td className="px-4 py-3">{user.email}</td>
                                        <td className="px-4 py-3">{user.role?.code ?? '-'}</td>
                                        <td className="px-4 py-3">{user.office?.code ?? '-'}</td>
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
