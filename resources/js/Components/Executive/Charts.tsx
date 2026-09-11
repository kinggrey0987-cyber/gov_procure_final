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
    action,
    children,
}: PropsWithChildren<{
    title: string;
    subtitle?: string;
    eyebrow?: string;
    tone?: 'default' | 'critical' | 'warning' | 'neutral' | 'emerald' | 'indigo';
    action?: React.ReactNode;
}>) {
    const toneClass: Record<NonNullable<typeof tone>, { shell: string; badge: string }> = {
        default: {
            shell: 'border-slate-200 bg-white shadow-sm',
            badge: 'bg-red-50 text-red-700 border-red-200',
        },
        critical: {
            shell: 'border-red-200 bg-white shadow-sm ring-1 ring-red-100',
            badge: 'bg-red-100 text-red-800 border-red-300',
        },
        warning: {
            shell: 'border-amber-200 bg-white shadow-sm ring-1 ring-amber-100',
            badge: 'bg-amber-100 text-amber-800 border-amber-300',
        },
        neutral: {
            shell: 'border-slate-200 bg-white shadow-sm',
            badge: 'bg-slate-100 text-slate-700 border-slate-300',
        },
        emerald: {
            shell: 'border-emerald-200 bg-white shadow-sm ring-1 ring-emerald-100',
            badge: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        },
        indigo: {
            shell: 'border-indigo-200 bg-white shadow-sm ring-1 ring-indigo-100',
            badge: 'bg-indigo-100 text-indigo-800 border-indigo-300',
        },
    };

    return (
        <section className={`overflow-hidden rounded-2xl border ${toneClass[tone].shell} transition hover:shadow-md`}>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    {eyebrow ? (
                        <div className="mb-1">
                            <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ${toneClass[tone].badge}`}>
                                {eyebrow}
                            </span>
                        </div>
                    ) : null}
                    <h3 className="text-base font-bold text-slate-900">{title}</h3>
                    {subtitle && <p className="mt-0.5 text-xs text-slate-500 font-medium">{subtitle}</p>}
                </div>
                {action ? <div className="flex items-center gap-2">{action}</div> : null}
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

export function HorizontalBarChart({
    data,
    emptyText,
    valueFormatter,
    palette = 'default',
    showPercentage = false,
}: {
    data: ChartPoint[];
    emptyText: string;
    valueFormatter?: (value: number) => string;
    palette?: 'default' | 'rose' | 'amber' | 'emerald' | 'indigo' | 'severity' | 'multi';
    showPercentage?: boolean;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const max = Math.max(...data.map((item) => item.value), 1);
    const total = data.reduce((sum, item) => sum + item.value, 0);

    const multiGradients = [
        'bg-gradient-to-r from-red-600 to-rose-500',
        'bg-gradient-to-r from-indigo-600 to-indigo-500',
        'bg-gradient-to-r from-sky-600 to-sky-500',
        'bg-gradient-to-r from-amber-500 to-yellow-500',
        'bg-gradient-to-r from-emerald-600 to-teal-500',
        'bg-gradient-to-r from-purple-600 to-fuchsia-500',
    ];

    const getBarGradient = (label: string, index: number): string => {
        if (palette === 'severity') {
            const upper = label.toUpperCase();
            if (upper.includes('HIGH') || upper.includes('CRITICAL')) {
                return 'bg-gradient-to-r from-rose-600 to-rose-500';
            }
            if (upper.includes('MED') || upper.includes('TRIGGERED') || upper.includes('WARN')) {
                return 'bg-gradient-to-r from-amber-500 to-amber-400';
            }
            if (upper.includes('RESOLVED') || upper.includes('APPROVED')) {
                return 'bg-gradient-to-r from-emerald-600 to-emerald-500';
            }
            return 'bg-gradient-to-r from-slate-400 to-slate-300';
        }
        if (palette === 'multi') {
            return multiGradients[index % multiGradients.length];
        }
        if (palette === 'emerald') {
            return 'bg-gradient-to-r from-emerald-600 to-emerald-500';
        }
        if (palette === 'amber') {
            return 'bg-gradient-to-r from-amber-500 to-amber-400';
        }
        if (palette === 'indigo') {
            return 'bg-gradient-to-r from-indigo-600 to-indigo-500';
        }
        return 'bg-gradient-to-r from-red-600 to-rose-500';
    };

    return (
        <div className="space-y-3 py-1">
            {data.map((item, index) => {
                const widthPercent = Math.max((item.value / max) * 100, item.value > 0 ? 5 : 0);
                const sharePercent = total > 0 ? Math.round((item.value / total) * 100) : 0;
                const gradientClass = getBarGradient(item.label, index);

                return (
                    <div key={item.label} className="group min-w-0 space-y-1.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="truncate font-semibold text-slate-700 pr-2 group-hover:text-slate-900 transition" title={item.label}>
                                {item.label}
                            </span>
                            <div className="flex items-center gap-1.5 shrink-0">
                                {showPercentage && total > 0 ? (
                                    <span className="text-[11px] font-medium text-slate-600">
                                        ({sharePercent}%)
                                    </span>
                                ) : null}
                                <span className="font-bold text-slate-900">
                                    {valueFormatter ? valueFormatter(item.value) : item.value.toLocaleString()}
                                </span>
                            </div>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                className={`h-full rounded-full ${gradientClass} transition-all duration-500`}
                                style={{ width: `${widthPercent}%` }}
                            />
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
    showPointCards = false,
}: {
    data: ChartPoint[];
    emptyText: string;
    valueFormatter?: (value: number) => string;
    showPointCards?: boolean;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    if (data.length <= 2) {
        return <CompactTrendBars data={data} valueFormatter={valueFormatter} />;
    }

    const width = 680;
    const height = 200;
    const paddingX = 35;
    const paddingY = 22;
    const paddingBottom = 32;
    const plotWidth = width - paddingX * 2;
    const plotHeight = height - paddingY - paddingBottom;
    const max = Math.max(...data.map((point) => point.value), 1);
    const total = data.reduce((sum, p) => sum + p.value, 0);
    const peak = [...data].sort((a, b) => b.value - a.value)[0];
    const latest = data[data.length - 1];

    const points = data.map((point, index) => {
        const x = data.length === 1
            ? width / 2
            : paddingX + (index / (data.length - 1)) * plotWidth;
        const y = paddingY + (1 - point.value / max) * plotHeight;

        return { ...point, x, y };
    });

    const polylinePoints = points.map((point) => `${point.x},${point.y}`).join(' ');
    const areaPolygonPoints = `${points[0].x},${height - paddingBottom} ` + points.map((p) => `${p.x},${p.y}`).join(' ') + ` ${points[points.length - 1].x},${height - paddingBottom}`;

    return (
        <div className="space-y-3">
            {/* Executive Quick Stats Banner */}
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50/80 px-3.5 py-2 text-xs border border-slate-200">
                <div className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-red-600 animate-pulse"></span>
                    <span className="text-slate-600 font-medium">Latest Period:</span>
                    <strong className="text-slate-900 font-bold">{latest?.label ?? '-'} ({valueFormatter ? valueFormatter(latest?.value ?? 0) : latest?.value ?? 0})</strong>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-slate-500">Peak: <strong className="text-red-700">{peak?.label} ({peak?.value})</strong></span>
                    <span className="text-slate-300">|</span>
                    <span className="text-slate-500">Cumulative: <strong className="text-slate-900">{total.toLocaleString()}</strong></span>
                </div>
            </div>

            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${width} ${height}`} className="h-48 w-full min-w-[500px]">
                    <defs>
                        <linearGradient id="trendAreaGradientRed" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="rgb(220 38 38)" stopOpacity="0.25" />
                            <stop offset="100%" stopColor="rgb(220 38 38)" stopOpacity="0.0" />
                        </linearGradient>
                    </defs>

                    {/* Horizontal Grid lines */}
                    <line x1={paddingX} y1={height - paddingBottom} x2={width - paddingX} y2={height - paddingBottom} stroke="rgb(226 232 240)" strokeWidth="1.5" />
                    <line x1={paddingX} y1={paddingY + plotHeight / 2} x2={width - paddingX} y2={paddingY + plotHeight / 2} stroke="rgb(241 245 249)" strokeWidth="1" strokeDasharray="4 4" />
                    <line x1={paddingX} y1={paddingY} x2={width - paddingX} y2={paddingY} stroke="rgb(241 245 249)" strokeWidth="1" strokeDasharray="4 4" />

                    {/* Area fill */}
                    <polygon
                        points={areaPolygonPoints}
                        fill="url(#trendAreaGradientRed)"
                    />

                    {/* Polyline */}
                    <polyline
                        points={polylinePoints}
                        fill="none"
                        stroke="rgb(185 28 28)"
                        strokeWidth="2.5"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />

                    {/* Data points & X Labels */}
                    {points.map((point, index) => {
                        const isFirst = index === 0;
                        const isLast = index === points.length - 1;
                        const isPeak = point.value === peak?.value && point.value > 0;
                        const shouldShowLabel = data.length <= 6 || index % 2 === 0 || isLast;

                        return (
                            <g key={point.label}>
                                {isPeak || isLast ? (
                                    <circle cx={point.x} cy={point.y} r="6" fill="rgb(254 202 202)" opacity="0.6" />
                                ) : null}
                                <circle
                                    cx={point.x}
                                    cy={point.y}
                                    r={isPeak || isLast ? '4.5' : '3'}
                                    fill={isPeak || isLast ? 'rgb(220 38 38)' : 'white'}
                                    stroke="rgb(185 28 28)"
                                    strokeWidth="2"
                                />
                                {shouldShowLabel ? (
                                    <text
                                        x={point.x}
                                        y={height - 12}
                                        textAnchor={isFirst ? 'start' : isLast ? 'end' : 'middle'}
                                        className="fill-slate-600 text-[10px] font-semibold"
                                    >
                                        {point.label}
                                    </text>
                                ) : null}
                                <title>{`${point.label}: ${valueFormatter ? valueFormatter(point.value) : point.value}`}</title>
                            </g>
                        );
                    })}
                </svg>
            </div>

            {showPointCards ? (
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 pt-2">
                    {data.map((point) => (
                        <div key={point.label} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                            <p className="truncate font-semibold text-slate-700" title={point.label}>{point.label}</p>
                            <p className="mt-1 text-sm font-bold text-red-700">{valueFormatter ? valueFormatter(point.value) : point.value}</p>
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export function DonutChart({
    data,
    emptyText,
    centerLabel,
    layout = 'auto',
    paletteType = 'default',
}: {
    data: ChartPoint[];
    emptyText: string;
    centerLabel?: string;
    layout?: 'auto' | 'col';
    paletteType?: 'default' | 'severity';
}) {
    const filteredData = data.filter((row) => row.value > 0);

    if (filteredData.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const total = filteredData.reduce((sum, row) => sum + row.value, 0);
    const radius = 72;
    const strokeWidth = 24;
    const circumference = 2 * Math.PI * radius;
    const defaultPalette = ['rgb(185 28 28)', 'rgb(245 158 11)', 'rgb(37 99 235)', 'rgb(22 163 74)', 'rgb(71 85 105)', 'rgb(225 29 72)'];

    const getColor = (label: string, index: number) => {
        if (paletteType === 'severity') {
            const upper = label.toUpperCase();
            if (upper.includes('HIGH') || upper.includes('CRITICAL')) return 'rgb(220 38 38)';
            if (upper.includes('MED') || upper.includes('TRIGGERED')) return 'rgb(245 158 11)';
            if (upper.includes('LOW')) return 'rgb(100 116 139)';
            if (upper.includes('RESOLVED') || upper.includes('APPROVED')) return 'rgb(16 185 129)';
        }
        return defaultPalette[index % defaultPalette.length];
    };

    let runningOffset = 0;

    const isColLayout = layout === 'col';

    return (
        <div className={isColLayout ? 'space-y-4' : 'grid min-w-0 gap-4 lg:grid-cols-[minmax(0,136px)_minmax(0,1fr)] lg:items-center'}>
            <div className={`mx-auto ${isColLayout ? 'h-[136px] w-[136px]' : 'h-[148px] w-[148px] lg:h-[156px] lg:w-[156px]'}`}>
                <svg viewBox="0 0 220 220" className="h-full w-full">
                    <circle
                        cx="110"
                        cy="110"
                        r={radius}
                        fill="none"
                        stroke="rgb(241 245 249)"
                        strokeWidth={strokeWidth}
                    />

                    {filteredData.map((segment, index) => {
                        const segmentLength = (segment.value / total) * circumference;
                        const segmentOffset = runningOffset;
                        runningOffset += segmentLength;
                        const color = getColor(segment.label, index);

                        return (
                            <circle
                                key={segment.label}
                                cx="110"
                                cy="110"
                                r={radius}
                                fill="none"
                                stroke={color}
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

            <div className="min-w-0 space-y-1.5">
                {filteredData.map((segment, index) => {
                    const percent = total > 0 ? Math.round((segment.value / total) * 100) : 0;
                    const color = getColor(segment.label, index);

                    return (
                        <div key={segment.label} className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs">
                            <div className="flex items-center gap-2 min-w-0 pr-2">
                                <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: color }} />
                                <span className="truncate font-semibold text-slate-700" title={segment.label}>
                                    {segment.label}
                                </span>
                            </div>
                            <span className="shrink-0 whitespace-nowrap font-bold text-slate-900">
                                {segment.value} ({percent}%)
                            </span>
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





