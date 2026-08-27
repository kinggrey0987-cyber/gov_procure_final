import React from 'react';

export type SnapshotItem = {
    item?: string | null;
    quantity?: string | number | null;
    cost?: string | number | null;
};

export type TraceabilitySource = {
    id: number;
    source_role: string;
    source_label: string;
    source_type?: string;
    source_id?: number;
    snapshot?: Record<string, any>;
};

interface Props {
    sources: TraceabilitySource[];
    title?: string;
    description?: string;
}

function formatLabel(key: string): string {
    return key
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatCurrency(val: any): string {
    if (val === null || val === undefined || val === '') return '₱0.00';
    const str = String(val).trim();
    if (str.startsWith('₱') || str.startsWith('PHP')) return str;
    const num = Number(str.replace(/,/g, ''));
    if (!Number.isNaN(num)) {
        return `₱${num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
    return `₱${str}`;
}

function formatValue(key: string, value: any): React.ReactNode {
    if (value === null || value === undefined || value === '') {
        return <span className="text-slate-400 font-normal">N/A</span>;
    }

    const strVal = String(value).trim();

    if (key.toLowerCase().includes('budget') || key.toLowerCase().includes('amount') || key.toLowerCase().includes('cost')) {
        return <span className="font-bold text-red-700">{formatCurrency(strVal)}</span>;
    }

    if (key.toLowerCase().includes('status')) {
        const isSuccess = ['approved', 'completed', 'received', 'published', 'verified'].some((s) => strVal.toLowerCase().includes(s));
        return (
            <span
                className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wide ${
                    isSuccess
                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                        : 'bg-amber-50 text-amber-800 border border-amber-200'
                }`}
            >
                {formatLabel(strVal)}
            </span>
        );
    }

    if (key === 'supplier' && strVal.toLowerCase() === 'unknown supplier') {
        return <span className="text-slate-400 font-normal">N/A</span>;
    }

    return strVal;
}

export default function ProcurementTraceabilityMatrix({
    sources,
    title = 'Procurement Traceability & Implementation Matrix',
    description = 'Frozen source records, budget allocations, and itemized deliverables included in this official public disclosure.',
}: Props) {
    if (!sources || sources.length === 0) {
        return (
            <div className="rounded-2xl border border-dashed border-red-200 bg-red-50/40 p-6 text-center">
                <p className="text-sm font-semibold text-slate-700">No source traceability records attached.</p>
                <p className="mt-1 text-xs text-slate-500">This disclosure does not currently reference any frozen procurement records.</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-bold tracking-tight text-slate-900 flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-red-700"></span>
                    {title}
                </h3>
                {description && <p className="mt-1 text-xs text-slate-600 leading-relaxed">{description}</p>}
            </div>

            <div className="space-y-5">
                {sources.map((source, index) => {
                    const snapshot = source.snapshot || {};
                    const items: SnapshotItem[] = Array.isArray(snapshot.items) ? snapshot.items : [];
                    const scalarEntries = Object.entries(snapshot).filter(([k]) => k !== 'items');
                    const isPrimary = (source.source_role || '').toUpperCase().includes('PRIMARY');

                    return (
                        <div
                            key={source.id || index}
                            className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow"
                        >
                            {/* Card Top Banner */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 px-4 py-3 text-white">
                                <div className="flex items-center gap-2.5 min-w-0">
                                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-700 text-xs font-black text-white shadow-sm">
                                        {index + 1}
                                    </span>
                                    <span
                                        className={`shrink-0 rounded px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider ${
                                            isPrimary
                                                ? 'bg-red-700 text-white ring-1 ring-white/20'
                                                : 'bg-slate-700 text-slate-200'
                                        }`}
                                    >
                                        {source.source_role?.replace(/^RELATED_/, '').replaceAll('_', ' ') || 'SOURCE'}
                                    </span>
                                    <h4 className="truncate text-sm font-bold text-white tracking-wide">
                                        {source.source_label}
                                    </h4>
                                </div>
                                <span className="text-[11px] font-semibold text-slate-300">
                                    Official Sourced Record
                                </span>
                            </div>

                            {/* Metadata Specification Grid */}
                            {scalarEntries.length > 0 && (
                                <div className="border-b border-slate-100 bg-slate-50/60 p-4">
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                        {scalarEntries.map(([key, val]) => (
                                            <div
                                                key={key}
                                                className="rounded-lg border border-slate-200/80 bg-white p-2.5 shadow-xs"
                                            >
                                                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                    {formatLabel(key)}
                                                </p>
                                                <div className="mt-1 text-xs font-semibold text-slate-900">
                                                    {formatValue(key, val)}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Itemized Deliverables Table */}
                            {items.length > 0 ? (
                                <div className="p-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <p className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                            <svg className="w-3.5 h-3.5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            Itemized Deliverables & Line Items ({items.length})
                                        </p>
                                        <span className="text-[11px] text-slate-500 font-medium">Philippine Peso (PHP)</span>
                                    </div>

                                    <div className="overflow-x-auto rounded-lg border border-slate-200 shadow-xs">
                                        <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                                            <thead className="bg-slate-100 font-bold uppercase tracking-wider text-slate-700">
                                                <tr>
                                                    <th scope="col" className="px-3 py-2.5 w-12 text-center text-slate-500">#</th>
                                                    <th scope="col" className="px-3 py-2.5">Item Description & Specification</th>
                                                    <th scope="col" className="px-3 py-2.5 text-center w-36">Quantity / Unit</th>
                                                    <th scope="col" className="px-3 py-2.5 text-right w-36">Total Cost (PHP)</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 bg-white">
                                                {items.map((item, iIndex) => (
                                                    <tr key={iIndex} className={iIndex % 2 === 1 ? 'bg-slate-50/50' : 'bg-white'}>
                                                        <td className="px-3 py-2.5 text-center font-medium text-slate-400">
                                                            {iIndex + 1}
                                                        </td>
                                                        <td className="px-3 py-2.5 font-semibold text-slate-900">
                                                            {item.item || `Item ${iIndex + 1}`}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-center font-medium text-slate-700">
                                                            {item.quantity ?? '-'}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-right font-bold text-red-700">
                                                            {item.cost ? formatCurrency(item.cost) : '-'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
