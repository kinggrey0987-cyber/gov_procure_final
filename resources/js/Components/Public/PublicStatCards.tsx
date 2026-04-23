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
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {stats.map((stat) => (
                <article key={stat.label} className="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-sm font-medium text-slate-600">{stat.label}</p>
                            <p className="mt-2 text-3xl font-bold text-slate-800">{stat.value}</p>
                        </div>
                        <span className={`mt-0.5 h-10 w-10 rounded-full ${accentStyles[stat.accent]}`} />
                    </div>
                </article>
            ))}
        </section>
    );
}
