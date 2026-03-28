import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function OfficerDashboard() {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold text-gray-800">Officer Dashboard</h2>}
        >
            <Head title="Officer Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        Operational procurement and inventory tasks will be shown here.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
