import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Props = {
    disclosure: {
        disclosure_id: number;
        title: string;
        type: string;
        status: string;
        description?: string | null;
        file_path?: string | null;
    };
};

export default function DisclosureShow({ disclosure }: Props) {
    const form = useForm({ remarks: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Disclosure Detail</h2>}>
            <Head title="Disclosure Detail" />
            <div className="py-10">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="rounded border bg-white p-6 text-sm space-y-1">
                        <p><strong>ID:</strong> {disclosure.disclosure_id}</p>
                        <p><strong>Title:</strong> {disclosure.title}</p>
                        <p><strong>Type:</strong> {disclosure.type}</p>
                        <p><strong>Status:</strong> {disclosure.status}</p>
                        <p><strong>Description:</strong> {disclosure.description ?? '-'}</p>
                        <p><strong>File:</strong> {disclosure.file_path ?? '-'}</p>
                    </div>

                    {disclosure.status !== 'PUBLISHED' && (
                        <form
                            className="space-y-2 rounded border bg-white p-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(route('executive.disclosures.publish', disclosure.disclosure_id));
                            }}
                        >
                            <input className="w-full rounded border px-3 py-2" placeholder="Publish remarks" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                            <button type="submit" className="rounded bg-emerald-600 px-4 py-2 text-sm text-white">Publish</button>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
