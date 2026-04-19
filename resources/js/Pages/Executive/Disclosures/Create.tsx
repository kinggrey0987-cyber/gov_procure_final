import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function DisclosureCreate() {
    const form = useForm({
        title: '',
        type: '',
        reference_type: '',
        reference_id: '',
        description: '',
        file_path: '',
    });

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
                        <input className="w-full rounded border px-3 py-2" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Reference Type" value={form.data.reference_type} onChange={(e) => form.setData('reference_type', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="Reference ID" value={form.data.reference_id} onChange={(e) => form.setData('reference_id', e.target.value)} />
                        <input className="w-full rounded border px-3 py-2" placeholder="File Path" value={form.data.file_path} onChange={(e) => form.setData('file_path', e.target.value)} />
                        <textarea className="w-full rounded border px-3 py-2" placeholder="Description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        <button type="submit" className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Save Draft</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
