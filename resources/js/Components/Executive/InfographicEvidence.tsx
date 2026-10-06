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
    zScore,
    taxonomy,
    baselineParams,
    literatureCitation,
}: {
    detected?: number | null;
    expected?: number | null;
    threshold?: number | null;
    isCurrency?: boolean;
    unitLabel?: string;
    percentDelta?: number | null;
    zScore?: number | null;
    taxonomy?: {
        category?: string;
        category_title?: string;
        mathematical_model?: string;
        decision_boundary?: string;
        literature_citation?: string;
        governance_mandate?: string;
    } | null;
    baselineParams?: {
        mean?: number | null;
        std_dev?: number | null;
        sample_size?: number | null;
        z_score?: number | null;
        warning_threshold?: number | null;
        critical_threshold?: number | null;
        confidence_level?: string | null;
    } | null;
    literatureCitation?: string | null;
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
        <div className="space-y-2.5 rounded-xl border border-slate-200 bg-white p-3 sm:p-3.5 shadow-sm">
            {/* Infographic KPI Trio */}
            <div className="grid gap-2 sm:grid-cols-3">
                <div className="relative overflow-hidden rounded-lg border border-red-200 bg-gradient-to-br from-red-50 to-white p-2.5 shadow-xs">
                    <span className="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-red-600 animate-pulse" />
                    <p className="text-[10px] font-bold uppercase tracking-wider text-red-700">Actual Transaction</p>
                    <p className="mt-0.5 text-lg font-black text-red-950 leading-tight">{formatVal(detected)}</p>
                    <p className="mt-0.5 text-[10px] text-red-800/80 font-medium truncate">Recorded in procurement record</p>
                </div>

                <div className="relative overflow-hidden rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-2.5 shadow-xs">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Normal Reference</p>
                    <p className="mt-0.5 text-lg font-black text-slate-900 leading-tight">{formatVal(expected)}</p>
                    <p className="mt-0.5 text-[10px] text-slate-500 font-medium truncate">Historical municipal baseline</p>
                </div>

                <div className="relative overflow-hidden rounded-lg border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-2.5 shadow-xs">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-amber-700">Deviation / Variance</p>
                    <p className="mt-0.5 text-lg font-black text-amber-950 leading-tight">
                        {deltaPercent !== null ? `${deltaPercent >= 0 ? '+' : ''}${deltaPercent}%` : 'Elevated'}
                    </p>
                    <p className="mt-0.5 text-[10px] text-amber-800/80 font-medium truncate">
                        {diffValue !== null
                            ? `${diffValue >= 0 ? '+' : ''}${formatVal(diffValue)} difference`
                            : 'Above allowable boundary'}
                    </p>
                </div>
            </div>

            {/* Visual Variance Meter Track */}
            <div className="space-y-1.5 pt-0.5">
                <div className="flex items-center justify-between text-[10px] font-semibold text-slate-600">
                    <span className="flex items-center gap-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Safe Baseline
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500" /> Caution Boundary
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-red-600" /> Anomaly Surge
                    </span>
                </div>

                {/* Meter track with gradient & pin markers */}
                <div className="relative pt-4 pb-4">
                    {/* Background Bar */}
                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 border border-slate-200">
                        <div className="h-full w-full rounded-full bg-gradient-to-r from-emerald-500 via-amber-400 to-red-600 opacity-90" />
                    </div>

                    {/* Normal Benchmark Pin */}
                    {hasExpected && (
                        <div
                            className="absolute top-0 -translate-x-1/2 flex flex-col items-center pointer-events-none"
                            style={{ left: `${basePercent}%` }}
                        >
                            <span className="rounded bg-slate-900 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs whitespace-nowrap leading-none">
                                Baseline: {formatVal(expected)}
                            </span>
                            <div className="h-4 w-0.5 bg-slate-900 mt-0.5" />
                        </div>
                    )}

                    {/* Actual Value Pin */}
                    {hasDetected && (
                        <div
                            className="absolute top-0 -translate-x-1/2 flex flex-col items-center pointer-events-none z-10"
                            style={{ left: `${actualPercent}%` }}
                        >
                            <span className="rounded bg-red-600 px-1.5 py-0.5 text-[9px] font-extrabold text-white shadow-sm whitespace-nowrap ring-1 ring-red-200 leading-none">
                                Actual: {formatVal(detected)}
                            </span>
                            <div className="h-3.5 w-0.5 bg-red-600 mt-0.5" />
                            <div className="h-2 w-2 rounded-full bg-red-600 ring-2 ring-red-200 animate-ping" />
                        </div>
                    )}
                </div>

                {/* Plain-Language Executive Summary Callout */}
                <div className="rounded-lg border border-red-100 bg-red-50/60 px-2.5 py-1.5 text-[11px] text-red-900 flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1.5 min-w-0">
                        <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-red-600 text-white font-bold text-[9px]">
                            !
                        </span>
                        <span className="truncate">
                            {hasDetected && hasExpected
                                ? `Recorded ${formatVal(detected)} exceeds baseline (${formatVal(expected)}) by ${deltaPercent !== null ? `+${deltaPercent}%` : 'elevated margin'}.`
                                : 'Recorded transaction is operating outside normal procurement tolerances.'}
                        </span>
                    </div>
                    {diffValue !== null && (
                        <span className="shrink-0 font-bold bg-white border border-red-200 rounded px-1.5 py-0.5 text-red-700 text-[10px]">
                            {diffValue >= 0 ? '+' : ''}{formatVal(diffValue)} Variance
                        </span>
                    )}
                </div>

                {/* Statistical Model, Baseline Parameters (mu, sigma, Z, N) & Academic Literature Card */}
                {(zScore !== null && zScore !== undefined || taxonomy || baselineParams) && (
                    <div className="rounded-xl border border-indigo-100 bg-slate-50/80 p-3 text-xs space-y-2 mt-2">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                            <div className="flex items-center gap-2">
                                <span className="rounded bg-indigo-700 px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                                    {taxonomy?.category || 'STATISTICAL MODEL'}
                                </span>
                                <span className="font-bold text-slate-800 text-[11px]">
                                    {taxonomy?.category_title || 'Statistical Parameter Baseline'}
                                </span>
                            </div>
                            {zScore !== null && zScore !== undefined && (
                                <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-black border ${
                                    Math.abs(zScore) >= 3 ? 'bg-red-100 text-red-900 border-red-300' : 'bg-amber-100 text-amber-900 border-amber-300'
                                }`}>
                                    Z = {zScore >= 0 ? `+${zScore.toFixed(2)}` : zScore.toFixed(2)}σ
                                </span>
                            )}
                        </div>

                        {/* Statistical Formula Badges: mu, sigma, Z, N */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1">
                            <div className="rounded border border-slate-200 bg-white p-2">
                                <p className="text-[10px] font-semibold text-slate-500">Baseline Mean (μ)</p>
                                <p className="font-bold text-slate-900 mt-0.5">{formatVal(expected)}</p>
                            </div>
                            <div className="rounded border border-slate-200 bg-white p-2">
                                <p className="text-[10px] font-semibold text-slate-500">Std Deviation (σ)</p>
                                <p className="font-bold text-slate-900 mt-0.5">
                                    {baselineParams?.std_dev ? (isCurrency ? `₱${baselineParams.std_dev.toFixed(2)}` : baselineParams.std_dev.toFixed(2)) : 'N/A (Fixed)'}
                                </p>
                            </div>
                            <div className="rounded border border-slate-200 bg-white p-2">
                                <p className="text-[10px] font-semibold text-slate-500">Sample Size (N)</p>
                                <p className="font-bold text-slate-900 mt-0.5">
                                    {baselineParams?.sample_size ? `${baselineParams.sample_size} records` : 'Active Window'}
                                </p>
                            </div>
                            <div className="rounded border border-slate-200 bg-white p-2">
                                <p className="text-[10px] font-semibold text-slate-500">Decision Limit</p>
                                <p className="font-bold text-indigo-700 mt-0.5">
                                    {baselineParams?.confidence_level || (Math.abs(zScore || 0) >= 3 ? 'Critical 3σ' : 'Warning 2σ')}
                                </p>
                            </div>
                        </div>

                        {/* Academic Literature Citation */}
                        {(taxonomy?.literature_citation || literatureCitation) && (
                            <div className="pt-1.5 border-t border-slate-200/60 text-[10px] text-slate-600 flex items-start gap-1.5">
                                <span className="font-bold text-slate-500 shrink-0">📚 Literature Reference:</span>
                                <span className="italic leading-normal">
                                    {taxonomy?.literature_citation || literatureCitation}
                                </span>
                            </div>
                        )}
                        {taxonomy?.governance_mandate && (
                            <div className="text-[10px] text-slate-600 flex items-start gap-1.5">
                                <span className="font-bold text-indigo-700 shrink-0">🏛️ Governance Mandate:</span>
                                <span className="leading-normal">{taxonomy.governance_mandate}</span>
                            </div>
                        )}
                    </div>
                )}
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
        <div className="space-y-2.5 rounded-xl border border-amber-200 bg-white p-3 sm:p-3.5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                <div>
                    <span className="inline-flex rounded-full bg-red-100 border border-red-200 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-red-800">
                        Critical Stockout Risk
                    </span>
                    <h3 className="text-sm font-bold text-slate-900 mt-0.5">{itemName} Warehouse Stock</h3>
                </div>
                <div className="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-right">
                    <p className="text-[9px] font-bold uppercase tracking-wider text-red-700">Depletion Level</p>
                    <p className="text-xs font-extrabold text-red-900">{deficitPercent}% Below Buffer</p>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="rounded-lg border border-red-200 bg-gradient-to-br from-red-50 to-white p-2.5">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-red-700">Available Stock on Hand</p>
                    <p className="mt-0.5 text-xl font-black text-red-950">{available.toFixed(2)} Units</p>
                    <p className="text-[10px] text-red-700 truncate">In municipal warehouse</p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-2.5">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Minimum Reorder Point</p>
                    <p className="mt-0.5 text-xl font-black text-slate-900">{threshold.toFixed(2)} Units</p>
                    <p className="text-[10px] text-slate-500 truncate">Mandatory safety buffer</p>
                </div>
            </div>

            <div className="space-y-1">
                <div className="flex justify-between text-[10px] font-semibold text-slate-600">
                    <span>Capacity: {capacityPercent}%</span>
                    <span>Buffer: {threshold.toFixed(2)} Units</span>
                </div>
                <div className="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 border border-slate-200">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-red-600 to-rose-500 transition-all duration-700"
                        style={{ width: `${Math.max(capacityPercent, 6)}%` }}
                    />
                </div>
            </div>

            <div className="rounded-lg border border-amber-100 bg-amber-50/70 p-2 text-[11px] text-amber-900">
                <strong>Executive Action Required:</strong> Stock is below safety buffer. Immediate replenishment recommended.
            </div>
        </div>
    );
}
