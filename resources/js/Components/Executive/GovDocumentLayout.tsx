import React, { ReactNode } from 'react';
import RepublicSeal from './RepublicSeal';

export type GovDocumentMetadata = {
    title: string;
    formCode?: string;
    trackingCode?: string;
    fiscalYear?: string | number;
    period?: string;
    moduleScope?: string;
    preparedByName?: string;
    preparedByRole?: string;
    preparedAt?: string;
    approverName?: string;
    approverRole?: string;
};

export type GovLetterheadProps = {
    officeName?: string;
    subtitle?: string;
};

export function GovLetterhead({
    officeName = 'Office of the Municipal Mayor',
    subtitle = 'GovSupply Connect – Municipal Procurement & Transparency Management System',
}: GovLetterheadProps) {
    return (
        <div className="border-b-2 border-slate-900 pb-3">
            <div className="flex items-center justify-between gap-4">
                {/* Left Seal: Municipality of E.B. Magalona */}
                <div className="flex flex-shrink-0 items-center justify-center">
                    <img
                        src="/logo.jpg"
                        alt="Municipality of Enrique B. Magalona Seal"
                        className="h-16 w-16 rounded-full border border-slate-300 bg-white object-contain shadow-sm"
                        crossOrigin="anonymous"
                    />
                </div>

                {/* Center Heading */}
                <div className="flex-1 text-center">
                    <p className="text-[11px] font-medium uppercase tracking-wider text-slate-700">Republic of the Philippines</p>
                    <p className="text-[11px] font-medium uppercase tracking-wider text-slate-700">Province of Negros Occidental</p>
                    <h2 className="text-base font-extrabold uppercase tracking-wide text-slate-950 sm:text-lg">
                        Municipality of Enrique B. Magalona
                    </h2>
                    <p className="text-xs font-semibold uppercase tracking-wider text-red-800">{officeName}</p>
                    <p className="mt-0.5 text-[10px] text-slate-500">{subtitle}</p>
                </div>

                {/* Right Seal: Republic of the Philippines Seal */}
                <div className="flex flex-shrink-0 items-center justify-center">
                    <RepublicSeal className="h-16 w-16 shadow-sm" />
                </div>
            </div>

            {/* Official double rule separator */}
            <div className="mt-2.5 flex flex-col gap-0.5" aria-hidden="true">
                <div className="h-[2.5px] w-full bg-slate-900" />
                <div className="h-[1px] w-full bg-slate-400" />
            </div>
        </div>
    );
}

export type GovDocumentHeaderProps = {
    metadata: GovDocumentMetadata;
    isContinuation?: boolean;
    pageNumber?: number;
    totalPages?: number;
};

