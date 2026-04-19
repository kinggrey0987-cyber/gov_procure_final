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

export function TrendGroupedBarChart({
    data,
    firstLabel,
    secondLabel,
    emptyText,
}: {
    data: GroupedChartPoint[];
    firstLabel: string;
    secondLabel: string;
    emptyText: string;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const width = Math.max(620, data.length * 70 + 120);
    const height = 230;
    const chartTop = 24;
    const chartBottom = 180;
    const plotHeight = chartBottom - chartTop;
    const max = Math.max(1, ...data.map((item) => Math.max(item.first, item.second)));
    const groupWidth = (width - 90) / data.length;
    const barWidth = Math.max(10, Math.min(20, groupWidth * 0.24));

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-600">
                <span className="inline-flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-red-600" />{firstLabel}</span>
                <span className="inline-flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-amber-500" />{secondLabel}</span>
            </div>

            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${width} ${height}`} className="h-56 w-full min-w-[520px]">
                    <line x1="50" y1={chartBottom} x2={width - 20} y2={chartBottom} stroke="rgb(203 213 225)" strokeWidth="1.5" />

                    {data.map((item, index) => {
                        const x = 58 + index * groupWidth;
                        const firstHeight = (item.first / max) * plotHeight;
                        const secondHeight = (item.second / max) * plotHeight;

                        return (
                            <g key={item.label}>
                                <rect
                                    x={x}
                                    y={chartBottom - firstHeight}
                                    width={barWidth}
                                    height={Math.max(firstHeight, item.first > 0 ? 3 : 0)}
                                    rx="3"
                                    fill="rgb(220 38 38)"
                                />
                                <rect
                                    x={x + barWidth + 6}
                                    y={chartBottom - secondHeight}
                                    width={barWidth}
                                    height={Math.max(secondHeight, item.second > 0 ? 3 : 0)}
                                    rx="3"
                                    fill="rgb(245 158 11)"
                                />
                                <text x={x + barWidth} y={chartBottom + 14} textAnchor="middle" className="fill-slate-600 text-[10px]">
                                    {item.label.length > 11 ? `${item.label.slice(0, 11)}...` : item.label}
                                </text>
                            </g>
                        );
                    })}
                </svg>
            </div>
        </div>
    );
}

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
            <div className="border-b border-slate-200 px-5 py-4">
                {eyebrow ? (
                    <div className="mb-1.5 flex items-center justify-between gap-3">
                        <span className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ${toneClass[tone].badge}`}>
                            {eyebrow}
                        </span>
                    </div>
                ) : null}
                <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
                {subtitle && <p className="mt-0.5 text-sm text-slate-600">{subtitle}</p>}
            </div>
            <div className="p-5">{children}</div>
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
        <div className="space-y-3.5">
            {data.map((item) => {
                const width = `${Math.max((item.value / max) * 100, item.value > 0 ? 7 : 0)}%`;

                return (
                    <div key={item.label} className="space-y-1">
                        <div className="flex items-center justify-between text-sm">
                            <span className="truncate pr-3 font-medium text-slate-700" title={item.label}>{item.label}</span>
                            <span className="font-semibold text-slate-900">{valueFormatter ? valueFormatter(item.value) : item.value}</span>
                        </div>
                        <div className="h-2.5 rounded-full bg-slate-200/70">
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
        <div className="space-y-3.5">
            {data.map((item) => (
                <div key={item.label} className="space-y-2">
                    <p className="truncate text-sm font-medium text-slate-700" title={item.label}>{item.label}</p>
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

    if (data.length <= 2) {
        return <CompactTrendBars data={data} valueFormatter={valueFormatter} />;
    }

    const width = 680;
    const height = 220;
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
        <div className="space-y-3">
            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${width} ${height}`} className="h-52 w-full min-w-[520px]">
                    <line x1={paddingX} y1={height - paddingY} x2={width - paddingX} y2={height - paddingY} stroke="rgb(203 213 225)" strokeWidth="1.5" />
                    <line x1={paddingX} y1={paddingY} x2={paddingX} y2={height - paddingY} stroke="rgb(203 213 225)" strokeWidth="1.5" />

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
                            <circle cx={point.x} cy={point.y} r="3.6" fill="rgb(220 38 38)" />
                            <title>{`${point.label}: ${valueFormatter ? valueFormatter(point.value) : point.value}`}</title>
                        </g>
                    ))}
                </svg>
            </div>

            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                {data.map((point) => (
                    <div key={point.label} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                        <p className="truncate font-semibold text-slate-700" title={point.label}>{point.label}</p>
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
    const palette = ['rgb(185 28 28)', 'rgb(245 158 11)', 'rgb(37 99 235)', 'rgb(22 163 74)', 'rgb(71 85 105)', 'rgb(225 29 72)'];

    let runningOffset = 0;

    return (
        <div className="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,196px)_minmax(0,1fr)] lg:items-center">
            <div className="mx-auto h-[196px] w-[196px]">
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

            <div className="min-w-0 space-y-2">
                {filteredData.map((segment, index) => {
                    const percent = total > 0 ? Math.round((segment.value / total) * 100) : 0;

                    return (
                        <div key={segment.label} className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm">
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: palette[index % palette.length] }} />
                                <span className="font-medium leading-5 text-slate-700" title={segment.label}>{segment.label}</span>
                            </div>
                            <span className="shrink-0 font-semibold text-slate-900">{segment.value} ({percent}%)</span>
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

function CompactTrendBars({
    data,
    valueFormatter,
}: {
    data: ChartPoint[];
    valueFormatter?: (value: number) => string;
}) {
    const max = Math.max(...data.map((point) => point.value), 1);

    return (
        <div className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
                {data.map((point) => {
                    const height = `${Math.max((point.value / max) * 100, point.value > 0 ? 14 : 0)}%`;

                    return (
                        <div key={point.label} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p className="truncate text-xs font-semibold uppercase tracking-wide text-slate-600" title={point.label}>{point.label}</p>
                            <div className="mt-2 flex h-28 items-end rounded-lg bg-white p-2">
                                <div className="w-full rounded-md bg-gradient-to-t from-red-700 to-red-500" style={{ height }} />
                            </div>
                            <p className="mt-2 text-sm font-bold text-red-700">{valueFormatter ? valueFormatter(point.value) : point.value}</p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
