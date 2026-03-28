import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type ClaimRow = {
    claims_id: number;
    claim_code: string;
    status: string;
};

type Props = {
    claims: {
        data: ClaimRow[];
    };
};

export default function ClaimIndex({ claims }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Claims</h2>}>
            <Head title="Claims" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('inventory.claims.create')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            File Claim
                        </Link>
                    </div>
                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Claim Code</th>
                                    <th className="px-4 py-2 text-left">Status</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {claims.data.map((row) => (
                                    <tr key={row.claims_id}>
                                        <td className="px-4 py-2">{row.claim_code}</td>
                                        <td className="px-4 py-2">{row.status}</td>
                                        <td className="px-4 py-2">
                                            <Link
                                                href={route('inventory.claims.show', row.claims_id)}
                                                className="rounded bg-indigo-600 px-3 py-1 text-xs text-white"
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
