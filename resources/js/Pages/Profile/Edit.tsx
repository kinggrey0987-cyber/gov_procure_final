import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    pendingEmailChange,
}: PageProps<{ mustVerifyEmail: boolean; status?: string; twoFactor?: { required: boolean; policy: string }; pendingEmailChange?: { email: string; expires_at?: string | null } | null }>) {
    const page = usePage().props as {
        auth: {
            user: {
                role_code?: string;
            };
        };
                        twoFactor?: {
            required: boolean;
            policy: string;
        };
        pendingEmailChange?: { email: string; expires_at?: string | null } | null;
    };
    const roleCode = page.auth.user.role_code ?? '';
    const canDeleteAccount = !['ADMIN', 'EXEC_OFFICER'].includes(roleCode);
    const twoFactor = page.twoFactor ?? { required: false, policy: 'optional' };

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
                            pendingEmailChange={pendingEmailChange ?? page.pendingEmailChange ?? null}
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

                            <div className="mt-8 max-w-2xl rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h4 className="text-sm font-semibold text-slate-900">Two-Factor Authentication</h4>
                                {twoFactor.required ? (
                                    <p className="mt-2 text-sm text-slate-600">
                                        Two-factor authentication is <span className="font-semibold text-emerald-700">enabled and mandatory</span> for your internal role.
                                        You will be asked to verify an email code on every new sign in.
                                    </p>
                                ) : (
                                    <p className="mt-2 text-sm text-slate-600">
                                        Two-factor authentication is not currently required for this account role.
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>

                    {roleCode === 'EXEC_OFFICER' && (
                        <section className="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-900">Executive Workspace Access</h3>
                                <p className="text-sm text-slate-600">Your profile is scoped for monitoring dashboards, approvals, and governance evidence access.</p>
                            </div>
                            <div className="p-6 sm:p-8">
                                <div className="max-w-2xl rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                    Account role: Executive Officer. Access policies are enforced by role-based authorization and workflow permissions.
                                </div>
                            </div>
                        </section>
                    )}

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





