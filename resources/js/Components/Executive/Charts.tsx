import { PropsWithChildren } from 'react';

export type ChartPoint = {
    label: string;
    value: number;
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
