import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    alert: {
        alerts_id: number;
        alert_type: string;
        severity: string;
        status: string;
        alert_message: string;
    };
};

export default function ExecutiveAlertShow({ alert }: Props) {
    const form = useForm({ status: '', remarks: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Alert Detail</h2>}>
            <Head title="Alert Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <p><strong>ID:</strong> {alert.alerts_id}</p>
                        <p><strong>Type:</strong> {alert.alert_type}</p>
                        <p><strong>Severity:</strong> {alert.severity}</p>
                        <p><strong>Status:</strong> {alert.status}</p>
                        <p><strong>Message:</strong> {alert.alert_message}</p>
                    </div>

                    <form
                        className="space-y-2 rounded border bg-white p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('executive.alerts.update-status', alert.alerts_id));
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
