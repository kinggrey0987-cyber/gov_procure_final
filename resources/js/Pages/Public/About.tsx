import PublicNavbar from '@/Components/Public/PublicNavbar';
import { Head } from '@inertiajs/react';

type Props = {
    canLogin?: boolean;
};

export default function About({ canLogin = true }: Props) {
    return (
        <>
            <Head title="About GovSupply Connect" />

            <div className="min-h-screen bg-slate-100">
                <PublicNavbar canLogin={canLogin} />

                <main className="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h1 className="text-2xl font-bold text-red-700">About GovSupply Connect</h1>
                        <p className="mt-3 text-sm text-slate-700">
                            GovSupply Connect is the official procurement and supply monitoring system for the Municipality of E.B. Magalona,
                            Negros Occidental. The platform is designed to improve visibility, accountability, and traceability across public procurement workflows.
                        </p>
                        <p className="mt-3 text-sm text-slate-700">
                            The public portal presents approved and published disclosure records, transparency snapshots, and downloadable files that are safe
                            for public release under configured disclosure and access controls.
                        </p>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-semibold text-slate-900">Contact Information</h2>
                        <div className="mt-3 space-y-3 text-sm text-slate-700">
                            <div>
                                <p className="font-semibold text-slate-900">General Services Office</p>
                                <p>Municipal Hall, E.B. Magalona</p>
                                <p>Negros Occidental, Philippines</p>
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900">Office Hours</p>
                                <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
                                <p>Saturday - Sunday: Closed</p>
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900">Contact Channels</p>
                                <p>Email: gso@ebmagalona.gov.ph</p>
                                <p>Phone: (034) XXX-XXXX</p>
                                <p>Procurement: procurement@ebmagalona.gov.ph</p>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}





