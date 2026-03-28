import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const page = usePage().props as {
        auth: {
            user: {
                role_code?: string;
            };
        };
    };
    const roleCode = page.auth.user.role_code ?? '';
    const canDeleteAccount = !['ADMIN', 'EXEC_OFFICER'].includes(roleCode);

    return (
        <AuthenticatedLayout header="Profile Settings">
            <Head title="Profile" />

            <div className="py-6">
                <div className="mx-auto max-w-5xl space-y-6">
                    <section className="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-6 py-4 text-white">
                            <h3 className="text-lg font-semibold">Profile Information</h3>
                            <p className="text-sm text-red-100">Maintain your executive identity and account details.</p>
                        </div>
                        <div className="p-6 sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-2xl"
                        />
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                        <div className="border-b border-slate-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-slate-900">Security Settings</h3>
                            <p className="text-sm text-slate-600">Update your password for executive account protection.</p>
                        </div>
                        <div className="p-6 sm:p-8">
                            <UpdatePasswordForm className="max-w-2xl" />
                        </div>
                    </section>

                    {canDeleteAccount && (
                        <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-900">Account Deletion</h3>
                                <p className="text-sm text-slate-600">Permanently remove this account if needed.</p>
                            </div>
                            <div className="p-6 sm:p-8">
                                <DeleteUserForm className="max-w-2xl" />
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
