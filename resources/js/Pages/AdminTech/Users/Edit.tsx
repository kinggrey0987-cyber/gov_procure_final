import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type RoleOption = {
    id: number;
    code: string;
    name: string;
};

type OfficeOption = {
    id: number;
    code: string;
    name: string;
};

type UserRecord = {
    id: number;
    name: string;
    email: string;
    emp_id: string | null;
    role_id: number;
    role_code: string;
    office_id: number | null;
};

type Props = {
    userRecord: UserRecord;
    roles: RoleOption[];
    offices: OfficeOption[];
};

export default function EditUser({ userRecord, roles, offices }: Props) {
    const form = useForm({
        name: userRecord.name,
        email: userRecord.email,
        emp_id: userRecord.emp_id ?? '',
        role_id: String(userRecord.role_id),
        office_id: userRecord.office_id ? String(userRecord.office_id) : '',
    });

    const selectedRole = roles.find((role) => String(role.id) === form.data.role_id);
    const isAdminRole = selectedRole?.code === 'ADMIN';

    useEffect(() => {
        if (isAdminRole) {
            if (form.data.office_id !== '') {
                form.setData('office_id', '');
            }

            return;
        }

        if (!form.data.office_id && offices[0]) {
            form.setData('office_id', String(offices[0].id));
        }
    }, [form, form.data.office_id, isAdminRole, offices]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.put(route('admin-tech.users.update', userRecord.id));
    };

    return (
        <AuthenticatedLayout header="Edit User Account">
            <Head title="Edit User Account" />

            <section className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header className="mb-6">
                    <h3 className="text-2xl font-semibold text-slate-800">Edit User Account</h3>
                    <p className="mt-1 text-sm text-slate-500">Update account details while preserving role-specific governance rules.</p>
                </header>

                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <InputLabel htmlFor="name" value="Full Name" />
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
                        <InputLabel htmlFor="email" value="Email Address" />
                        <TextInput
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            className="mt-1 block w-full"
                            required
                        />
                        <InputError message={form.errors.email} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="emp_id" value="Employee ID" />
                        <TextInput
                            id="emp_id"
                            type="text"
                            value={form.data.emp_id}
                            onChange={(event) => form.setData('emp_id', event.target.value)}
                            className="mt-1 block w-full"
                            placeholder="e.g., EMP2026001"
                            required
                        />
                        <InputError message={form.errors.emp_id} className="mt-2" />
                        <p className="mt-2 text-xs text-slate-500">
                            The unique government/employee ID. Required and must be unique.
                        </p>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="role_id" value="Role" />
                            <select
                                id="role_id"
                                value={form.data.role_id}
                                onChange={(event) => form.setData('role_id', event.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.role_id} className="mt-2" />
                        </div>

                        {isAdminRole ? (
                            <div>
                                <InputLabel htmlFor="office_na" value="Office" />
                                <input
                                    id="office_na"
                                    value="Technical Administration (Auto-assigned)"
                                    className="mt-1 block w-full rounded-md border-gray-200 bg-slate-100 text-slate-600"
                                    disabled
                                    readOnly
                                />
                                <p className="mt-2 text-xs text-slate-500">
                                    Admin accounts are technical-only and are not assigned to operational office workflows.
                                </p>
                                <InputError message={form.errors.office_id} className="mt-2" />
                            </div>
                        ) : (
                            <div>
                                <InputLabel htmlFor="office_id" value="Office" />
                                <select
                                    id="office_id"
                                    value={form.data.office_id}
                                    onChange={(event) => form.setData('office_id', event.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="" disabled>
                                        Select office
                                    </option>
                                    {offices.map((office) => (
                                        <option key={office.id} value={office.id}>
                                            {office.code} - {office.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.office_id} className="mt-2" />
                            </div>
                        )}
                    </div>

                    <p className="text-xs text-slate-500">
                        Changing a user to Admin automatically uses Technical Administration. Non-admin roles must keep a valid operational office.
                    </p>

                    <div className="flex items-center gap-3 pt-2">
                        <PrimaryButton disabled={form.processing}>Save Changes</PrimaryButton>
                        <Link
                            href={route('admin-tech.users.index')}
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
