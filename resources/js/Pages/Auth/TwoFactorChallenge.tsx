import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

type Props = {
    email: string;
    expiresInSeconds: number;
    status?: string;
};

export default function TwoFactorChallenge({ email, expiresInSeconds, status }: Props) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        code: '',
    });

    const expirationText = useMemo(() => {
        if (expiresInSeconds <= 0) {
            return 'Code expired. Request a new code.';
        }

        const minutes = Math.floor(expiresInSeconds / 60);
        const seconds = expiresInSeconds % 60;

        if (minutes <= 0) {
            return `Code expires in ${seconds} seconds.`;
        }

        return `Code expires in ${minutes} minute${minutes > 1 ? 's' : ''} and ${seconds} second${seconds !== 1 ? 's' : ''}.`;
    }, [expiresInSeconds]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        post(route('two-factor.challenge.verify'));
    };

    const resendCode = () => {
        clearErrors();
        post(route('two-factor.challenge.resend'));
    };

    const cancelSignIn = () => {
        post(route('two-factor.challenge.cancel'));
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-red-700 via-red-700 to-red-800">
            <Head title="Two-Factor Verification" />

            <header className="border-b border-white/15 bg-red-700/80 backdrop-blur-sm">
                <div className="mx-auto flex w-full max-w-6xl items-center px-4 py-4 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-3">
                        <img src="/logo.jpg" alt="Municipality of E.B. Magalona Logo" className="h-12 w-12 rounded border border-white/40 bg-white object-cover" />
                        <div>
                            <p className="text-lg font-bold text-white sm:text-2xl">Municipality of E.B. Magalona</p>
                            <p className="text-sm text-amber-300">GovSupply Connect Security Verification</p>
                        </div>
                    </Link>
                </div>
            </header>

            <main className="mx-auto flex w-full max-w-6xl flex-col items-center px-4 pb-10 pt-8 sm:px-6 sm:pt-12 lg:px-8">
                <section className="mt-8 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-2xl sm:px-7 sm:py-7">
                    <div className="text-center">
                        <h2 className="text-3xl font-bold text-red-700">Two-Factor Verification</h2>
                        <p className="mt-2 text-sm text-slate-600">Login is not complete until your one-time verification code is confirmed.</p>
                    </div>

                    {status && (
                        <div className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                            {status}
                        </div>
                    )}

                    <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p className="font-semibold">Verification code sent to:</p>
                        <p>{email}</p>
                        <p className="mt-2 text-xs text-slate-500">{expirationText}</p>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div>
                            <InputLabel htmlFor="code" value="6-digit verification code" />
                            <TextInput
                                id="code"
                                type="text"
                                inputMode="numeric"
                                maxLength={6}
                                value={data.code}
                                onChange={(event) => setData('code', event.target.value.replace(/\D/g, '').slice(0, 6))}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 text-center text-lg tracking-[0.25em] shadow-sm focus:border-red-500 focus:ring-red-500"
                                isFocused={true}
                            />
                            <InputError message={errors.code} className="mt-2" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? 'Verifying...' : 'Verify and Continue'}
                        </button>
                    </form>

                    <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            onClick={resendCode}
                            disabled={processing}
                            className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Resend Code
                        </button>

                        <button
                            type="button"
                            onClick={cancelSignIn}
                            disabled={processing}
                            className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-rose-300 bg-rose-50 px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Cancel Sign In
                        </button>
                    </div>
                </section>
            </main>
        </div>
    );
}
