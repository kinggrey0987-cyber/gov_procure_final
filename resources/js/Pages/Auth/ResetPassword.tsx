import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { normalizePasswordValidationMessage } from '@/lib/passwordValidationMessage';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });
    const passwordError = normalizePasswordValidationMessage(errors.password);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-red-700 via-red-700 to-red-800">
            <Head title="Reset Password" />

            <header className="border-b border-white/15 bg-red-700/80 backdrop-blur-sm">
                <div className="mx-auto flex w-full max-w-6xl items-center px-4 py-4 sm:px-6 lg:px-8">
                    <a href="/" className="flex items-center gap-3">
                        <img src="/logo.jpg" alt="Municipality of E.B. Magalona Logo" className="h-12 w-12 rounded border border-white/40 bg-white object-cover" />
                        <div>
                            <p className="text-lg font-bold text-white sm:text-2xl">Municipality of E.B. Magalona</p>
                            <p className="text-sm text-amber-300">GovSupply Connect Account Recovery</p>
                        </div>
                    </a>
                </div>
            </header>

            <main className="mx-auto flex w-full max-w-6xl flex-col items-center px-4 pb-10 pt-8 sm:px-6 sm:pt-12 lg:px-8">
                <section className="mt-8 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-2xl sm:px-7 sm:py-7">
                    <div className="text-center">
                        <h2 className="text-3xl font-bold text-red-700">Reset Password</h2>
                        <p className="mt-2 text-lg text-slate-500">Create a new secure password for your GovSupply Connect account.</p>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div>
                            <InputLabel htmlFor="email" value="Email / Username" />

                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                autoComplete="username"
                                onChange={(e) => setData('email', e.target.value)}
                            />

                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="password" value="New Password" />

                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                autoComplete="new-password"
                                isFocused={true}
                                onChange={(e) => setData('password', e.target.value)}
                            />

                            <InputError message={passwordError} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="password_confirmation"
                                value="Confirm New Password"
                            />

                            <TextInput
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                autoComplete="new-password"
                                onChange={(e) =>
                                    setData('password_confirmation', e.target.value)
                                }
                            />

                            <InputError
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? 'Resetting Password...' : 'Reset Password'}
                        </button>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Link
                                href={route('login')}
                                className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                            >
                                Back to Login
                            </Link>

                            <a
                                href="/"
                                className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                            >
                                Return to Home
                            </a>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    );
}





