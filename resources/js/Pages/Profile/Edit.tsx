import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
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
    const twoFactor = page.twoFactor ?? { required: false, policy: 'optional' };

    return (
        <AuthenticatedLayout header="Profile Settings">
            <Head title="Profile" />

            <div className="py-2 lg:h-[calc(100vh-8rem)] flex flex-col justify-start overflow-y-auto lg:overflow-hidden">
                <div className="mx-auto w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-3.5 items-start">
                    {/* Card 1: Profile Information (Matches User Manual Screenshot) */}
                    <section className="overflow-hidden rounded-xl border border-red-200 bg-white shadow-xs">
                        <div className="border-b border-slate-200 bg-gradient-to-r from-red-700 via-red-600 to-red-500 px-4 py-2.5 text-white">
                            <h3 className="text-base font-semibold">Profile Information</h3>
                            <p className="text-xs text-red-100">Maintain your executive identity and account details.</p>
                        </div>
                        <div className="p-4 sm:p-5">
                            <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                                pendingEmailChange={pendingEmailChange ?? page.pendingEmailChange ?? null}
                                className="w-full"
                            />
                        </div>
                    </section>

                    {/* Card 2: Security Settings & 2FA (Matches User Manual Screenshot) */}
                    <div className="space-y-3.5">
                        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                            <div className="border-b border-slate-200 px-4 py-2.5 bg-slate-50/50">
                                <h3 className="text-base font-semibold text-slate-900">Security Settings</h3>
                                <p className="text-xs text-slate-600">Update your password for executive account protection.</p>
                            </div>
                            <div className="p-4 sm:p-5">
                                <UpdatePasswordForm className="w-full" hideHeader />

                                <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <h4 className="text-xs font-semibold text-slate-900">Two-Factor Authentication</h4>
                                    {twoFactor.required ? (
                                        <p className="mt-1 text-xs text-slate-600">
                                            Two-factor authentication is <span className="font-semibold text-emerald-700">enabled and mandatory</span> for your internal role.
                                            You will be asked to verify an email code on every new sign in.
                                        </p>
                                    ) : (
                                        <p className="mt-1 text-xs text-slate-600">
                                            Two-factor authentication is not currently required for this account role.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>

                        {roleCode === 'EXEC_OFFICER' && (
                            <section className="overflow-hidden rounded-xl border border-red-100 bg-white shadow-xs">
                                <div className="border-b border-slate-200 px-4 py-2 bg-slate-50/50">
                                    <h3 className="text-xs font-semibold text-slate-900">Executive Workspace Access</h3>
                                    <p className="text-[11px] text-slate-600">Scanned access policies for monitoring dashboards, approvals, and governance.</p>
                                </div>
                                <div className="p-3">
                                    <div className="rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs text-red-800">
                                        Account role: Executive Officer. Access policies are enforced by role-based authorization and workflow permissions.
                                    </div>
                                </div>
                            </section>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
