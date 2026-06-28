import { Link } from '@inertiajs/react';

export type DashboardAlert = {
    alerts_id: number;
    alert_type: string;
    severity: string;
    alert_message: string;
    detected_at?: string | null;
    office_name?: string | null;
    supply_request_id?: number | null;
    purchase_order_id?: number | null;
};

const severityStyles: Record<string, { border: string; bg: string; text: string; icon: string }> = {
    HIGH: { border: 'border-rose-300', bg: 'bg-rose-50', text: 'text-rose-800', icon: '🔴' },
    MEDIUM: { border: 'border-amber-300', bg: 'bg-amber-50', text: 'text-amber-800', icon: '🟡' },
    LOW: { border: 'border-blue-200', bg: 'bg-blue-50', text: 'text-blue-800', icon: '🔵' },
};

function formatAlertDate(value?: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function DashboardAlertBanner({ alerts }: { alerts: DashboardAlert[] }) {
    if (!alerts || alerts.length === 0) return null;

    return (
        <section className="space-y-2">
            <div className="flex items-center gap-2">
                <span className="relative flex h-3 w-3">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75" />
                    <span className="relative inline-flex h-3 w-3 rounded-full bg-rose-500" />
                </span>
                <h3 className="text-sm font-bold uppercase tracking-wide text-rose-700">Active Alerts ({alerts.length})</h3>
            </div>
            {alerts.map((alert) => {
                const style = severityStyles[alert.severity?.toUpperCase()] ?? severityStyles.LOW;
                return (
                    <div
                        key={alert.alerts_id}
                        className={`flex items-start gap-3 rounded-xl border ${style.border} ${style.bg} px-4 py-3 shadow-sm`}
                    >
                        <span className="mt-0.5 text-base leading-none">{style.icon}</span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={`text-xs font-bold uppercase tracking-wide ${style.text}`}>
                                    {humanizeAlertType(alert.alert_type)}
                                </span>
                                <span className="rounded-full bg-white/60 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                    {alert.severity}
                                </span>
                                {alert.detected_at ? (
                                    <span className="text-[10px] text-slate-500">{formatAlertDate(alert.detected_at)}</span>
                                ) : null}
                            </div>
                            <p className={`mt-1 text-sm leading-relaxed ${style.text}`}>{alert.alert_message}</p>
                            {alert.office_name ? (
                                <p className="mt-1 text-xs text-slate-600">Office: {alert.office_name}</p>
                            ) : null}
                        </div>
                    </div>
                );
            })}
        </section>
    );
}

function humanizeAlertType(type: string): string {
    return type
        .replace(/_/g, ' ')
        .toLowerCase()
        .replace(/\b\w/g, (c) => c.toUpperCase());
}
