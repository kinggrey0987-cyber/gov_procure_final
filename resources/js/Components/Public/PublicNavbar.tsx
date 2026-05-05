type Props = {
    canLogin: boolean;
};

export default function PublicNavbar({ canLogin }: Props) {
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
                    <a href={route('landing')} className="text-red-600">Home</a>
                    <a href={`${route('landing')}#summary`} className="hover:text-red-600">Transparency Summary</a>
                    <a href={`${route('landing')}#disclosures`} className="hover:text-red-600">Disclosures</a>
                    <a href={`${route('landing')}#reports`} className="hover:text-red-600">Reports</a>
                    <a href={route('public.how-it-works')} className="hover:text-red-600">How It Works</a>
                    <a href={route('public.about')} className="hover:text-red-600">About</a>
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
                    <a href={route('landing')} className="shrink-0 rounded-full bg-red-50 px-3 py-2 text-red-700">Home</a>
                    <a href={`${route('landing')}#summary`} className="shrink-0 rounded-full border border-slate-200 px-3 py-2">Summary</a>
                    <a href={`${route('landing')}#disclosures`} className="shrink-0 rounded-full border border-slate-200 px-3 py-2">Disclosures</a>
                    <a href={`${route('landing')}#reports`} className="shrink-0 rounded-full border border-slate-200 px-3 py-2">Reports</a>
                    <a href={route('public.how-it-works')} className="shrink-0 rounded-full border border-slate-200 px-3 py-2">How It Works</a>
                    <a href={route('public.about')} className="shrink-0 rounded-full border border-slate-200 px-3 py-2">About</a>
                </div>
            </nav>
        </header>
    );
}





