import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

type DistributionRecordOption = {
    distribution_record_id: number;
    distribution_code: string;
    office_id: number;
    office_name: string;
    release_date?: string | null;
    status: string;
    request_code: string;
};

type Props = {
    distributionRecords: DistributionRecordOption[];
    preselectedDistributionRecordId?: number | null;
};

export default function ClaimCreate({ distributionRecords, preselectedDistributionRecordId = null }: Props) {
    const { auth } = usePage<PageProps>().props;
    const isOfficeUser = auth?.user?.role_code === 'OFFICE_USER';
    const preselectedRecord = preselectedDistributionRecordId
        ? distributionRecords.find((record) => record.distribution_record_id === preselectedDistributionRecordId)
        : null;
    const first = preselectedRecord ?? distributionRecords[0];
    const form = useForm({
        distribution_record_id: first?.distribution_record_id ?? '',
        office_id: first?.office_id ?? 0,
        claim_type: '',
        claim_date: '',
        claim_location: '',
        remarks: '',
        attachment_title: '',
        attachment_file: null as File | null,
    });

    const selectedRecord = useMemo(
        () => distributionRecords.find((record) => record.distribution_record_id === Number(form.data.distribution_record_id)),
        [distributionRecords, form.data.distribution_record_id],
    );

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">File Claim</h2>}>
            <Head title="File Claim" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-sm text-slate-600">Claims are post-distribution issue records. Select an existing released or received distribution record first.</p>
                        <p className="mt-2 text-xs text-slate-500">Typical claim types: missing items, damaged items, quantity mismatch, or wrong item received.</p>
                    </div>

                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route(isOfficeUser ? 'office.claims.store' : 'inventory.claims.store'), { forceFormData: true });
                        }}
                    >
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Select Released Distribution Record</label>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.distribution_record_id}
                                onChange={(e) => {
                                    const selectedRecordId = Number(e.target.value);
                                    const selected = distributionRecords.find((record) => record.distribution_record_id === selectedRecordId);
                                    form.setData('distribution_record_id', selectedRecordId);
                                    form.setData('office_id', selected?.office_id ?? 0);
                                }}
                            >
                                <option value="">Select Distribution Record</option>
                                {distributionRecords.map((record) => (
                                    <option key={record.distribution_record_id} value={record.distribution_record_id}>
                                        {record.distribution_code} - {record.office_name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.distribution_record_id ? <p className="mt-1 text-xs text-rose-600">{form.errors.distribution_record_id}</p> : null}
                        </div>

                        {selectedRecord ? (
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <p><span className="font-semibold text-slate-800">Office:</span> {selectedRecord.office_name}</p>
                                <p><span className="font-semibold text-slate-800">Distribution Code:</span> {selectedRecord.distribution_code}</p>
                                <p><span className="font-semibold text-slate-800">Linked Request:</span> {selectedRecord.request_code}</p>
                                <p><span className="font-semibold text-slate-800">Release Date:</span> {selectedRecord.release_date ?? '-'}</p>
                                <p><span className="font-semibold text-slate-800">Distribution Status:</span> {selectedRecord.status}</p>
                            </div>
                        ) : null}

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Claim Type</label>
                            <select
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                value={form.data.claim_type}
                                onChange={(e) => form.setData('claim_type', e.target.value)}
                            >
                                <option value="">Select issue type</option>
                                <option value="MISSING_ITEM">Missing Item</option>
                                <option value="DAMAGED_ITEM">Damaged Item</option>
                                <option value="QUANTITY_MISMATCH">Quantity Mismatch</option>
                                <option value="WRONG_ITEM_RECEIVED">Wrong Item Received</option>
                                <option value="SHORTAGE">Shortage</option>
                            </select>
                            {form.errors.claim_type ? <p className="mt-1 text-xs text-rose-600">{form.errors.claim_type}</p> : null}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date Observed</label>
                                <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" type="date" value={form.data.claim_date} onChange={(e) => form.setData('claim_date', e.target.value)} />
                                {form.errors.claim_date ? <p className="mt-1 text-xs text-rose-600">{form.errors.claim_date}</p> : null}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Claim Location</label>
                                <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Receiving area, stock room, office desk" value={form.data.claim_location} onChange={(e) => form.setData('claim_location', e.target.value)} />
                                {form.errors.claim_location ? <p className="mt-1 text-xs text-rose-600">{form.errors.claim_location}</p> : null}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Description / Remarks</label>
                            <textarea className="min-h-[90px] w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Describe what was observed and what quantity/item was affected." value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                            {form.errors.remarks ? <p className="mt-1 text-xs text-rose-600">{form.errors.remarks}</p> : null}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Attachment Title (Optional)</label>
                                <input className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Photo evidence or issue report" value={form.data.attachment_title} onChange={(e) => form.setData('attachment_title', e.target.value)} />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Attachment File (Optional)</label>
                                <input
                                    type="file"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    onChange={(e) => form.setData('attachment_file', e.target.files?.[0] ?? null)}
                                />
                            </div>
                        </div>

                        {form.hasErrors ? <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">Please review the highlighted claim form fields before submitting.</p> : null}

                        <input type="hidden" value={form.data.office_id} readOnly />

                        <button type="submit" disabled={form.processing} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60">
                            {form.processing ? 'Submitting Claim...' : 'Submit Claim'}
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}





