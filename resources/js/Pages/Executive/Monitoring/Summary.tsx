import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type OfficeBreakdown = {
    id: number;
    name: string;
    anomaly_count: number;
    alert_count: number;
};

type Props = {
    summary: {
        open_anomaly_count: number;
        in_review_anomaly_count: number;
        high_severity_anomaly_count: number;
        open_alert_count: number;
        in_review_alert_count: number;
        high_severity_alert_count: number;
        cross_office_anomaly_count: number;
        office_breakdown: OfficeBreakdown[];
    };
};

export default function ExecutiveMonitoringSummary({ summary }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Executive Monitoring</h2>}>
            <Head title="Executive Monitoring" />

            <div className="py-10">
                <div className="mx-auto grid max-w-6xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-4 sm:px-6 lg:px-8">
                    <Card label="Open Anomalies" value={summary.open_anomaly_count} />
                    <Card label="In-Review Anomalies" value={summary.in_review_anomaly_count} />
                    <Card label="High Severity Anomalies" value={summary.high_severity_anomaly_count} />
                    <Card label="Open Alerts" value={summary.open_alert_count} />
                    <Card label="In-Review Alerts" value={summary.in_review_alert_count} />
                    <Card label="High Severity Alerts" value={summary.high_severity_alert_count} />
                    <Card label="Offices With Anomalies" value={summary.cross_office_anomaly_count} />
                </div>

                <div className="mx-auto mt-6 flex max-w-6xl gap-3 px-4 sm:px-6 lg:px-8">
                    <Link href={route('executive.anomalies.index')} className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                        View Anomalies
                    </Link>
                    <Link href={route('executive.alerts.index')} className="rounded bg-emerald-600 px-4 py-2 text-sm text-white">
                        View Alerts
                    </Link>
                    <Link href={route('executive.disclosures.index')} className="rounded bg-sky-600 px-4 py-2 text-sm text-white">
                        Manage Disclosures
                    </Link>
                </div>

                <div className="mx-auto mt-6 max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded border bg-white">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">Office</th>
                                    <th className="px-4 py-2 text-left">Anomalies</th>
                                    <th className="px-4 py-2 text-left">Alerts</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {summary.office_breakdown.map((office) => (
                                    <tr key={office.id}>
                                        <td className="px-4 py-2">{office.name}</td>
                                        <td className="px-4 py-2">{office.anomaly_count}</td>
                                        <td className="px-4 py-2">{office.alert_count}</td>
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

function Card({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded border bg-white p-4">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}
