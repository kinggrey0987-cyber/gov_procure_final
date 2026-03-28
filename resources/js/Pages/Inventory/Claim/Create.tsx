import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type DistributionRecordOption = {
    distribution_record_id: number;
    office_id: number;
};

type Props = {
    distributionRecords: DistributionRecordOption[];
};

export default function ClaimCreate({ distributionRecords }: Props) {
    const first = distributionRecords[0];
    const form = useForm({
        distribution_record_id: first?.distribution_record_id ?? '',
        office_id: first?.office_id ?? 0,
        claim_type: '',
        claim_date: '',
        claim_location: '',
        remarks: '',
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">File Claim</h2>}>
            <Head title="File Claim" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-sm text-slate-600">Claims are for post-distribution issues only and should reference an existing released record.</p>
                    </div>

                    <form
                        className="space-y-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('inventory.claims.store'));
                        }}
                    >
                        <select
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            value={form.data.distribution_record_id}
                            onChange={(e) => {
                                const selectedRecordId = Number(e.target.value);
                                const selectedRecord = distributionRecords.find((record) => record.distribution_record_id === selectedRecordId);
                                form.setData('distribution_record_id', selectedRecordId);
                                form.setData('office_id', selectedRecord?.office_id ?? 0);
                            }}
                        >
                            <option value="">Select Distribution Record</option>
                            {distributionRecords.map((record) => (
                                <option key={record.distribution_record_id} value={record.distribution_record_id}>
                                    Record #{record.distribution_record_id}
                                </option>
                            ))}
                        </select>

                        <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Office ID" value={form.data.office_id || ''} onChange={(e) => form.setData('office_id', Number(e.target.value))} />
                        <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Claim Type" value={form.data.claim_type} onChange={(e) => form.setData('claim_type', e.target.value)} />
                        <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" type="date" value={form.data.claim_date} onChange={(e) => form.setData('claim_date', e.target.value)} />
                        <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Claim Location" value={form.data.claim_location} onChange={(e) => form.setData('claim_location', e.target.value)} />
                        <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Submit</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