export function GovDocumentHeader({ metadata, isContinuation = false, pageNumber = 1, totalPages = 1 }: GovDocumentHeaderProps) {
    const formattedDate = metadata.preparedAt || new Date().toLocaleString('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    const formatRole = (role?: string) => {
        if (!role) return 'Executive Staff';
        return role.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    };

    return (
        <div className="space-y-3">
            {/* Top Form Code */}
            <div className="flex items-center justify-between text-[11px] font-medium text-slate-600">
                <span className="font-semibold text-slate-800">
                    {metadata.formCode || 'FDP Form - Executive Oversight & Procurement Management'}
                </span>
                <span>
                    Tracking No: <strong className="text-slate-900">{metadata.trackingCode || `EBM-EXEC-${metadata.fiscalYear || '2026'}-${String(pageNumber).padStart(2, '0')}`}</strong>
                </span>
            </div>

            {/* Document Title */}
            <div className="text-center">
                <h1 className="text-lg font-bold uppercase tracking-tight text-slate-950 sm:text-xl">
                    {metadata.title}
                </h1>
                {isContinuation && (
                    <p className="mt-0.5 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        (Continuation Sheet — Page {pageNumber} of {totalPages})
                    </p>
                )}
            </div>

            {/* Structured Government Metadata Grid (Standard LGU Table) */}
            <div className="overflow-hidden rounded-md border border-slate-300 bg-slate-50/50 text-[11px]">
                <div className="grid grid-cols-2 divide-x divide-slate-300 border-b border-slate-300">
                    <div className="px-3 py-1.5">
                        <span className="font-semibold text-slate-600">REGION:</span>{' '}
                        <span className="font-bold text-slate-900">REGION VI - WESTERN VISAYAS</span>
                    </div>
                    <div className="px-3 py-1.5">
                        <span className="font-semibold text-slate-600">CALENDAR YEAR / PERIOD:</span>{' '}
                        <span className="font-bold text-slate-900">{metadata.period || metadata.fiscalYear || '2026'}</span>
                    </div>
                </div>

                <div className="grid grid-cols-2 divide-x divide-slate-300 border-b border-slate-300">
                    <div className="px-3 py-1.5">
                        <span className="font-semibold text-slate-600">PROVINCE:</span>{' '}
                        <span className="font-bold text-slate-900">NEGROS OCCIDENTAL</span>
                    </div>
                    <div className="px-3 py-1.5">
                        <span className="font-semibold text-slate-600">MODULE / SCOPE:</span>{' '}
                        <span className="font-bold text-slate-900">{metadata.moduleScope || 'Executive Governance Oversight'}</span>
                    </div>
                </div>

                <div className="grid grid-cols-2 divide-x divide-slate-300">
                    <div className="px-3 py-1.5">
                        <span className="font-semibold text-slate-600">CITY / MUNICIPALITY:</span>{' '}
                        <span className="font-bold text-slate-900">MUNICIPALITY OF ENRIQUE B. MAGALONA</span>
                    </div>
                    <div className="px-3 py-1.5">
                        <span className="font-semibold text-slate-600">PREPARED BY:</span>{' '}
                        <span className="font-bold text-slate-900">
                            {metadata.preparedByName || 'System Executive'}
                        </span>
                        <span className="text-slate-600"> ({formatRole(metadata.preparedByRole)})</span>
                    </div>
                </div>

                <div className="border-t border-slate-300 bg-slate-100/70 px-3 py-1 text-[10px] text-slate-600 flex justify-between">
                    <span><strong>PREPARED AT:</strong> {formattedDate}</span>
                    <span><strong>AUTHENTICATION:</strong> System Verified Official Document</span>
                </div>
            </div>
        </div>
    );
}

export function GovAttestation() {
    return (
        <div className="rounded-md border border-slate-300 bg-slate-50 px-4 py-2.5 text-center text-xs text-slate-700 italic">
            &ldquo;We hereby certify that we have reviewed the contents and hereby attest to the veracity, completeness, and correctness of the data and information contained in this official document.&rdquo;
        </div>
    );
}

export type GovSignatoriesProps = {
    preparedByName?: string;
    preparedByRole?: string;
    preparedAt?: string;
    approverName?: string;
    approverRole?: string;
    approverOffice?: string;
};

