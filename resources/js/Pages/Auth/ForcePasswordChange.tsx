import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import { normalizePasswordValidationMessage } from '@/lib/passwordValidationMessage';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Props = {
    email: string;
};

export default function ForcePasswordChange({ email }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const passwordError = normalizePasswordValidationMessage(errors.password);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        put(route('force-password.update'));
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-red-700 via-red-700 to-red-800">
            <Head title="Set New Password" />

            <main className="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
                <section className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-2xl sm:px-7 sm:py-7">
                    <div className="text-center">
                        <h2 className="text-3xl font-bold text-red-700">Set New Password</h2>
                        <p className="mt-2 text-sm text-slate-600">
                            For security, newly created accounts must replace the temporary password before entering protected modules.
                        </p>
                    </div>

                    <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p className="font-semibold">Account</p>
                        <p>{email}</p>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div>
                            <InputLabel htmlFor="current_password" value="Temporary Password" />
                            <PasswordInput
                                id="current_password"
                                value={data.current_password}
                                onChange={(event) => setData('current_password', event.target.value)}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                wrapperClassName="mt-2"
                                required
                            />
                            <InputError message={errors.current_password} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="password" value="New Password" />
                            <PasswordInput
                                id="password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                wrapperClassName="mt-2"
                                required
                            />
                            <InputError message={passwordError} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="password_confirmation" value="Confirm New Password" />
                            <PasswordInput
                                id="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(event) => setData('password_confirmation', event.target.value)}
                                className="mt-2 block h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                wrapperClassName="mt-2"
                                required
                            />
                        </div>

                        <p className="text-xs text-slate-500">
                            Password must be at least 12 characters and include uppercase, lowercase, number, and symbol.
                        </p>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? 'Updating...' : 'Update Password and Continue'}
                        </button>
                    </form>
                </section>
            </main>
        </div>
    );
}





