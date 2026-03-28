import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-red-700 via-red-700 to-red-800">
            <Head title="Log in" />

            <header className="border-b border-white/15 bg-red-700/80 backdrop-blur-sm">
                <div className="mx-auto flex w-full max-w-6xl items-center px-4 py-4 sm:px-6 lg:px-8">
                    <a href="/" className="flex items-center gap-3">
                        <img src="/logo.jpg" alt="Municipality of E.B. Magalona Logo" className="h-12 w-12 rounded border border-white/40 bg-white object-cover" />
                        <div>
                            <p className="text-lg font-bold text-white sm:text-2xl">Municipality of E.B. Magalona</p>
                            <p className="text-sm text-amber-300">Negros Occidental - Municipal Government Portal</p>
                        </div>
                    </a>
                </div>
            </header>

            <main className="mx-auto flex w-full max-w-6xl flex-col items-center px-4 pb-10 pt-8 sm:px-6 sm:pt-12 lg:px-8">
                <div className="text-center">
                    <img
                        src="/logo.jpg"
                        alt="GovSupply Connect Logo"
                        className="mx-auto h-20 w-20 rounded-full border-4 border-white/70 bg-white object-cover shadow-md"
                    />
                    <h1 className="mt-5 text-4xl font-extrabold text-white">GovSupply Connect</h1>
                    <p className="mx-auto mt-2 max-w-2xl text-xl text-amber-300">Municipal Procurement Transparency and Supply Distribution System</p>
                </div>

                <section className="mt-8 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-2xl sm:px-7 sm:py-7">
                    <div className="text-center">
                        <h2 className="text-3xl font-bold text-red-700">Secure Login</h2>
                        <p className="mt-2 text-lg text-slate-500">Enter your credentials to access the system</p>
                    </div>

                    {status && (
                        <div className="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                            {status}
                        </div>
                    )}

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
                                isFocused={true}
                                onChange={(e) => setData('email', e.target.value)}
                            />

                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="password" value="Password" />

                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />

                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <label className="flex items-center">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) =>
                                        setData(
                                            'remember',
                                            (e.target.checked || false) as false,
                                        )
                                    }
                                />
                                <span className="ms-2 text-sm text-slate-600">
                                    Remember me
                                </span>
                            </label>

                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-sm font-semibold text-red-700 hover:text-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                                >
                                    Forgot Password?
                                </Link>
                            )}
                        </div>

                        <div className="flex flex-col gap-3 pt-1">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing ? 'Signing In...' : 'Sign In Securely'}
                            </button>

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

            <footer className="border-t border-white/20 bg-red-700/90 py-6 text-center">
                <div className="mx-auto max-w-6xl space-y-2 px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-center gap-2 text-white">
                        <img src="/logo.jpg" alt="Municipality Seal" className="h-7 w-7 rounded border border-white/40 bg-white object-cover" />
                        <p className="text-base font-medium">© 2026 Municipality of E.B. Magalona, Negros Occidental</p>
                    </div>
                    <p className="text-base text-amber-300">
                        GovSupply Connect | Procurement and Supply Monitoring System | Version 2.0.1
                    </p>
                    <p className="text-base text-white">Transparency • Accountability • Public Service</p>
                </div>
            </footer>
        </div>
    );
}