export function GovSignatories({
    preparedByName = 'System Executive',
    preparedByRole = 'Executive Staff',
    preparedAt,
    approverName = 'HON. JOVILYN D. MAGALONA',
    approverRole = 'Municipal Mayor',
    approverOffice = 'Office of the Municipal Mayor',
}: GovSignatoriesProps) {
    const formattedDate = preparedAt || new Date().toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    const formatRole = (role?: string) => {
        if (!role) return 'Executive Staff';
        return role.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    };

    return (
        <div className="mt-6 pt-4 border-t border-slate-300">
            <div className="grid grid-cols-2 gap-8 sm:gap-16">
                {/* Prepared By (Logged-in user who generated the report) */}
                <div className="flex flex-col items-center text-center">
                    <p className="self-start text-xs font-semibold uppercase tracking-wider text-slate-700 mb-8">
                        Prepared By:
                    </p>
                    <div className="w-full max-w-[260px] border-b-2 border-slate-800 pb-1">
                        <p className="text-sm font-extrabold uppercase tracking-wide text-slate-950">
                            {preparedByName}
                        </p>
                    </div>
                    <p className="mt-1 text-xs font-medium text-slate-700">
                        {formatRole(preparedByRole)}
                    </p>
                    <p className="text-[10px] text-slate-500">Date: {formattedDate}</p>
                </div>

                {/* Approved / Noted By (Local Chief Executive) */}
                <div className="flex flex-col items-center text-center">
                    <p className="self-start text-xs font-semibold uppercase tracking-wider text-slate-700 mb-8">
                        Approved / Noted By:
                    </p>
                    <div className="w-full max-w-[260px] border-b-2 border-slate-800 pb-1">
                        <p className="text-sm font-extrabold uppercase tracking-wide text-slate-950">
                            {approverName}
                        </p>
                    </div>
                    <p className="mt-1 text-xs font-semibold text-slate-900">
                        {approverRole}
                    </p>
                    <p className="text-[10px] text-slate-600">{approverOffice}</p>
                </div>
            </div>
        </div>
    );
}

export type GovDocumentFooterProps = {
    formCode?: string;
    revision?: string;
    effectivityDate?: string;
    pageNumber?: number;
    totalPages?: number;
};

export function GovDocumentFooter({
    formCode = 'EBM-EXEC-FORM-01',
    revision = 'Rev. 01',
    effectivityDate = 'January 2026',
    pageNumber = 1,
    totalPages = 1,
}: GovDocumentFooterProps) {
    return (
        <div className="mt-6 border-t border-slate-300 pt-2 text-[10px] text-slate-500">
            <div className="flex items-center justify-between">
                <span className="font-medium text-slate-700">{formCode}</span>
                <span>{revision}</span>
                <span>Effectivity: {effectivityDate}</span>
                <span className="font-semibold text-slate-800">
                    Page {pageNumber} of {totalPages}
                </span>
            </div>
        </div>
    );
}

export type GovDocumentPageProps = {
    metadata: GovDocumentMetadata;
    pageNumber?: number;
    totalPages?: number;
    showLetterhead?: boolean;
    showSignatories?: boolean;
    showAttestation?: boolean;
    children: ReactNode;
    className?: string;
};

export function GovDocumentPage({
    metadata,
    pageNumber = 1,
    totalPages = 1,
    showLetterhead = true,
    showSignatories = false,
    showAttestation = false,
    children,
    className = '',
}: GovDocumentPageProps) {
    const isContinuation = pageNumber > 1;

    return (
        <section className={`pdf-page overflow-hidden rounded-xl border border-slate-300 bg-white p-6 shadow-sm sm:p-8 space-y-4 text-slate-900 ${className}`}>
            {/* Letterhead */}
            {showLetterhead && <GovLetterhead />}

            {/* Document Header & Metadata Table */}
            <GovDocumentHeader
                metadata={metadata}
                isContinuation={isContinuation}
                pageNumber={pageNumber}
                totalPages={totalPages}
            />

            {/* Main Content Area */}
            <div className="space-y-4 pt-1">{children}</div>

            {/* Certification / Attestation Clause */}
            {showAttestation && <GovAttestation />}

            {/* Signatory Block */}
            {showSignatories && (
                <GovSignatories
                    preparedByName={metadata.preparedByName}
                    preparedByRole={metadata.preparedByRole}
                    preparedAt={metadata.preparedAt}
                    approverName={metadata.approverName}
                    approverRole={metadata.approverRole}
                />
            )}

            {/* Footer with Form Details and Pagination */}
            <GovDocumentFooter
                formCode={metadata.formCode}
                pageNumber={pageNumber}
                totalPages={totalPages}
            />
        </section>
    );
}
