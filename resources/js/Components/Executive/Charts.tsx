import { Fragment, PropsWithChildren } from 'react';

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

    const width = Math.max(760, data.length * 44);
    const height = 230;
    const chartTop = 22;
    const chartBottom = 185;
    const plotHeight = chartBottom - chartTop;
    const max = Math.max(1, ...data.map((item) => Math.max(item.first, item.second)));
    const paddingLeft = 45;
    const paddingRight = 15;
    const plotWidth = width - paddingLeft - paddingRight;
    const groupWidth = plotWidth / data.length;
    const barWidth = Math.max(7, Math.min(15, (groupWidth - 8) / 2));

    return (
        <div className="space-y-3 flex-1 flex flex-col justify-between">
            <div className="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-600 shrink-0">
                <span className="inline-flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-red-600" />{firstLabel}</span>
                <span className="inline-flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-amber-500" />{secondLabel}</span>
            </div>

            <div className="w-full flex-1 flex items-center justify-center pt-2 overflow-x-auto min-w-0">
                <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-auto max-h-[230px] min-w-[580px] overflow-visible">
                    <line x1={paddingLeft} y1={chartBottom} x2={width - paddingRight} y2={chartBottom} stroke="rgb(203 213 225)" strokeWidth="1.5" />

                    {data.map((item, index) => {
                        const x = paddingLeft + index * groupWidth + (groupWidth - (barWidth * 2 + 4)) / 2;
                        const firstHeight = (item.first / max) * plotHeight;
                        const secondHeight = (item.second / max) * plotHeight;
                        const labelX = x + barWidth + 2;

                        return (
                            <g key={item.label}>
                                <rect
                                    x={x}
                                    y={chartBottom - firstHeight}
                                    width={barWidth}
                                    height={Math.max(firstHeight, item.first > 0 ? 3 : 0)}
                                    rx="2"
                                    fill="rgb(220 38 38)"
                                    className="transition-opacity hover:opacity-80"
                                />
                                <rect
                                    x={x + barWidth + 4}
                                    y={chartBottom - secondHeight}
                                    width={barWidth}
                                    height={Math.max(secondHeight, item.second > 0 ? 3 : 0)}
                                    rx="2"
                                    fill="rgb(245 158 11)"
                                    className="transition-opacity hover:opacity-80"
                                />
                                <text x={labelX} y={chartBottom + 15} textAnchor="middle" className="fill-slate-600 text-[9px] font-semibold">
                                    {item.label}
                                </text>
                                <title>{`${item.label} - ${firstLabel}: ${item.first} | ${secondLabel}: ${item.second}`}</title>
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
    className = '',
    children,
}: PropsWithChildren<{
    title: string;
    subtitle?: string;
    eyebrow?: string;
    tone?: 'default' | 'critical' | 'warning' | 'neutral' | 'emerald' | 'indigo';
    action?: React.ReactNode;
    className?: string;
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
        <section className={`overflow-hidden rounded-xl border ${toneClass[tone].shell} transition hover:shadow-md h-full flex flex-col ${className}`}>
            <div className="flex flex-wrap items-center justify-between gap-1.5 border-b border-slate-100 px-3 py-1.5 shrink-0">
                <div className="min-w-0">
                    {eyebrow ? (
                        <div className="mb-0.5">
                            <span className={`inline-flex rounded-full border px-2 py-0.2 text-[9px] font-bold uppercase tracking-wider ${toneClass[tone].badge}`}>
                                {eyebrow}
                            </span>
                        </div>
                    ) : null}
                    <h3 className="text-xs font-bold text-slate-900 leading-tight truncate">{title}</h3>
                    {subtitle && <p className="text-[10px] text-slate-500 font-medium line-clamp-1">{subtitle}</p>}
                </div>
                {action ? <div className="shrink-0">{action}</div> : null}
            </div>
            <div className="p-2 sm:p-2.5 flex-1 flex flex-col min-h-0 overflow-hidden">{children}</div>
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
        <div className="py-1 flex-1 overflow-y-auto min-h-0 space-y-2.5">
            {data.map((item, index) => {
                const widthPercent = Math.max((item.value / max) * 100, item.value > 0 ? 5 : 0);
                const sharePercent = total > 0 ? Math.round((item.value / total) * 100) : 0;
                const gradientClass = getBarGradient(item.label, index);

                return (
                    <div key={item.label} className="group block">
                        <div className="flex items-center justify-between gap-2 mb-1">
                            <span className="truncate text-xs font-semibold text-slate-700 group-hover:text-slate-900 transition" title={item.label}>
                                {item.label}
                            </span>
                            <div className="text-right shrink-0 whitespace-nowrap">
                                {showPercentage && total > 0 ? (
                                    <span className="text-[10px] font-medium text-slate-500 mr-1.5">
                                        ({sharePercent}%)
                                    </span>
                                ) : null}
                                <span className="text-xs font-bold text-slate-900">
                                    {valueFormatter ? valueFormatter(item.value) : item.value.toLocaleString()}
                                </span>
                            </div>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100 block">
                            <div
                                className={`h-full rounded-full ${gradientClass} transition-all duration-500 block`}
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
    height = 150,
}: {
    data: ChartPoint[];
    emptyText: string;
    valueFormatter?: (value: number) => string;
    showPointCards?: boolean;
    height?: number;
}) {
    if (data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    if (data.length <= 2) {
        return <CompactTrendBars data={data} valueFormatter={valueFormatter} />;
    }

    const width = 680;
    const paddingX = 35;
    const paddingY = 16;
    const paddingBottom = 26;
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
        <div className="space-y-2">
            {/* Executive Quick Stats Banner */}
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50/80 px-3 py-1.5 text-xs border border-slate-200">
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

            <div className="overflow-hidden">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    style={height > 150 ? { height: `${height}px` } : undefined}
                    className={height > 150 ? 'w-full min-w-0' : 'h-32 sm:h-36 w-full min-w-0'}
                >
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
                        const targetTicks = 5;
                        const stride = data.length <= 6 ? 1 : Math.max(2, Math.ceil((data.length - 1) / (targetTicks - 1)));
                        const distanceToLast = points.length - 1 - index;
                        const shouldShowLabel =
                            data.length <= 6
                                ? true
                                : isFirst || isLast || (index % stride === 0 && distanceToLast >= Math.ceil(stride * 0.75));

                        const displayLabel = point.label.length > 15 ? `${point.label.slice(0, 13)}…` : point.label;

                        return (
                            <g key={`${point.label}-${index}`}>
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
                                        {displayLabel}
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

export type SpendPoint = {
    label: string;
    value: number;
    orderCount?: number;
    rawMonth?: string;
};

export function SpendTrendBarChart({
    data,
    emptyText = 'No expenditure data in current filter scope',
    currencySymbol = '₱',
}: {
    data: SpendPoint[];
    emptyText?: string;
    currencySymbol?: string;
}) {
    if (!data || data.length === 0) {
        return <EmptyState text={emptyText} />;
    }

    const totalSpend = data.reduce((sum, item) => sum + item.value, 0);
    const totalOrders = data.reduce((sum, item) => sum + (item.orderCount ?? 0), 0);
    const max = Math.max(...data.map((item) => item.value), 1);
    const average = totalSpend / data.length;
    const peak = [...data].sort((a, b) => b.value - a.value)[0];

    const formatShort = (val: number): string => {
        if (val >= 1_000_000) return `${currencySymbol}${(val / 1_000_000).toFixed(2)}M`;
        if (val >= 1_000) return `${currencySymbol}${(val / 1_000).toFixed(1)}k`;
        return `${currencySymbol}${val.toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;
    };

    const formatFull = (val: number): string => {
        return `${currencySymbol}${val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    const width = Math.max(760, data.length * 44);
    const height = 185;
    const paddingLeft = 48;
    const paddingRight = 16;
    const chartTop = 18;
    const chartBottom = 140;
    const plotWidth = width - paddingLeft - paddingRight;
    const plotHeight = chartBottom - chartTop;

    const groupWidth = plotWidth / data.length;
    const barWidth = Math.max(12, Math.min(26, groupWidth * 0.46));

    const ySteps = [1, 0.75, 0.5, 0.25, 0];

    return (
        <div className="space-y-2.5 flex-1 flex flex-col justify-between">
            {/* Executive Spend KPI Header */}
            <div className="grid grid-cols-2 gap-2 rounded-xl border border-red-100 bg-gradient-to-r from-red-50/70 via-white to-slate-50 p-2 text-xs shadow-xs shrink-0">
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Expenditure</p>
                    <p className="mt-0.5 text-xs font-extrabold text-red-900 leading-normal" title={formatFull(totalSpend)}>{formatFull(totalSpend)}</p>
                </div>
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Peak Period</p>
                    <p className="mt-0.5 text-xs font-extrabold text-slate-900 leading-normal" title={`${peak?.label}: ${formatFull(peak?.value ?? 0)}`}>
                        {peak?.label ?? '—'} <span className="text-[10px] font-semibold text-red-700">({formatShort(peak?.value ?? 0)})</span>
                    </p>
                </div>
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Monthly Average</p>
                    <p className="mt-0.5 text-xs font-extrabold text-slate-900 leading-normal" title={formatFull(average)}>{formatFull(average)}</p>
                </div>
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Orders Processed</p>
                    <p className="mt-0.5 text-xs font-extrabold text-slate-900 leading-normal">{totalOrders} <span className="text-[10.5px] font-medium text-slate-500">POs</span></p>
                </div>
            </div>

            {/* SVG Bar Chart - 100% Fluid & Responsive, Zero Scrollbars */}
            <div className="w-full flex-1 flex items-center justify-center pt-1 overflow-hidden min-w-0">
                <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-auto max-h-[160px] min-w-0 overflow-visible">
                    <defs>
                        <linearGradient id="spendBarGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="rgb(220 38 38)" />
                            <stop offset="100%" stopColor="rgb(185 28 28)" />
                        </linearGradient>
                        <linearGradient id="peakBarGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="rgb(239 68 68)" />
                            <stop offset="100%" stopColor="rgb(153 27 27)" />
                        </linearGradient>
                        <filter id="spendBarShadow" x="-10%" y="-10%" width="120%" height="120%">
                            <feDropShadow dx="0" dy="2" stdDeviation="2" floodColor="rgb(185 28 28)" floodOpacity="0.2" />
                        </filter>
                    </defs>

                    {/* Y-Axis Guidelines & Labels */}
                    {ySteps.map((ratio) => {
                        const y = chartBottom - ratio * plotHeight;
                        const val = ratio * max;
                        return (
                            <g key={ratio}>
                                <line
                                    x1={paddingLeft}
                                    y1={y}
                                    x2={width - paddingRight}
                                    y2={y}
                                    stroke={ratio === 0 ? 'rgb(203 213 225)' : 'rgb(241 245 249)'}
                                    strokeWidth={ratio === 0 ? '1.5' : '1'}
                                    strokeDasharray={ratio === 0 ? undefined : '4 4'}
                                />
                                <text
                                    x={paddingLeft - 8}
                                    y={y + 3}
                                    textAnchor="end"
                                    className="fill-slate-400 text-[9px] font-semibold"
                                >
                                    {formatShort(val)}
                                </text>
                            </g>
                        );
                    })}

                    {/* Bars and Month Labels */}
                    {data.map((item, index) => {
                        const x = paddingLeft + index * groupWidth + (groupWidth - barWidth) / 2;
                        const barHeight = Math.max((item.value / max) * plotHeight, item.value > 0 ? 4 : 0);
                        const y = chartBottom - barHeight;
                        const isPeak = item.value === peak?.value && item.value > 0;

                        const barLabelStride = data.length <= 12 ? 1 : Math.max(2, Math.ceil(data.length / 8));
                        const distToLast = data.length - 1 - index;
                        const showMonthLabel =
                            data.length <= 12 ||
                            index === 0 ||
                            index === data.length - 1 ||
                            isPeak ||
                            (index % barLabelStride === 0 && distToLast >= Math.ceil(barLabelStride * 0.75));

                        return (
                            <g key={item.label} className="group cursor-pointer">
                                {/* Bar */}
                                <rect
                                    x={x}
                                    y={y}
                                    width={barWidth}
                                    height={barHeight}
                                    rx="3"
                                    fill={isPeak ? 'url(#peakBarGradient)' : 'url(#spendBarGradient)'}
                                    filter="url(#spendBarShadow)"
                                    className="transition-all duration-300 group-hover:opacity-85"
                                />

                                {/* Value label above bar - only render when there is enough horizontal room OR it is the peak bar */}
                                {barHeight > 14 && (groupWidth >= 38 || isPeak || data.length <= 12) ? (
                                    <text
                                        x={x + barWidth / 2}
                                        y={y - 5}
                                        textAnchor="middle"
                                        className={`text-[9px] ${isPeak ? 'fill-red-800 font-extrabold' : 'fill-slate-700 font-bold'}`}
                                    >
                                        {formatShort(item.value)}
                                    </text>
                                ) : null}

                                {/* Month label on X axis */}
                                {showMonthLabel ? (
                                    <text
                                        x={x + barWidth / 2}
                                        y={chartBottom + 15}
                                        textAnchor="middle"
                                        className={`text-[9px] font-semibold ${isPeak ? 'fill-red-800 font-bold' : 'fill-slate-600'}`}
                                    >
                                        {item.label}
                                    </text>
                                ) : null}

                                {/* Order Count Badge below Month (aligned with shown month ticks to avoid overlap) */}
                                {item.orderCount !== undefined && showMonthLabel ? (
                                    <text
                                        x={x + barWidth / 2}
                                        y={chartBottom + 27}
                                        textAnchor="middle"
                                        className="fill-slate-400 text-[8px] font-medium"
                                    >
                                        {item.orderCount} PO{item.orderCount === 1 ? '' : 's'}
                                    </text>
                                ) : null}

                                <title>{`${item.label}: ${formatFull(item.value)} (${item.orderCount ?? 0} orders)`}</title>
                            </g>
                        );
                    })}
                </svg>
            </div>
        </div>
    );
}






