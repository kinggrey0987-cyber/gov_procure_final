import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    record: {
        distribution_record_id: number;
        received_by?: number | null;
        release_date?: string | null;
        storage_location?: string | null;
        notes?: string | null;
    };
};

export default function DistributionEdit({ record }: Props) {
    const form = useForm({
        received_by: record.received_by ?? '',
        release_date: record.release_date ?? '',
        storage_location: record.storage_location ?? '',
        notes: record.notes ?? '',
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Edit Distribution</h2>}>
            <Head title="Edit Distribution" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form
                        className="space-y-3 rounded border bg-white p-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('inventory.distribution-records.update', record.distribution_record_id));
                        }}
                    >
                        <input
                            className="w-full rounded border px-3 py-2"
                            placeholder="Received By"
                            value={form.data.received_by}
                            onChange={(e) => form.setData('received_by', Number(e.target.value))}
                        />
                        <input
                            className="w-full rounded border px-3 py-2"
                            placeholder="Release Date"
                            value={form.data.release_date}
                            onChange={(e) => form.setData('release_date', e.target.value)}
                        />
                        <input
                            className="w-full rounded border px-3 py-2"
                            placeholder="Storage Location"
                            value={form.data.storage_location}
                            onChange={(e) => form.setData('storage_location', e.target.value)}
                        />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                            Update
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





