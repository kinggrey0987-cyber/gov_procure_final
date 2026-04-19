import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';

type EligibleSource = {
    label: string;
    reference_type: string;
    reference_id: number;
    suggested_type: string;
    suggested_title: string;
};

type Props = {
    eligibleSources: {
        approved_ppmps: EligibleSource[];
        completed_purchase_orders: EligibleSource[];
        distribution_summaries: EligibleSource[];
    };
};

export default function DisclosureCreate({ eligibleSources }: Props) {
    const form = useForm({
        title: '',
        type: '',
        reference_type: '',
        reference_id: '',
        description: '',
        file_path: '',
        status: 'DRAFT',
        remarks: '',
    });

    const sourceOptions = [
        ...eligibleSources.approved_ppmps,
        ...eligibleSources.completed_purchase_orders,
        ...eligibleSources.distribution_summaries,
    ];

    const onSelectSource = (value: string) => {
        if (!value) {
            form.setData('reference_type', '');
            form.setData('reference_id', '');
            return;
        }

        const source = sourceOptions.find((item) => `${item.reference_type}:${item.reference_id}` === value);
        if (!source) {
            return;
        }

        form.setData('reference_type', source.reference_type);
        form.setData('reference_id', String(source.reference_id));

        if (!form.data.title) {
            form.setData('title', source.suggested_title);
        }

        if (!form.data.type) {
            form.setData('type', source.suggested_type);
        }
    };

    return (
        <AuthenticatedLayout header="Create Disclosure Draft">
            <Head title="Create Disclosure Draft" />
            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white px-6 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Disclosure Drafting</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">Create Disclosure Draft</h3>
                    <p className="mt-1 text-sm text-slate-600">Prepare a disclosure draft for governance review and publication workflow.</p>
                </section>

                <div className="mx-auto w-full max-w-3xl">
                    <form
                        className="space-y-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('executive.disclosures.store'));
                        }}
                    >
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Disclosure Eligibility Source</p>
                            <p className="mt-1 text-sm text-slate-700">Select an approved/completed source record to anchor this public disclosure.</p>
                            <select
                                className="mt-3 w-full rounded border px-3 py-2 text-sm"
                                onChange={(e) => onSelectSource(e.target.value)}
                                defaultValue=""
                            >
                                <option value="">Manual entry (no selected source)</option>
                                {sourceOptions.map((source) => (
                                    <option key={`${source.reference_type}-${source.reference_id}`} value={`${source.reference_type}:${source.reference_id}`}>
                                        {source.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <input className="w-full rounded border px-3 py-2" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Reference Type" value={form.data.reference_type} onChange={(e) => form.setData('reference_type', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Reference ID" value={form.data.reference_id} onChange={(e) => form.setData('reference_id', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Public File Path (optional, storage path)" value={form.data.file_path} onChange={(e) => form.setData('file_path', e.target.value)} />
                        <textarea className="w-full rounded border px-3 py-2" placeholder="Description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />

                        <select className="w-full rounded border px-3 py-2" value={form.data.status} onChange={(e) => form.setData('status', e.target.value as 'DRAFT' | 'PUBLISHED')}>
                            <option value="DRAFT">Save as Draft</option>
                            <option value="PUBLISHED">Publish Immediately</option>
                        </select>
                        <textarea className="w-full rounded border px-3 py-2" placeholder="Publication remarks (optional)" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />

                        <div className="flex flex-wrap items-center gap-2">
                            <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                {form.data.status === 'PUBLISHED' ? 'Publish Disclosure' : 'Save Draft'}
                            </button>
                            <Link href={route('executive.disclosures.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Back to Disclosure Review
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
