import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';
import { PageProps as AppPageProps } from '@/types';

type PageProps = AppPageProps & {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

const extractLockoutSeconds = (message?: string): number => {
    if (! message) {
        return 0;
    }

    const secondsMatch = message.match(/(\d+)\s+seconds?/i);
    if (secondsMatch) {
        return Number(secondsMatch[1]);
    }

    const minutesMatch = message.match(/(\d+)\s+minutes?/i);
    if (minutesMatch) {
        return Number(minutesMatch[1]) * 60;
    }

    return 0;
};

const formatCountdown = (seconds: number): string => {
    const safeSeconds = Math.max(0, seconds);
    const minutes = Math.floor(safeSeconds / 60);
    const remainingSeconds = safeSeconds % 60;

    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
};

export default function Login({
    status,
    error,
    canResetPassword,
}: {
    status?: string;
    error?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });
    const { flash } = usePage<PageProps>().props;
    const visibleStatus = status ?? flash?.success ?? null;
    const visibleError = error ?? flash?.error ?? null;
    const [lockoutSeconds, setLockoutSeconds] = useState(0);
    const emailError = useMemo(() => {
        if (! errors.email || lockoutSeconds <= 0) {
            return errors.email;
        }

        return `Too many login attempts. Please try again in ${formatCountdown(lockoutSeconds)}.`;
    }, [errors.email, lockoutSeconds]);

    useEffect(() => {
        setLockoutSeconds(extractLockoutSeconds(errors.email));
    }, [errors.email]);

    useEffect(() => {
        if (lockoutSeconds <= 0) {
            return;
        }

        const interval = window.setInterval(() => {
            setLockoutSeconds((seconds) => Math.max(0, seconds - 1));
        }, 1000);

        return () => window.clearInterval(interval);
    }, [lockoutSeconds]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Refresh activity timestamp in localStorage so new session doesn't inherit old inactivity time
        try {
            localStorage.setItem('govsupply_session_last_active', Date.now().toString());
        } catch {
            // Ignore
        }

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="flex min-h-screen flex-col justify-between bg-gradient-to-b from-red-700 via-red-700 to-red-800 lg:h-screen lg:overflow-hidden">
            <Head title="Login" />

            <header className="shrink-0 border-b border-white/15 bg-red-700/80 backdrop-blur-sm">
                <div className="mx-auto flex w-full max-w-6xl items-center px-4 py-2.5 sm:px-6 lg:px-8">
                    <a href="/" className="flex items-center gap-2.5">
                        <img src="/logo.jpg" alt="Municipality of E.B. Magalona Logo" className="h-9 w-9 rounded border border-white/40 bg-white object-cover sm:h-10 sm:w-10" />
                        <div>
                            <p className="text-base font-bold leading-tight text-white sm:text-xl">Municipality of E.B. Magalona</p>
                            <p className="text-xs text-amber-300">Negros Occidental - Municipal Government Portal</p>
                        </div>
                    </a>
                </div>
            </header>

            <main className="mx-auto my-auto flex w-full max-w-6xl flex-col items-center px-4 py-3 sm:px-6 lg:px-8">
                <div className="text-center">
                    <img
                        src="/logo.jpg"
                        alt="GovSupply Connect Logo"
                        className="mx-auto h-14 w-14 rounded-full border-2 border-white/70 bg-white object-cover shadow-md sm:h-16 sm:w-16"
                    />
                    <h1 className="mt-2 text-2xl font-extrabold text-white sm:text-3xl">GovSupply Connect</h1>
                    <p className="mx-auto mt-0.5 max-w-2xl text-xs text-amber-300 sm:text-sm">Municipal Procurement Transparency and Supply Distribution System</p>
                </div>

                <section className="mt-3 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-2xl sm:mt-4 sm:px-6 sm:py-5">
                    <div className="text-center">
                        <h2 className="text-2xl font-bold text-red-700 sm:text-2xl">Secure Login</h2>
                        <p className="mt-1 text-xs text-slate-500 sm:text-sm">Enter your credentials to access the system</p>
                    </div>

                    {visibleStatus && (
                        <div className="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-700">
                            {visibleStatus}
                        </div>
                    )}

                    {visibleError && (
                        <div className="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                            {visibleError}
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-3.5 space-y-3">
                        <div>
                            <InputLabel htmlFor="email" value="Email / Username" />

                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block h-9 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 sm:h-10"
                                autoComplete="username"
                                isFocused={true}
                                onChange={(e) => setData('email', e.target.value)}
                            />

                            <InputError message={emailError} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="password" value="Password" />

                            <PasswordInput
                                id="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block h-9 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 sm:h-10"
                                wrapperClassName="mt-1"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />

                            <InputError message={errors.password} className="mt-1" />
                        </div>

                        <div className="flex items-center justify-between gap-2 pt-0.5">
                            <label className="flex items-center">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) =>
                                        setData('remember', e.target.checked)
                                    }
                                />
                                <span className="ms-2 text-xs text-slate-600 sm:text-sm">
                                    Remember me
                                </span>
                            </label>

                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-semibold text-red-700 hover:text-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 sm:text-sm"
                                >
                                    Forgot Password?
                                </Link>
                            )}
                        </div>

                        <div className="flex flex-col gap-2 pt-1">
                            <button
                                type="submit"
                                disabled={processing || lockoutSeconds > 0}
                                className="inline-flex h-9 w-full items-center justify-center rounded-lg bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 sm:h-10"
                            >
                                {processing
                                    ? 'Signing In...'
                                    : lockoutSeconds > 0
                                        ? `Sign In Securely (${formatCountdown(lockoutSeconds)})`
                                        : 'Sign In Securely'}
                            </button>

                            <div className="relative py-1">
                                <div className="absolute inset-0 flex items-center">
                                    <div className="w-full border-t border-slate-200"></div>
                                </div>
                                <div className="relative flex justify-center text-xs">
                                    <span className="bg-white px-2 text-slate-400">Or continue with</span>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <a
                                    href={route('google.redirect')}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:h-9"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                    </svg>
                                    Google
                                </a>

                                <a
                                    href="/"
                                    className="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:h-9"
                                >
                                    Home Portal
                                </a>
                            </div>
                        </div>
                    </form>
                </section>

            </main>

            <footer className="shrink-0 border-t border-white/20 bg-red-700/90 py-2.5 text-center">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 text-xs text-white/90 sm:px-6">
                    <div className="flex items-center gap-1.5">
                        <img src="/logo.jpg" alt="Municipality Seal" className="h-5 w-5 rounded border border-white/40 bg-white object-cover" />
                        <span className="font-semibold">Municipality of E.B. Magalona</span>
                    </div>
                    <span>•</span>
                    <span className="text-amber-300">GovSupply Connect v2.0.1</span>
                    <span className="hidden sm:inline">•</span>
                    <span className="hidden sm:inline">Transparency & Public Service</span>
                </div>
            </footer>
        </div>
    );
}




