import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function ViewerDashboard() {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold text-gray-800">Viewer Dashboard</h2>}
        >
            <Head title="Viewer Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        Transparency and read-only monitoring views will be shown here.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
