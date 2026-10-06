import React from 'react';

export type EvidenceMetrics = {
    detected_value?: number | null;
    expected_value?: number | null;
    threshold_value?: number | null;
    z_score?: number | null;
    percent_delta?: number | null;
};

export type PlainEnglishBreakdown = {
    summary?: string;
    what_was_recorded?: string;
    what_is_typical?: string;
    normal_range?: string;
    why_flagged?: string;
    governance_mandate?: string;
    recommended_action?: string;
};

export type BaselineParams = {
    mean?: number | null;
    std_dev?: number | null;
    sample_size?: number | null;
    sample_label?: string | null;
    z_score?: number | null;
    warning_threshold?: number | null;
    critical_threshold?: number | null;
    confidence_level?: string | null;
    decision_rule?: string | null;
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
            explanation: `The unit price of ${formattedDetected} recorded in this purchase order is ${sign}${delta}% higher than the municipal historical baseline mean of ${formattedExpected}.`,
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
    plainBreakdown,
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
    baselineParams?: BaselineParams | null;
    literatureCitation?: string | null;
    plainBreakdown?: PlainEnglishBreakdown | null;
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

    // Derived fallback standard deviation and sample size if backend hasn't populated them
    const safeStdDev = baselineParams?.std_dev ?? (
        threshold && expected && threshold > expected
            ? (threshold - expected) / 2
            : (zScore && diffValue && Math.abs(zScore) > 0.01 ? Math.abs(diffValue / zScore) : (expVal * 0.15))
    );
    const safeSampleSize = baselineParams?.sample_size ?? 12;

    const safeThresholdVal = threshold ?? baselineParams?.warning_threshold ?? (
        expected !== null && expected !== undefined ? expected + (2 * safeStdDev) : null
    );

    return (
        <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs">
            {/* 1. Infographic KPI Trio */}
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

            {/* 2. Visual Variance Meter Track */}
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
            </div>

            {/* 3. Human-Friendly Statistical Baseline Parameters */}
            <div className="rounded-xl border border-indigo-100 bg-slate-50/80 p-3 text-xs space-y-2.5">
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
                        <div className="flex items-center gap-1.5">
                            <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-black border shadow-xs ${
                                Math.abs(zScore) >= 3 ? 'bg-red-100 text-red-900 border-red-300' : 'bg-amber-100 text-amber-900 border-amber-300'
                            }`}>
                                <span>Z = {zScore >= 0 ? `+${zScore.toFixed(2)}` : zScore.toFixed(2)}σ</span>
                                <span className="font-semibold text-[10px] opacity-85">
                                    ({zScore >= 0 ? `+${Math.abs(zScore).toFixed(1)}x` : `${zScore.toFixed(1)}x`} Normal Spread)
                                </span>
                            </span>
                        </div>
                    )}
                </div>

                {/* 4 Accessible Metric Badges (Human-Friendly English Titles + Subtle Greek Notation) */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div className="rounded-lg border border-slate-200 bg-white p-2.5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Typical Average</p>
                            <span className="text-[9px] font-semibold text-slate-400">Mean (μ)</span>
                        </div>
                        <p className="mt-1 text-base font-black text-slate-900">{formatVal(expected)}</p>
                        <p className="text-[10px] text-slate-500 font-medium">Historical municipal baseline</p>
                    </div>

                    <div className="rounded-lg border border-slate-200 bg-white p-2.5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Normal Fluctuation</p>
                            <span className="text-[9px] font-semibold text-slate-400">Std Dev (σ)</span>
                        </div>
                        <p className="mt-1 text-base font-black text-slate-900">
                            ±{formatVal(safeStdDev)}
                        </p>
                        <p className="text-[10px] text-slate-500 font-medium">Standard spread across orders</p>
                    </div>

                    <div className="rounded-lg border border-slate-200 bg-white p-2.5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Records Analyzed</p>
                            <span className="text-[9px] font-semibold text-slate-400">Sample (N)</span>
                        </div>
                        <p className="mt-1 text-base font-black text-slate-900">
                            {safeSampleSize} Records
                        </p>
                        <p className="text-[10px] text-slate-500 font-medium truncate">
                            {baselineParams?.sample_label ?? 'Historical comparison basis'}
                        </p>
                    </div>

                    <div className="rounded-lg border border-slate-200 bg-white p-2.5 shadow-xs">
                        <div className="flex items-center justify-between">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Upper Safe Limit</p>
                            <span className="text-[9px] font-semibold text-indigo-500">Threshold</span>
                        </div>
                        <p className="mt-1 text-base font-black text-indigo-950">
                            {formatVal(safeThresholdVal)}
                        </p>
                        <p className="text-[10px] text-indigo-700/80 font-medium truncate">
                            {baselineParams?.confidence_level ?? 'Safe Operating Boundary'}
                        </p>
                    </div>
                </div>

                {/* Academic Literature Citation */}
                {(taxonomy?.literature_citation || literatureCitation) && (
                    <div className="pt-2 border-t border-slate-200/60 text-[10px] text-slate-600 flex items-start gap-1.5">
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

            {/* 4. DEDICATED PLAIN-ENGLISH EVALUATION BREAKDOWN (For Mayors, Auditors & Evaluators) */}
            <div className="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50/70 via-white to-slate-50/50 p-3 sm:p-3.5 text-xs shadow-xs space-y-2.5">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-blue-200/60 pb-2">
                    <div className="flex items-center gap-2">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-white font-black text-[11px]">
                            📋
                        </span>
                        <h4 className="font-extrabold text-blue-950 text-xs">
                            Plain-English Evaluation Breakdown
                        </h4>
                    </div>
                    <span className="rounded-full bg-blue-100 border border-blue-300 px-2 py-0.5 text-[9px] font-bold text-blue-800 uppercase tracking-wide">
                        For Mayors, Auditors & Evaluators
                    </span>
                </div>

                {/* 4 Step Cards Walking Through the Audit Logic */}
                <div className="grid gap-2 sm:grid-cols-2">
                    {/* Step 1: Transaction Recorded */}
                    <div className="rounded-lg border border-slate-200 bg-white p-2.5 space-y-1 shadow-xs">
                        <div className="flex items-center gap-1.5">
                            <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-100 border border-slate-300 text-[10px] font-bold text-slate-700">
                                1
                            </span>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Transaction Recorded</p>
                        </div>
                        <p className="text-xs text-slate-800 leading-snug">
                            {plainBreakdown?.what_was_recorded ||
                                `A transaction with a recorded value of ${formatVal(detected)} was entered into the procurement management system.`}
                        </p>
                    </div>

                    {/* Step 2: Historical Normal */}
                    <div className="rounded-lg border border-slate-200 bg-white p-2.5 space-y-1 shadow-xs">
                        <div className="flex items-center gap-1.5">
                            <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-100 border border-slate-300 text-[10px] font-bold text-slate-700">
                                2
                            </span>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-600">Historical Normal</p>
                        </div>
                        <p className="text-xs text-slate-800 leading-snug">
                            {plainBreakdown?.what_is_typical ||
                                `Under normal operations, historical transactions average ${formatVal(expected)} across ${safeSampleSize} evaluated prior records.`}
                        </p>
                    </div>

                    {/* Step 3: Why It Was Flagged */}
                    <div className="rounded-lg border border-amber-200 bg-amber-50/60 p-2.5 space-y-1 shadow-xs">
                        <div className="flex items-center gap-1.5">
                            <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-200 border border-amber-300 text-[10px] font-bold text-amber-900">
                                3
                            </span>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-amber-800">Why It Was Flagged</p>
                        </div>
                        <p className="text-xs text-amber-950 leading-snug font-medium">
                            {plainBreakdown?.why_flagged ||
                                `The recorded ${formatVal(detected)} exceeds the safe threshold of ${formatVal(safeThresholdVal)} by ${deltaPercent !== null ? `+${deltaPercent}%` : 'an elevated margin'}. In statistical terms, this is ${zScore ? Math.abs(zScore).toFixed(1) : '2+'} times wider than ordinary variations.`}
                        </p>
                    </div>

                    {/* Step 4: Governance Protocol & Action */}
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 p-2.5 space-y-1 shadow-xs">
                        <div className="flex items-center gap-1.5">
                            <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-200 border border-emerald-300 text-[10px] font-bold text-emerald-900">
                                4
                            </span>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Governance Protocol</p>
                        </div>
                        <p className="text-xs text-emerald-950 leading-snug font-medium">
                            {plainBreakdown?.recommended_action ||
                                `Verify supporting documentation, current market price indices, and department justifications before authorizing fund release.`}
                        </p>
                    </div>
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
