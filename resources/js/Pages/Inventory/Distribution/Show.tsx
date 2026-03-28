import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    record: {
        distribution_record_id: number;
        distribution_code: string;
        status: string;
    };
};

export default function DistributionShow({ record }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Distribution Detail</h2>}>
            <Head title="Distribution Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="space-y-2 rounded border bg-white p-6 text-sm">
                        <p><strong>ID:</strong> {record.distribution_record_id}</p>
                        <p><strong>Code:</strong> {record.distribution_code}</p>
                        <p><strong>Status:</strong> {record.status}</p>
                        <div className="pt-2">
                            <Link
                                href={route('inventory.distribution-records.items.manage', record.distribution_record_id)}
                                className="rounded bg-indigo-600 px-4 py-2 text-xs text-white"
                            >
                                Manage Items
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
