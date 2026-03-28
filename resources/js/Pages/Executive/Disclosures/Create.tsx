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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Create Disclosure Draft</h2>}>
            <Head title="Create Disclosure Draft" />
            <div className="py-10">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form
                        className="space-y-3 rounded border bg-white p-6"
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
                        <button type="submit" className="rounded bg-indigo-600 px-4 py-2 text-sm text-white">Save Draft</button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
