import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    actual_delivery_date?: string | null;
    expected_arrival_date?: string | null;
    remarks?: string | null;
};

type DeliveryForm = {
    actual_delivery_date: string;
    expected_arrival_date: string;
    remarks: string;
};

type Props = {
    delivery: Delivery;
};

export default function DeliveryEdit({ delivery }: Props) {
    const { data, setData, put, processing, errors } = useForm<DeliveryForm>({
        actual_delivery_date: delivery.actual_delivery_date || '',
        expected_arrival_date: delivery.expected_arrival_date || '',
        remarks: delivery.remarks || '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('inventory.deliveries.update', delivery.delivery_id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Delivery {delivery.delivery_code}</h2>}>
            <Head title={`Edit ${delivery.delivery_code}`} />

            <div className="py-8">
                <div className="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-5">
                        <FormField label="Actual Delivery Date" error={errors.actual_delivery_date}>
                            <input
                                type="date"
                                value={data.actual_delivery_date}
                                onChange={(e) => setData('actual_delivery_date', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Expected Arrival Date" error={errors.expected_arrival_date}>
                            <input
                                type="date"
                                value={data.expected_arrival_date}
                                onChange={(e) => setData('expected_arrival_date', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            />
                        </FormField>

                        <FormField label="Remarks" error={errors.remarks}>
                            <textarea
                                rows={4}
                                value={data.remarks}
                                onChange={(e) => setData('remarks', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                            />
                        </FormField>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                            <Link href={route('inventory.deliveries.show', delivery.delivery_id)} className="rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function FormField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-semibold text-slate-700">{label}</label>
            {children}
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}





