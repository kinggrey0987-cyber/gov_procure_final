type Props = {
    title: string;
    subtitle: string;
};

export default function PublicHero({ title, subtitle }: Props) {
    const baseButtonClass =
        'inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2';

    return (
        <section
            id="home"
            className="bg-gradient-to-b from-red-700 to-red-800 py-10 sm:py-12"
        >
            <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <h1 className="text-3xl font-extrabold text-white sm:text-4xl">{title}</h1>
                <p className="mx-auto mt-3 max-w-4xl text-base text-red-50 sm:text-lg">{subtitle}</p>
                <div className="mt-5 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a
                        href="#disclosures"
                        className={`${baseButtonClass} bg-white text-red-700 shadow-sm hover:bg-red-50 focus-visible:ring-white`}
                    >
                        View Published Disclosures
                    </a>
                    <a
                        href="#reports"
                        className={`${baseButtonClass} border border-red-100 text-white hover:bg-red-700 focus-visible:ring-red-100`}
                    >
                        Download Public Reports
                    </a>
                </div>
            </div>
        </section>
    );
}





