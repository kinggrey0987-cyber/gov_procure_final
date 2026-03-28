type Stat = {
    label: string;
    value: string;
    accent: 'red' | 'green' | 'blue' | 'amber';
};

type Props = {
    stats: Stat[];
};

const accentStyles: Record<Stat['accent'], string> = {
    red: 'bg-red-600',
    green: 'bg-green-700',
    blue: 'bg-blue-600',
    amber: 'bg-amber-500',
};

export default function PublicStatCards({ stats }: Props) {
    return (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {stats.map((stat) => (
                <article key={stat.label} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-lg text-slate-600">{stat.label}</p>
                            <p className="mt-3 text-5 font-extrabold text-slate-800 sm:text-4xl">{stat.value}</p>
                        </div>
                        <span className={`mt-1 h-14 w-14 rounded-full ${accentStyles[stat.accent]}`} />
                    </div>
                </article>
            ))}
        </section>
    );
}
