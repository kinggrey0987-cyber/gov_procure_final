import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function AdminDashboard() {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold text-gray-800">Admin Dashboard</h2>}
        >
            <Head title="Admin Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        System-wide governance and monitoring overview will be shown here.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
