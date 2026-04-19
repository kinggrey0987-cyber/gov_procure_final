import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

type EligibleSource = {
    label: string;
    reference_type: string;
    reference_id: number;
    suggested_type: string;
    suggested_title: string;
};

type Props = {
    disclosure: {
        disclosure_id: number;
        title: string;
        type: string;
        reference_type?: string | null;
        reference_id?: number | null;
        description?: string | null;
        file_path?: string | null;
        status: string;
    };
    eligibleSources: {
        approved_ppmps: EligibleSource[];
        completed_purchase_orders: EligibleSource[];
        distribution_summaries: EligibleSource[];
    };
};

export default function DisclosureEdit({ disclosure, eligibleSources }: Props) {
    const form = useForm({
        title: disclosure.title ?? '',
        type: disclosure.type ?? '',
        reference_type: disclosure.reference_type ?? '',
        reference_id: disclosure.reference_id ? String(disclosure.reference_id) : '',
        description: disclosure.description ?? '',
        file_path: disclosure.file_path ?? '',
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
            return;
        }

        const source = sourceOptions.find((item) => `${item.reference_type}:${item.reference_id}` === value);
        if (!source) {
            return;
        }

        form.setData('reference_type', source.reference_type);
        form.setData('reference_id', String(source.reference_id));
    };

    return (
        <AuthenticatedLayout header="Edit Disclosure Draft">
            <Head title="Edit Disclosure Draft" />
            <div className="space-y-6 py-8">
                <section className="rounded-2xl border border-red-200 bg-white px-6 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Disclosure Draft Editing</p>
                    <h3 className="mt-1 text-2xl font-semibold text-slate-900">Edit Draft Disclosure</h3>
                    <p className="mt-1 text-sm text-slate-600">Refine disclosure details before final executive publication.</p>
                </section>

                <div className="mx-auto w-full max-w-3xl">
                    <form
                        className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.06)]"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('executive.disclosures.update', disclosure.disclosure_id));
                        }}
                    >
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-red-700">Disclosure Eligibility Source</p>
                            <select className="mt-3 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" onChange={(e) => onSelectSource(e.target.value)} defaultValue="">
                                <option value="">Keep current source mapping</option>
                                {sourceOptions.map((source) => (
                                    <option key={`${source.reference_type}-${source.reference_id}`} value={`${source.reference_type}:${source.reference_id}`}>
                                        {source.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Title" required>
                                <input className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="Disclosure title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                            </Field>
                            <Field label="Type" required>
                                <input className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="DISCLOSURE_TYPE" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                            </Field>
                            <Field label="Reference Type">
                                <input className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="App\\Models\\..." value={form.data.reference_type} onChange={(e) => form.setData('reference_type', e.target.value)} />
                            </Field>
                            <Field label="Reference ID">
                                <input className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="Reference ID" value={form.data.reference_id} onChange={(e) => form.setData('reference_id', e.target.value)} />
                            </Field>
                        </div>

                        <Field label="Public File Path">
                            <input className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="Storage path if report file exists" value={form.data.file_path} onChange={(e) => form.setData('file_path', e.target.value)} />
                        </Field>

                        <Field label="Description">
                            <textarea className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" rows={5} placeholder="Public-facing summary of this disclosure" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        </Field>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Action">
                                <select className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" value={form.data.status} onChange={(e) => form.setData('status', e.target.value as 'DRAFT' | 'PUBLISHED')}>
                                    <option value="DRAFT">Keep as Draft</option>
                                    <option value="PUBLISHED">Publish Now</option>
                                </select>
                            </Field>
                            <Field label="Publication Remarks">
                                <input className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="Optional remarks" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                            </Field>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                {form.data.status === 'PUBLISHED' ? 'Publish Disclosure' : 'Update Draft'}
                            </button>
                            <Link href={route('executive.disclosures.show', disclosure.disclosure_id)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Back to Detail
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    children,
    required,
}: {
    label: string;
    children: React.ReactNode;
    required?: boolean;
}) {
    return (
        <label className="block space-y-1">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                {label}
                {required ? ' *' : ''}
            </span>
            {children}
        </label>
    );
}
