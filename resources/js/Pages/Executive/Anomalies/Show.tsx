import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    anomaly: {
        anomaly_id: number;
        anomaly_type: string;
        severity: string;
        status: string;
        detected_value?: string | null;
        expected_value?: string | null;
        threshold_value?: string | null;
        z_score?: string | null;
        detection_method?: string | null;
        remarks?: string | null;
    };
};

export default function ExecutiveAnomalyShow({ anomaly }: Props) {
    const form = useForm({ status: '', remarks: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Anomaly Detail</h2>}>
            <Head title="Anomaly Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <p><strong>ID:</strong> {anomaly.anomaly_id}</p>
                        <p><strong>Type:</strong> {formatAnomalyType(anomaly.anomaly_type)}</p>
                        <p><strong>Severity:</strong> {anomaly.severity}</p>
                        <p><strong>Status:</strong> {anomaly.status}</p>
                        <p><strong>Detected Value:</strong> {anomaly.detected_value ?? '-'}</p>
                        <p><strong>Expected Value:</strong> {anomaly.expected_value ?? '-'}</p>
                        <p><strong>Threshold:</strong> {anomaly.threshold_value ?? '-'}</p>
                        <p><strong>Z-Score:</strong> {anomaly.z_score ?? '-'}</p>
                        <p><strong>Method:</strong> {anomaly.detection_method ?? '-'}</p>
                        <p><strong>Remarks:</strong> {anomaly.remarks ?? '-'}</p>
                        {anomaly.anomaly_type === 'PRICE_Z_SCORE' ? (
                            <p><strong>Source Note:</strong> Price anomalies are derived from purchase order item pricing, with office used only for executive grouping context.</p>
                        ) : null}
                    </div>

                    <form
                        className="space-y-2 rounded border bg-white p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('executive.anomalies.update-status', anomaly.anomaly_id));
                        }}
                    >
                        <input className="w-full rounded border px-3 py-2" placeholder="IN_REVIEW / RESOLVED / DISMISSED" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Remarks" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">Update Status</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function formatAnomalyType(value: string): string {
    if (value === 'PRICE_Z_SCORE') {
        return 'Procurement Price Anomaly';
    }

    return value;
}
