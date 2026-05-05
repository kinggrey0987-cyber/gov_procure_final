import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function CreateOffice() {
    const form = useForm({
        name: '',
        code: '',
        description: '',
        is_active: true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            is_active: data.is_active ? 1 : 0,
        }));

        form.post(route('admin-tech.offices.store'));
    };

    return (
        <AuthenticatedLayout header="Create Office">
            <Head title="Create Office" />

            <section className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header className="mb-6">
                    <h3 className="text-2xl font-semibold text-slate-800">Create Office</h3>
                    <p className="mt-1 text-sm text-slate-500">Add a municipal office for technical access assignment and domain ownership.</p>
                </header>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="name" value="Office Name" />
                            <TextInput
                                id="name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                className="mt-1 block w-full"
                                required
                            />
                            <InputError message={form.errors.name} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="code" value="Office Code" />
                            <TextInput
                                id="code"
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                                className="mt-1 block w-full"
                                required
                            />
                            <InputError message={form.errors.code} className="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="description" value="Description" />
                        <textarea
                            id="description"
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={4}
                        />
                        <InputError message={form.errors.description} className="mt-2" />
                    </div>

                    <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) => form.setData('is_active', event.target.checked)}
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        Office is active
                    </label>
                    <InputError message={form.errors.is_active} className="mt-2" />

                    <div className="flex items-center gap-3 pt-2">
                        <PrimaryButton disabled={form.processing}>Create Office</PrimaryButton>
                        <Link
                            href={route('admin-tech.offices.index')}
                            className="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}





