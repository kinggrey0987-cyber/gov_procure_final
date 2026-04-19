import { PropsWithChildren } from 'react';

export type ChartPoint = {
    label: string;
    value: number;
};

export type GroupedChartPoint = {
    label: string;
    first: number;
    second: number;
};

export function ExecutiveChartCard({
    title,
    subtitle,
    eyebrow,
    tone = 'default',
    children,
}: PropsWithChildren<{ title: string; subtitle?: string; eyebrow?: string; tone?: 'default' | 'critical' | 'warning' | 'neutral' }>) {
    const toneClass: Record<NonNullable<typeof tone>, { shell: string; badge: string }> = {
        default: {
            shell: 'border-red-100 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]',
            badge: 'bg-red-50 text-red-700 border-red-200',
        },
        critical: {
            shell: 'border-red-300 bg-white shadow-[0_10px_24px_rgba(185,28,28,0.18)]',
            badge: 'bg-red-100 text-red-800 border-red-300',
        },
        warning: {
            shell: 'border-amber-300 bg-white shadow-[0_10px_24px_rgba(180,83,9,0.14)]',
            badge: 'bg-amber-100 text-amber-800 border-amber-300',
        },
        neutral: {
            shell: 'border-slate-200 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.06)]',
            badge: 'bg-slate-100 text-slate-700 border-slate-300',
        },
    };

    return (
        <section className={`overflow-hidden rounded-2xl border ${toneClass[tone].shell}`}>
            <div className="border-b border-slate-200 px-6 py-5">
                {eyebrow ? (
                    <div className="mb-2 flex items-center justify-between gap-3">
                        <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${toneClass[tone].badge}`}>
                            {eyebrow}
                        </span>
                    </div>
                ) : null}
                <h3 className="text-xl font-semibold text-slate-900">{title}</h3>
                {subtitle && <p className="mt-1 text-sm text-slate-600">{subtitle}</p>}
            </div>
            <div className="p-6">{children}</div>
        </section>
    );
}

export function HorizontalBarChart({
    data,
    emptyText,
    valueFormatter,
}: {
    data: ChartPoint[];
    emptyText: string;
    valueFormatter?: (value: number) => string;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const max = Math.max(...data.map((item) => item.value), 1);

    return (
        <div className="space-y-4">
            {data.map((item) => {
                const width = `${Math.max((item.value / max) * 100, item.value > 0 ? 7 : 0)}%`;

                return (
                    <div key={item.label} className="space-y-1">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium text-slate-700">{item.label}</span>
                            <span className="font-semibold text-slate-900">{valueFormatter ? valueFormatter(item.value) : item.value}</span>
                        </div>
                        <div className="h-2.5 rounded-full bg-red-100/70">
                            <div className="h-2.5 rounded-full bg-gradient-to-r from-red-700 to-red-500" style={{ width }} />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export function GroupedComparisonChart({
    data,
    firstLabel,
    secondLabel,
    emptyText,
}: {
    data: Array<{ label: string; first: number; second: number }>;
    firstLabel: string;
    secondLabel: string;
    emptyText: string;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const max = Math.max(
        1,
        ...data.map((item) => Math.max(item.first, item.second)),
    );

    return (
        <div className="space-y-4">
            {data.map((item) => (
                <div key={item.label} className="space-y-2">
                    <p className="text-sm font-medium text-slate-700">{item.label}</p>
                    <MetricBar
                        title={firstLabel}
                        value={item.first}
                        max={max}
                        tone="bg-gradient-to-r from-red-700 to-red-500"
                    />
                    <MetricBar
                        title={secondLabel}
                        value={item.second}
                        max={max}
                        tone="bg-gradient-to-r from-slate-500 to-slate-400"
                    />
                </div>
            ))}
        </div>
    );
}

export function TrendLineChart({
    data,
    emptyText,
    valueFormatter,
}: {
    data: ChartPoint[];
    emptyText: string;
    valueFormatter?: (value: number) => string;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const width = 680;
    const height = 260;
    const paddingX = 28;
    const paddingY = 24;
    const plotWidth = width - paddingX * 2;
    const plotHeight = height - paddingY * 2;
    const max = Math.max(...data.map((point) => point.value), 1);

    const points = data.map((point, index) => {
        const x = data.length === 1
            ? width / 2
            : paddingX + (index / (data.length - 1)) * plotWidth;
        const y = paddingY + (1 - point.value / max) * plotHeight;

        return { ...point, x, y };
    });

    const polylinePoints = points.map((point) => `${point.x},${point.y}`).join(' ');

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${width} ${height}`} className="h-60 w-full min-w-[560px]">
                    <defs>
                        <linearGradient id="execTrendFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="rgba(220, 38, 38, 0.24)" />
                            <stop offset="100%" stopColor="rgba(220, 38, 38, 0.02)" />
                        </linearGradient>
                    </defs>

                    <line x1={paddingX} y1={height - paddingY} x2={width - paddingX} y2={height - paddingY} stroke="rgb(254 202 202)" strokeWidth="2" />
                    <line x1={paddingX} y1={paddingY} x2={paddingX} y2={height - paddingY} stroke="rgb(254 202 202)" strokeWidth="2" />

                    <polygon
                        points={`${paddingX},${height - paddingY} ${polylinePoints} ${width - paddingX},${height - paddingY}`}
                        fill="url(#execTrendFill)"
                    />

                    <polyline
                        points={polylinePoints}
                        fill="none"
                        stroke="rgb(185 28 28)"
                        strokeWidth="3"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />

                    {points.map((point) => (
                        <g key={point.label}>
                            <circle cx={point.x} cy={point.y} r="4.5" fill="rgb(220 38 38)" />
                            <title>{`${point.label}: ${valueFormatter ? valueFormatter(point.value) : point.value}`}</title>
                        </g>
                    ))}
                </svg>
            </div>

            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {data.map((point) => (
                    <div key={point.label} className="rounded-lg border border-red-100 bg-red-50/50 px-3 py-2 text-xs">
                        <p className="font-semibold text-slate-700">{point.label}</p>
                        <p className="mt-1 text-sm font-bold text-red-700">{valueFormatter ? valueFormatter(point.value) : point.value}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function DonutChart({
    data,
    emptyText,
    centerLabel,
}: {
    data: ChartPoint[];
    emptyText: string;
    centerLabel?: string;
}) {
    const filteredData = data.filter((row) => row.value > 0);

    if (filteredData.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const total = filteredData.reduce((sum, row) => sum + row.value, 0);
    const radius = 72;
    const strokeWidth = 24;
    const circumference = 2 * Math.PI * radius;
    const palette = [
        'rgb(185 28 28)',
        'rgb(239 68 68)',
        'rgb(251 113 133)',
        'rgb(14 116 144)',
        'rgb(245 158 11)',
        'rgb(71 85 105)',
    ];

    let runningOffset = 0;

    return (
        <div className="grid gap-6 lg:grid-cols-[220px_1fr] lg:items-center">
            <div className="mx-auto h-[220px] w-[220px]">
                <svg viewBox="0 0 220 220" className="h-full w-full">
                    <circle
                        cx="110"
                        cy="110"
                        r={radius}
                        fill="none"
                        stroke="rgb(254 226 226)"
                        strokeWidth={strokeWidth}
                    />

                    {filteredData.map((segment, index) => {
                        const segmentLength = (segment.value / total) * circumference;
                        const segmentOffset = runningOffset;
                        runningOffset += segmentLength;

                        return (
                            <circle
                                key={segment.label}
                                cx="110"
                                cy="110"
                                r={radius}
                                fill="none"
                                stroke={palette[index % palette.length]}
                                strokeWidth={strokeWidth}
                                strokeDasharray={`${segmentLength} ${circumference - segmentLength}`}
                                strokeDashoffset={-segmentOffset}
                                transform="rotate(-90 110 110)"
                                strokeLinecap="butt"
                            />
                        );
                    })}

                    <text x="110" y="105" textAnchor="middle" className="fill-slate-500 text-[10px] uppercase tracking-[0.14em]">
                        {centerLabel ?? 'Total'}
                    </text>
                    <text x="110" y="128" textAnchor="middle" className="fill-slate-900 text-[22px] font-bold">
                        {total}
                    </text>
                </svg>
            </div>

            <div className="space-y-2">
                {filteredData.map((segment, index) => {
                    const percent = total > 0 ? Math.round((segment.value / total) * 100) : 0;

                    return (
                        <div key={segment.label} className="flex items-center justify-between rounded-lg border border-red-100 bg-white px-3 py-2 text-sm">
                            <div className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: palette[index % palette.length] }} />
                                <span className="font-medium text-slate-700">{segment.label}</span>
                            </div>
                            <span className="font-semibold text-slate-900">{segment.value} ({percent}%)</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function MetricBar({
    title,
    value,
    max,
    tone,
}: {
    title: string;
    value: number;
    max: number;
    tone: string;
}) {
    const width = `${Math.max((value / max) * 100, value > 0 ? 7 : 0)}%`;

    return (
        <div className="space-y-1">
            <div className="flex items-center justify-between text-xs text-slate-500">
                <span>{title}</span>
                <span className="font-semibold text-slate-800">{value}</span>
            </div>
            <div className="h-2 rounded-full bg-red-100/60">
                <div className={`h-2 rounded-full ${tone}`} style={{ width }} />
            </div>
        </div>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed border-red-200 bg-gradient-to-br from-red-50 to-white px-4 py-10 text-center text-sm text-slate-600">
            <p className="font-medium text-red-700">No data in the current scope</p>
            <p className="mt-1">{text}</p>
        </div>
    );
}
