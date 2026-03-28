type TrendRow = {
    label: string;
    count: number;
};

type TypeRow = {
    label: string;
    count: number;
};

type Props = {
    monthlyDisclosures: TrendRow[];
    typeBreakdown: TypeRow[];
};

const chartColors = ['#dc2626', '#16a34a', '#2563eb', '#f59e0b', '#7c3aed'];

export default function PublicInfoCards({ monthlyDisclosures, typeBreakdown }: Props) {
    const trend = monthlyDisclosures.length > 0 ? monthlyDisclosures : [{ label: 'No Data', count: 0 }];
    const maxCount = Math.max(...trend.map((row) => row.count), 1);
    const pointGap = trend.length > 1 ? 420 / (trend.length - 1) : 0;

    const points = trend.map((row, idx) => {
        const x = 70 + idx * pointGap;
        const y = 180 - (row.count / maxCount) * 120;

        return {
            x,
            y,
            label: row.label,
            count: row.count,
        };
    });

    const breakdown = typeBreakdown.slice(0, 5);
    const totalBreakdown = breakdown.reduce((sum, row) => sum + row.count, 0);

    const segments = breakdown.map((row, index) => {
        const before = breakdown.slice(0, index).reduce((sum, value) => sum + value.count, 0);
        const start = totalBreakdown > 0 ? (before / totalBreakdown) * 100 : 0;
        const end = totalBreakdown > 0 ? ((before + row.count) / totalBreakdown) * 100 : 0;

        return `${chartColors[index % chartColors.length]} ${start}% ${end}%`;
    });

    const conicGradient = segments.length > 0 ? `conic-gradient(${segments.join(',')})` : 'conic-gradient(#cbd5e1 0% 100%)';

    return (
        <section id="summary" className="grid gap-4 lg:grid-cols-2">
            <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 p-6">
                    <div className="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-red-700">
                        Public Transparency Summary
                    </div>
                    <h3 className="mt-3 text-2xl font-bold text-slate-800">Published Disclosure Trend</h3>
                    <p className="mt-2 text-lg text-slate-500">Monthly count of approved and published disclosures</p>
                </div>
                <div className="p-6">
                    <svg viewBox="0 0 560 220" className="h-56 w-full">
                        <line x1="40" y1="20" x2="40" y2="190" stroke="#94a3b8" strokeWidth="2" />
                        <line x1="40" y1="190" x2="540" y2="190" stroke="#94a3b8" strokeWidth="2" />
                        {points.map((point, idx) => (
                            <g key={idx}>
                                <line x1={point.x} y1="20" x2={point.x} y2="190" stroke="#e2e8f0" strokeDasharray="4 4" />
                                <circle cx={point.x} cy={point.y} r="6" fill="#dc2626" />
                                <text x={point.x} y="208" textAnchor="middle" className="fill-slate-500 text-[10px]">
                                    {point.label}
                                </text>
                            </g>
                        ))}
                        <polyline
                            points={points.map((point) => `${point.x},${point.y}`).join(' ')}
                            fill="none"
                            stroke="#dc2626"
                            strokeWidth="4"
                        />
                    </svg>
                    <p className="mt-3 text-sm text-slate-500">
                        Visual summaries reflect approved and published disclosure records only.
                    </p>
                </div>
            </article>

            <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 p-6">
                    <div className="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700">
                        Category Breakdown
                    </div>
                    <h3 className="mt-3 text-2xl font-bold text-slate-800">Most Requested Public Categories</h3>
                    <p className="mt-2 text-lg text-slate-500">Breakdown of published disclosures by type</p>
                </div>
                <div className="flex items-center justify-center p-6">
                    <div className="relative h-56 w-56 rounded-full" style={{ background: conicGradient }}>
                        <div className="absolute inset-8 rounded-full bg-white" />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-2 px-6 pb-6 text-sm text-slate-600 sm:grid-cols-3">
                    {breakdown.length === 0 && <p className="col-span-full text-slate-500">No published disclosure type data available yet.</p>}
                    {breakdown.map((row, index) => (
                        <p key={row.label}>
                            <span className="mr-2 inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: chartColors[index % chartColors.length] }} />
                            {row.label} ({row.count})
                        </p>
                    ))}
                </div>
            </article>
        </section>
    );
}
