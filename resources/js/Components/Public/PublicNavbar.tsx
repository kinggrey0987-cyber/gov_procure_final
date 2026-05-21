import { usePage } from '@inertiajs/react';

type Props = {
    canLogin: boolean;
};

export default function PublicNavbar({ canLogin }: Props) {
    const { url } = usePage();
    const currentPath = normalizePath(url);
    const landingPath = normalizePath(route('landing'));
    const howItWorksPath = normalizePath(route('public.how-it-works'));
    const aboutPath = normalizePath(route('public.about'));

    const isLanding = currentPath === landingPath;
    const isHowItWorks = currentPath === howItWorksPath;
    const isAbout = currentPath === aboutPath;

    return (
        <header className="border-b border-slate-200 bg-white">
            <div className="mx-auto flex min-h-20 w-full max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:min-h-24 sm:px-6 lg:px-8">
                <a href={route('landing')} className="flex min-w-0 items-center gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-red-700 bg-white sm:h-14 sm:w-14">
                        <img
                            src="/logo.jpg"
                            alt="GovSupply Connect Logo"
                            className="h-full w-full object-cover"
                        />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-lg font-bold leading-none text-red-700 sm:text-4xl">GovSupply Connect</p>
                        <p className="mt-1 text-xs text-slate-600 sm:text-sm">Municipality of E.B. Magalona</p>
                    </div>
                </a>

                <nav className="hidden items-center gap-6 text-sm font-semibold text-slate-700 xl:flex">
                    <a href={route('landing')} className={desktopNavClass(isLanding)}>Home</a>
                    <a href={`${route('landing')}#summary`} className={desktopNavClass(false)}>Transparency Summary</a>
                    <a href={`${route('landing')}#disclosures`} className={desktopNavClass(false)}>Disclosures</a>
                    <a href={`${route('landing')}#reports`} className={desktopNavClass(false)}>Reports</a>
                    <a href={route('public.how-it-works')} className={desktopNavClass(isHowItWorks)}>How It Works</a>
                    <a href={route('public.about')} className={desktopNavClass(isAbout)}>About</a>
                    {canLogin && (
                        <a
                            href={route('login')}
                            className="inline-flex h-10 items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        >
                            Login
                        </a>
                    )}
                </nav>

                {canLogin && (
                    <a
                        href={route('login')}
                        className="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-red-700 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 sm:px-4 xl:hidden"
                    >
                        Login
                    </a>
                )}
            </div>

            <nav className="border-t border-slate-100 xl:hidden">
                <div className="flex gap-2 overflow-x-auto px-4 py-3 text-xs font-semibold text-slate-700 [scrollbar-width:none] sm:px-6">
                    <a href={route('landing')} className={mobileNavClass(isLanding)}>Home</a>
                    <a href={`${route('landing')}#summary`} className={mobileNavClass(false)}>Summary</a>
                    <a href={`${route('landing')}#disclosures`} className={mobileNavClass(false)}>Disclosures</a>
                    <a href={`${route('landing')}#reports`} className={mobileNavClass(false)}>Reports</a>
                    <a href={route('public.how-it-works')} className={mobileNavClass(isHowItWorks)}>How It Works</a>
                    <a href={route('public.about')} className={mobileNavClass(isAbout)}>About</a>
                </div>
            </nav>
        </header>
    );
}

function desktopNavClass(active: boolean): string {
    return active ? 'text-red-600' : 'hover:text-red-600';
}

function mobileNavClass(active: boolean): string {
    const base = 'shrink-0 rounded-full border px-3 py-2';

    return active ? `${base} border-red-100 bg-red-50 text-red-700` : `${base} border-slate-200`;
}

function normalizePath(value: string): string {
    try {
        return new URL(value, 'http://localhost').pathname.replace(/\/$/, '') || '/';
    } catch {
        return value.split('?')[0].split('#')[0].replace(/\/$/, '') || '/';
    }
}





