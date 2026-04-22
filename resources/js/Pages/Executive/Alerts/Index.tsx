import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type AlertRow = {
    alerts_id: number;
    alert_type: string;
    severity: string;
    status: string;
    detected_at: string;
};

type Props = {
    alerts: { data: AlertRow[] };
};

export default function ExecutiveAlertIndex({ alerts }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Alerts</h2>}>
            <Head title="Alerts" />
            <div className="py-10">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Type</th>
                                    <th className="px-4 py-2 text-left">Severity</th>
                                    <th className="px-4 py-2 text-left">Status</th>
                                    <th className="px-4 py-2 text-left">Detected</th>
                                    <th className="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {alerts.data.map((row) => (
                                    <tr key={row.alerts_id}>
                                        <td className="px-4 py-2">{row.alert_type}</td>
                                        <td className="px-4 py-2">{row.severity}</td>
                                        <td className="px-4 py-2">{row.status}</td>
                                        <td className="px-4 py-2">{row.detected_at}</td>
                                        <td className="px-4 py-2">
                                            <Link href={route('executive.alerts.show', row.alerts_id)} className="rounded bg-indigo-600 px-3 py-1 text-xs text-white">
                                                Open Executive Review
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
