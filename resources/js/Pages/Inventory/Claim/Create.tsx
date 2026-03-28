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
        office_id: first?.office_id ?? '',
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
                    <form
                        className="space-y-3 rounded border bg-white p-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('inventory.claims.store'));
                        }}
                    >
                        <input className="w-full rounded border px-3 py-2" placeholder="Distribution Record ID" value={form.data.distribution_record_id} onChange={(e) => form.setData('distribution_record_id', Number(e.target.value))} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Office ID" value={form.data.office_id} onChange={(e) => form.setData('office_id', Number(e.target.value))} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Claim Type" value={form.data.claim_type} onChange={(e) => form.setData('claim_type', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Claim Date" value={form.data.claim_date} onChange={(e) => form.setData('claim_date', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Claim Location" value={form.data.claim_location} onChange={(e) => form.setData('claim_location', e.target.value)} />
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">Submit</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
