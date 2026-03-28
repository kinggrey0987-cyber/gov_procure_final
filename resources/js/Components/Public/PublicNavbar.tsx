type Props = {
    canLogin: boolean;
};

export default function PublicNavbar({ canLogin }: Props) {
    return (
        <header className="border-b border-slate-200 bg-white">
            <div className="mx-auto flex min-h-24 w-full max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="#home" className="flex items-center gap-3">
                    <div className="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border-2 border-red-700 bg-white">
                        <img
                            src="/logo.jpg"
                            alt="GovSupply Connect Logo"
                            className="h-full w-full object-cover"
                        />
                    </div>
                    <div>
                        <p className="text-4 leading-none font-bold text-red-700 sm:text-4xl">GovSupply Connect</p>
                        <p className="mt-1 text-xs text-slate-600 sm:text-sm">Municipality of E.B. Magalona</p>
                    </div>
                </a>

                <nav className="hidden items-center gap-6 text-sm font-semibold text-slate-700 xl:flex">
                    <a href="#home" className="text-red-600">Home</a>
                    <a href="#summary" className="hover:text-red-600">Transparency Summary</a>
                    <a href="#disclosures" className="hover:text-red-600">Disclosures</a>
                    <a href="#reports" className="hover:text-red-600">Reports</a>
                    <a href="#transparency" className="hover:text-red-600">How It Works</a>
                    <a href="#about" className="hover:text-red-600">About</a>
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
                        className="inline-flex h-10 items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 xl:hidden"
                    >
                        Login
                    </a>
                )}
            </div>
        </header>
    );
}
