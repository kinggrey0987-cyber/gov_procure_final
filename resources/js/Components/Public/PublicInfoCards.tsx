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
        <section id="summary" className="grid gap-3 lg:grid-cols-2">
            <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-700">Disclosure Trend</h3>
                </div>
                <div className="px-4 py-3">
                    <svg viewBox="0 0 560 220" className="h-32 w-full">
                        <line x1="40" y1="20" x2="40" y2="190" stroke="#94a3b8" strokeWidth="2" />
                        <line x1="40" y1="190" x2="540" y2="190" stroke="#94a3b8" strokeWidth="2" />
                        {points.map((point, idx) => (
                            <g key={idx}>
                                <line x1={point.x} y1="20" x2={point.x} y2="190" stroke="#e2e8f0" strokeDasharray="4 4" />
                                <circle cx={point.x} cy={point.y} r="4" fill="#dc2626" />
                                <text x={point.x} y="208" textAnchor="middle" className="fill-slate-500 text-[9px]">
                                    {point.label}
                                </text>
                            </g>
                        ))}
                        <polyline
                            points={points.map((point) => `${point.x},${point.y}`).join(' ')}
                            fill="none"
                            stroke="#dc2626"
                            strokeWidth="3"
                        />
                    </svg>
                </div>
            </article>

            <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-700">Disclosure Type Mix</h3>
                </div>
                <div className="flex items-center gap-4 px-4 py-3">
                    <div className="relative h-24 w-24 shrink-0 rounded-full" style={{ background: conicGradient }}>
                        <div className="absolute inset-4 rounded-full bg-white" />
                    </div>
                    <div className="grid flex-1 grid-cols-1 gap-1 text-xs text-slate-600 sm:grid-cols-2">
                        {breakdown.length === 0 && <p className="col-span-full text-slate-500">No published disclosure type data available yet.</p>}
                        {breakdown.map((row, index) => (
                            <p key={row.label}>
                                <span className="mr-2 inline-block h-2 w-2 rounded-full" style={{ backgroundColor: chartColors[index % chartColors.length] }} />
                                {formatDisclosureType(row.label)} ({row.count})
                            </p>
                        ))}
                    </div>
                </div>
            </article>
        </section>
    );
}

function formatDisclosureType(value: string): string {
    return value
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}





