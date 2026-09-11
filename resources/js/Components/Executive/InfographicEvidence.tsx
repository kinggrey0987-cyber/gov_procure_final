import React from 'react';

export type EvidenceMetrics = {
    detected_value?: number | null;
    expected_value?: number | null;
    threshold_value?: number | null;
    z_score?: number | null;
    percent_delta?: number | null;
};

export type AnomalyTypeInfo = {
    category: string;
    headline: string;
    unitLabel: string;
    isCurrency: boolean;
    explanation: string;
    governanceRisk: string;
    recommendedAction: string;
};

export function interpretAnomaly(
    anomalyType: string,
    detected?: number | null,
    expected?: number | null,
    threshold?: number | null,
    percentDelta?: number | null,
    itemLabel = 'Records',
): AnomalyTypeInfo {
    const type = (anomalyType || '').toUpperCase();
    const isPrice = type.includes('PRICE') || type.includes('Z_SCORE');
    const isFrequent = type.includes('FREQUENT') || type.includes('WINDOW') || type.includes('SPIKE');
    const isQuantity = type.includes('QUANTITY');
    const isPpmp = type.includes('PPMP');

    const formattedDetected = detected !== null && detected !== undefined
        ? isPrice
            ? `₱${Number(detected).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
            : `${Number(detected).toLocaleString('en-PH')} ${itemLabel}`
        : 'Unknown';

    const formattedExpected = expected !== null && expected !== undefined
        ? isPrice
            ? `₱${Number(expected).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
            : `${Number(expected).toLocaleString('en-PH', { maximumFractionDigits: 1 })} ${itemLabel}`
        : 'Benchmark';

    if (isPrice) {
        const delta = percentDelta ?? (detected && expected ? Math.round(((detected - expected) / expected) * 100) : 0);
        const sign = delta >= 0 ? '+' : '';
        return {
            category: 'Procurement Unit Price Variance',
            headline: `${formattedDetected} (${sign}${delta}% versus Historical Baseline)`,
            unitLabel: 'Price (PHP)',
            isCurrency: true,
            explanation: `The unit price of ${formattedDetected} encoded in this purchase order is ${sign}${delta}% higher than the municipal historical baseline mean of ${formattedExpected}.`,
            governanceRisk: 'Risk of budget overpricing or uncompetitive supplier quotations under RA 9184 and DBM-PS index standards.',
            recommendedAction: 'Verify supplier quotation against current DBM Price Index and recent PO contracts for the same item.',
        };
    }

    if (isFrequent) {
        const delta = percentDelta ?? (detected && expected ? Math.round(((detected - expected) / expected) * 100) : 0);
        const sign = delta >= 0 ? '+' : '';
        return {
            category: 'Requisition Frequency Surge',
            headline: `${formattedDetected} in 30-Day Window (${sign}${delta}% Surge)`,
            unitLabel: 'Requisitions',
            isCurrency: false,
            explanation: `The office submitted ${formattedDetected} within a single 30-day review period, exceeding the normal departmental baseline of ${formattedExpected}.`,
            governanceRisk: 'Frequent small-batch requests may indicate artificial splitting of requisitions to avoid procurement threshold ceilings.',
            recommendedAction: 'Review requisition consolidation rules with the office head and evaluate monthly allocation limits.',
        };
    }

    if (isQuantity) {
        const delta = percentDelta ?? (detected && expected ? Math.round(((detected - expected) / expected) * 100) : 0);
        const sign = delta >= 0 ? '+' : '';
        return {
            category: 'Unusual Requisition Quantity',
            headline: `${formattedDetected} Requested (${sign}${delta}% Above Normal)`,
            unitLabel: 'Quantity',
            isCurrency: false,
            explanation: `The requested quantity of ${formattedDetected} is significantly higher than historical departmental requisition patterns (${formattedExpected}).`,
            governanceRisk: 'Unusual quantity spikes increase the risk of inventory buildup, premature budget exhaustion, or unauthorized stocking.',
            recommendedAction: 'Verify physical inventory balance and validate justification with the end-user department.',
        };
    }

    if (isPpmp) {
        return {
            category: 'PPMP Budget / Schedule Irregularity',
            headline: `Requisition Variance Against Annual PPMP`,
            unitLabel: 'PPMP Allocation',
            isCurrency: false,
            explanation: `The requisition schedule or volume does not match the department's cleared Project Procurement Management Plan (PPMP).`,
            governanceRisk: 'Spending outside approved PPMP schedules breaches LGU budget execution guidelines.',
            recommendedAction: 'Confirm whether an approved PPMP amendment or supplemental budget allocation was officially authorized.',
        };
    }

    return {
        category: 'Governance Risk Signal',
        headline: `${formattedDetected} Flagged for Executive Oversight`,
        unitLabel: itemLabel,
        isCurrency: false,
        explanation: `The system detected an irregularity of ${formattedDetected} requiring executive review.`,
        governanceRisk: 'Operational or procurement deviation from standard municipal procedures.',
        recommendedAction: 'Review transaction supporting documents and endorse to General Services Office for validation.',
    };
}

export function InfographicVarianceMeter({
    detected,
    expected,
    threshold,
    isCurrency = false,
    unitLabel = 'Units',
    percentDelta,
}: {
    detected?: number | null;
    expected?: number | null;
    threshold?: number | null;
    isCurrency?: boolean;
    unitLabel?: string;
    percentDelta?: number | null;
}) {
    const hasDetected = detected !== null && detected !== undefined;
    const hasExpected = expected !== null && expected !== undefined;
    const detVal = detected ?? 0;
    const expVal = expected ?? 0;

    const formatVal = (val?: number | null) => {
        if (val === null || val === undefined) return 'N/A';
        if (isCurrency) {
            return `₱${Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
        return `${Number(val).toLocaleString('en-PH', { maximumFractionDigits: 2 })}`;
    };

    const deltaPercent = percentDelta !== null && percentDelta !== undefined
        ? percentDelta
        : (hasDetected && hasExpected && expVal > 0)
            ? Math.round(((detVal - expVal) / expVal) * 100)
            : null;

    const diffValue = hasDetected && hasExpected ? detVal - expVal : null;

    // Calculate scale positions (0 to 100%)
    const maxScaleValue = Math.max(detVal, expVal * 1.35, threshold ? expVal * 1.5 : 0, 1) * 1.15;
    const basePercent = Math.min(Math.max((expVal / maxScaleValue) * 100, 15), 70);
    const actualPercent = Math.min(Math.max((detVal / maxScaleValue) * 100, 15), 96);
    const thresholdPercent = threshold ? Math.min(Math.max(((expVal * 1.25) / maxScaleValue) * 100, basePercent + 10), 85) : null;

    return (
        <div className="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            {/* Infographic KPI Trio */}
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="relative overflow-hidden rounded-xl border border-red-200 bg-gradient-to-br from-red-50 to-white p-4 shadow-sm">
                    <span className="absolute right-3 top-3 h-2.5 w-2.5 rounded-full bg-red-600 animate-pulse" />
                    <p className="text-[11px] font-bold uppercase tracking-wider text-red-700">Actual Transaction</p>
                    <p className="mt-1 text-2xl font-extrabold text-red-950">{formatVal(detected)}</p>
                    <p className="mt-1 text-xs text-red-800/80 font-medium">Recorded in procurement record</p>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Normal Reference</p>
                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{formatVal(expected)}</p>
                    <p className="mt-1 text-xs text-slate-500 font-medium">Historical municipal baseline</p>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-sm">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-amber-700">Deviation / Variance</p>
                    <p className="mt-1 text-2xl font-extrabold text-amber-950">
                        {deltaPercent !== null ? `${deltaPercent >= 0 ? '+' : ''}${deltaPercent}%` : 'Elevated'}
                    </p>
                    <p className="mt-1 text-xs text-amber-800/80 font-medium">
                        {diffValue !== null
                            ? `${diffValue >= 0 ? '+' : ''}${formatVal(diffValue)} difference`
                            : 'Above allowable boundary'}
                    </p>
                </div>
            </div>

            {/* Visual Variance Meter Track */}
            <div className="space-y-3 pt-2">
                <div className="flex items-center justify-between text-xs font-semibold text-slate-600">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-emerald-500" /> Safe Baseline Zone
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-amber-500" /> Caution Boundary
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-red-600" /> Anomaly Surge Zone
                    </span>
                </div>

                {/* Meter track with gradient & pin markers */}
                <div className="relative pt-6 pb-8">
                    {/* Background Bar */}
                    <div className="h-4 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 border border-slate-200">
                        <div className="h-full w-full rounded-full bg-gradient-to-r from-emerald-500 via-amber-400 to-red-600 opacity-90" />
                    </div>

                    {/* Normal Benchmark Pin */}
                    {hasExpected && (
                        <div
                            className="absolute top-0 -translate-x-1/2 flex flex-col items-center pointer-events-none"
                            style={{ left: `${basePercent}%` }}
                        >
                            <span className="rounded-md bg-slate-900 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm whitespace-nowrap">
                                Baseline: {formatVal(expected)}
                            </span>
                            <div className="h-6 w-0.5 bg-slate-900 mt-0.5" />
                        </div>
                    )}

                    {/* Actual Value Pin */}
                    {hasDetected && (
                        <div
                            className="absolute top-1 -translate-x-1/2 flex flex-col items-center pointer-events-none z-10"
                            style={{ left: `${actualPercent}%` }}
                        >
                            <span className="rounded-md bg-red-600 px-2 py-0.5 text-[10px] font-extrabold text-white shadow-md whitespace-nowrap ring-2 ring-red-200">
                                Actual: {formatVal(detected)}
                            </span>
                            <div className="h-5 w-1 bg-red-600 rounded-full mt-0.5 shadow-sm" />
                            <div className="h-3 w-3 rounded-full bg-red-600 ring-4 ring-red-200 animate-ping" />
                        </div>
                    )}
                </div>

                {/* Plain-Language Executive Summary Callout */}
                <div className="rounded-xl border border-red-100 bg-red-50/60 p-3.5 text-xs text-red-900 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-600 text-white font-bold text-[10px]">
                            !
                        </span>
                        <span>
                            {hasDetected && hasExpected
                                ? `The recorded transaction of ${formatVal(detected)} exceeds historical baseline standards (${formatVal(expected)}) by ${deltaPercent !== null ? `+${deltaPercent}%` : 'an elevated margin'}.`
                                : 'The recorded transaction is operating outside normal procurement threshold tolerances.'}
                        </span>
                    </div>
                    {diffValue !== null && (
                        <span className="shrink-0 font-bold bg-white border border-red-200 rounded-lg px-2.5 py-1 text-red-700 ml-3">
                            {diffValue >= 0 ? '+' : ''}{formatVal(diffValue)} Variance
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

export function OperationalStockGauge({
    available,
    threshold,
    itemName = 'Inventory Item',
}: {
    available: number;
    threshold: number;
    itemName?: string;
}) {
    const safeThreshold = Math.max(threshold, 1);
    const capacityPercent = Math.min(Math.round((available / safeThreshold) * 100), 100);
    const deficitPercent = Math.max(100 - capacityPercent, 0);

    return (
        <div className="space-y-5 rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <span className="inline-flex rounded-full bg-red-100 border border-red-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-800">
                        Critical Stockout Risk
                    </span>
                    <h3 className="text-lg font-bold text-slate-900 mt-1">{itemName} Warehouse Stock Level</h3>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-1.5 text-right">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-red-700">Depletion Level</p>
                    <p className="text-base font-extrabold text-red-900">{deficitPercent}% Below Buffer</p>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl border border-red-200 bg-gradient-to-br from-red-50 to-white p-4">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-red-700">Available Stock on Hand</p>
                    <p className="mt-1 text-3xl font-black text-red-950">{available.toFixed(2)} Units</p>
                    <p className="mt-1 text-xs text-red-700">Currently in physical municipal warehouse</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Minimum Reorder Point</p>
                    <p className="mt-1 text-3xl font-black text-slate-900">{threshold.toFixed(2)} Units</p>
                    <p className="mt-1 text-xs text-slate-500">Mandatory GSO safety buffer threshold</p>
                </div>
            </div>

            <div className="space-y-2">
                <div className="flex justify-between text-xs font-semibold text-slate-600">
                    <span>Current Inventory Capacity ({capacityPercent}%)</span>
                    <span>Safety Buffer (100% = {threshold.toFixed(2)} Units)</span>
                </div>
                <div className="h-4 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 border border-slate-200">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-red-600 to-rose-500 transition-all duration-700"
                        style={{ width: `${Math.max(capacityPercent, 6)}%` }}
                    />
                </div>
            </div>

            <div className="rounded-xl border border-amber-100 bg-amber-50/70 p-3.5 text-xs text-amber-900">
                <strong>Executive Action Required:</strong> Stock level has fallen significantly below the municipal safety threshold. Endorse immediate replenishment to avoid stockout of essential supplies.
            </div>
        </div>
    );
}
