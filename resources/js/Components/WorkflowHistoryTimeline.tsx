import { humanizeEnum } from '@/lib/displayLabels';
import { useMemo } from 'react';

export type HistoryActor = {
    id?: number;
    name?: string | null;
    role?: string | null;
    office?: string | null;
};

export type StatusHistoryItem = {
    id: number;
    old_status?: string | null;
    new_status: string;
    remarks?: string | null;
    changed_at?: string | null;
    changed_by?: HistoryActor | null;
};

export type ApprovalHistoryItem = {
    id: number;
    status: string;
    remarks?: string | null;
    approved_at?: string | null;
    approver?: HistoryActor | null;
};

type Props = {
    statusHistory?: StatusHistoryItem[];
    approvalHistory?: ApprovalHistoryItem[];
    title?: string;
    subtitle?: string;
};

function formatTimestamp(value?: string | null): string {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function getStatusBadgeStyle(status: string): { bg: string; text: string; border: string } {
    const s = status.toUpperCase();
    if (['COMPLETED', 'RECEIVED', 'APPROVED', 'FULFILLED'].includes(s)) {
        return { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200' };
    }
    if (['RELEASED', 'PARTIALLY_RELEASED', 'PLACED'].includes(s)) {
        return { bg: 'bg-sky-50', text: 'text-sky-700', border: 'border-sky-200' };
    }
    if (['SUBMITTED', 'FOR_PROCUREMENT', 'PROCESSING', 'IN_REVIEW', 'OPEN'].includes(s)) {
        return { bg: 'bg-indigo-50', text: 'text-indigo-700', border: 'border-indigo-200' };
    }
    if (['RETURNED_FOR_REVISION', 'PENDING', 'DRAFT'].includes(s)) {
        return { bg: 'bg-amber-50', text: 'text-amber-800', border: 'border-amber-200' };
    }
    if (['REJECTED', 'CANCELLED', 'DISPUTED'].includes(s)) {
        return { bg: 'bg-rose-50', text: 'text-rose-700', border: 'border-rose-200' };
    }
    return { bg: 'bg-slate-50', text: 'text-slate-700', border: 'border-slate-200' };
}

function getRoleBadgeStyle(role?: string | null): string {
    const r = (role || '').toLowerCase();
    if (r.includes('mayor') || r.includes('executive') || r.includes('chief')) {
        return 'bg-purple-100 text-purple-800 border-purple-200';
    }
    if (r.includes('gso') || r.includes('property') || r.includes('custodian') || r.includes('inventory')) {
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    }
    if (r.includes('bac') || r.includes('procurement')) {
        return 'bg-blue-100 text-blue-800 border-blue-200';
    }
    return 'bg-slate-100 text-slate-800 border-slate-200';
}

export default function WorkflowHistoryTimeline({
    statusHistory = [],
    approvalHistory = [],
    title = 'Real-Time Workflow & Audit History',
    subtitle = 'Chronological immutable log of state transitions, accountable officers, and timestamps.',
}: Props) {
    const combinedEvents = useMemo(() => {
        const events: Array<{
            id: string;
            type: 'STATUS' | 'APPROVAL';
            timestamp: string | null;
            old_status?: string | null;
            new_status: string;
            actor_name: string;
            actor_role?: string | null;
            actor_office?: string | null;
            remarks?: string | null;
        }> = [];

        statusHistory.forEach((item) => {
            events.push({
                id: `status-${item.id}`,
                type: 'STATUS',
                timestamp: item.changed_at ?? null,
                old_status: item.old_status,
                new_status: item.new_status,
                actor_name: item.changed_by?.name || 'System Automated Workflow',
                actor_role: item.changed_by?.role,
                actor_office: item.changed_by?.office,
                remarks: item.remarks,
            });
        });

        approvalHistory.forEach((item) => {
            // Do not render empty ghost approval stubs that have no actual approver and no timestamp
            if (!item.approver && !item.approved_at) {
                return;
            }

            const roleLower = item.approver?.role?.toLowerCase() || '';
            const isExecutive = roleLower.includes('executive') || roleLower.includes('mayor');
            const isProcessing = item.status === 'PROCESSING';

            events.push({
                id: `approval-${item.id}`,
                type: 'APPROVAL',
                timestamp: item.approved_at ?? null,
                new_status: item.status,
                actor_name: item.approver?.name || (isProcessing ? 'Procurement Unit' : 'Authorized Officer'),
                actor_role: item.approver?.role || (isProcessing ? 'BAC Procurement Secretariat' : (isExecutive ? 'Executive Approver' : 'Approving Authority')),
                actor_office: item.approver?.office,
                remarks: item.remarks,
            });
        });

        // Sort descending by timestamp
        return events.sort((a, b) => {
            const timeA = a.timestamp ? new Date(a.timestamp).getTime() : 0;
            const timeB = b.timestamp ? new Date(b.timestamp).getTime() : 0;
            return timeB - timeA;
        });
    }, [statusHistory, approvalHistory]);

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4">
                <div>
                    <h4 className="text-xl font-bold text-slate-900">{title}</h4>
                    <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
                </div>
                <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                    <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                    {combinedEvents.length} Recorded Action(s)
                </span>
            </div>

            {combinedEvents.length === 0 ? (
                <div className="py-10 text-center">
                    <p className="text-sm font-medium text-slate-500">No previous status transitions recorded yet.</p>
                    <p className="mt-1 text-xs text-slate-400">This record is currently in its initial stage.</p>
                </div>
            ) : (
                <div className="relative mt-6 space-y-6 before:absolute before:bottom-3 before:left-4 before:top-3 before:w-0.5 before:bg-slate-200 sm:before:left-5">
                    {combinedEvents.map((event, idx) => {
                        const isLatest = idx === 0;
                        const badgeStyle = getStatusBadgeStyle(event.new_status);
                        const roleBadgeStyle = getRoleBadgeStyle(event.actor_role);

                        return (
                            <div key={event.id} className="relative flex items-start gap-4 sm:gap-5">
                                {/* Dot indicator */}
                                <div
                                    className={`relative z-10 flex h-8 w-8 sm:h-10 sm:w-10 flex-shrink-0 items-center justify-center rounded-full border-2 ${
                                        isLatest
                                            ? 'border-emerald-500 bg-emerald-100 text-emerald-700 ring-4 ring-emerald-50'
                                            : 'border-slate-300 bg-white text-slate-600 shadow-sm'
                                    }`}
                                >
                                    {event.type === 'APPROVAL' ? (
                                        <span className="text-xs sm:text-sm">⚖️</span>
                                    ) : isLatest ? (
                                        <span className="text-xs sm:text-sm font-bold">✓</span>
                                    ) : (
                                        <span className="text-xs sm:text-sm">●</span>
                                    )}
                                </div>

                                {/* Event Card */}
                                <div
                                    className={`min-w-0 flex-1 rounded-xl border p-4 shadow-sm transition ${
                                        isLatest
                                            ? 'border-emerald-300 bg-emerald-50/40'
                                            : 'border-slate-200 bg-white hover:border-slate-300'
                                    }`}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        {/* Status transition */}
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {event.old_status ? (
                                                <>
                                                    <span className="inline-flex rounded-md border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                                        {humanizeEnum(event.old_status)}
                                                    </span>
                                                    <span className="text-xs font-bold text-slate-400">➔</span>
                                                </>
                                            ) : null}
                                            <span
                                                className={`inline-flex rounded-md border px-2.5 py-0.5 text-xs font-bold ${badgeStyle.border} ${badgeStyle.bg} ${badgeStyle.text}`}
                                            >
                                                {event.type === 'APPROVAL'
                                                    ? (event.new_status === 'PROCESSING'
                                                        ? 'Processing'
                                                        : (event.actor_role?.toLowerCase().includes('executive') || event.actor_role?.toLowerCase().includes('mayor')
                                                            ? `Executive Approval: ${humanizeEnum(event.new_status)}`
                                                            : `Approval: ${humanizeEnum(event.new_status)}`))
                                                    : humanizeEnum(event.new_status)}
                                            </span>
                                            {isLatest ? (
                                                <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">
                                                    Current State
                                                </span>
                                            ) : null}
                                        </div>

                                        {/* Timestamp */}
                                        <div className="flex items-center gap-1 text-xs text-slate-500 font-medium whitespace-nowrap">
                                            <span>🕒</span>
                                            <span>{formatTimestamp(event.timestamp)}</span>
                                        </div>
                                    </div>

                                    {/* Accountable Officer Details */}
                                    <div className="mt-3 flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
                                        <span className="text-xs font-semibold text-slate-500">Action taken by:</span>
                                        <strong className="text-xs font-bold text-slate-900">{event.actor_name}</strong>
                                        {event.actor_role ? (
                                            <span className={`inline-flex rounded border px-2 py-0.5 text-[11px] font-semibold ${roleBadgeStyle}`}>
                                                {event.actor_role}
                                            </span>
                                        ) : null}
                                        {event.actor_office ? (
                                            <span className="text-[11px] text-slate-500 font-medium">
                                                • {event.actor_office}
                                            </span>
                                        ) : null}
                                    </div>

                                    {/* Official Remarks */}
                                    {event.remarks ? (
                                        <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50/80 p-2.5 text-xs text-slate-700">
                                            <p className="font-semibold text-slate-600 text-[10px] uppercase tracking-wider mb-0.5">Official Remarks / Note:</p>
                                            <p className="leading-relaxed italic">"{event.remarks}"</p>
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
