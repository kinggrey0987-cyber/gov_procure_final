import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    pendingEmailChange,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    pendingEmailChange?: { email: string; expires_at?: string | null } | null;
    className?: string;
}) {
    const user = usePage().props.auth.user;
    const [confirmingEmailChange, setConfirmingEmailChange] = useState(Boolean(pendingEmailChange));

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const emailCodeForm = useForm({
        code: '',
    });

    useEffect(() => {
        if (pendingEmailChange) {
            setConfirmingEmailChange(true);
        }
    }, [pendingEmailChange]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    const confirmEmailChange: FormEventHandler = (e) => {
        e.preventDefault();

        emailCodeForm.post(route('profile.email-change.confirm'), {
            preserveScroll: true,
            onSuccess: () => {
                emailCodeForm.reset('code');
                setConfirmingEmailChange(false);
            },
        });
    };

    const closeEmailChangeModal = () => {
        setConfirmingEmailChange(false);
        emailCodeForm.clearErrors();
        emailCodeForm.reset('code');
    };

    const cancelEmailChange = () => {
        router.delete(route('profile.email-change.cancel'), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmingEmailChange(false);
                emailCodeForm.clearErrors();
                emailCodeForm.reset('code');
                setData('email', user.email);
            },
        });
    };

    const resendEmailChangeCode = () => {
        if (!pendingEmailChange) {
            return;
        }

        router.patch(route('profile.update'), {
            name: data.name,
            email: pendingEmailChange.email,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Profile Information
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Update your account's profile information and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Name" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                    {data.email !== user.email ? (
                        <p className="mt-2 text-xs text-amber-700">
                            Changing your email requires a verification code sent to your current email address: {user.email}.
                        </p>
                    ) : null}
                </div>

                {status === 'email-change-code-sent' ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        A verification code was sent to your current email address. Enter it below to confirm the email change.
                    </div>
                ) : null}

                {status === 'email-change-confirmed' ? (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Your email address was updated. Please verify the new email address if prompted.
                    </div>
                ) : null}

                {status === 'email-change-cancelled' ? (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        The pending email change was cancelled.
                    </div>
                ) : null}

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Your email address is unverified.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your
                                email address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>

            {pendingEmailChange ? (
                <>
                    <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 className="text-sm font-semibold text-slate-900">Email change pending</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Pending new email: <span className="font-semibold text-slate-900">{pendingEmailChange.email}</span>
                        </p>
                        <PrimaryButton className="mt-4" onClick={() => setConfirmingEmailChange(true)}>
                            Confirm Email Change
                        </PrimaryButton>
                    </div>

                    <Modal show={confirmingEmailChange} onClose={closeEmailChangeModal} maxWidth="md">
                        <form onSubmit={confirmEmailChange} className="p-6">
                            <h3 className="text-lg font-semibold text-slate-900">Confirm Email Change</h3>
                            <p className="mt-1 text-sm text-slate-600">
                                Enter the verification code sent to your current email address to confirm the new email: <span className="font-semibold text-slate-900">{pendingEmailChange.email}</span>
                            </p>

                            <div className="mt-6">
                                <InputLabel htmlFor="email-change-code" value="Verification Code" />
                                <TextInput
                                    id="email-change-code"
                                    className="mt-1 block w-full"
                                    value={emailCodeForm.data.code}
                                    onChange={(e) => emailCodeForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                    inputMode="numeric"
                                    maxLength={6}
                                    placeholder="Enter 6-digit code"
                                    required
                                    isFocused
                                />
                                <InputError className="mt-2" message={emailCodeForm.errors.code} />
                            </div>

                            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                                <button
                                    type="button"
                                    onClick={resendEmailChangeCode}
                                    disabled={processing}
                                    className="text-sm font-semibold text-slate-600 hover:text-slate-900 disabled:opacity-50 sm:me-auto"
                                >
                                    Resend code
                                </button>
                                <SecondaryButton type="button" onClick={cancelEmailChange} disabled={emailCodeForm.processing}>
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton disabled={emailCodeForm.processing}>Confirm Email Change</PrimaryButton>
                            </div>
                        </form>
                    </Modal>
                </>
            ) : null}
        </section>
    );
}

