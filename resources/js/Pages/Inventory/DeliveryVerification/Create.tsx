import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { humanizeEnum } from '@/lib/displayLabels';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    receipt_status: string;
    actual_delivery_date?: string | null;
    expected_arrival_date?: string | null;
    purchase_order?: {
        po_number?: string | null;
    } | null;
    supplier?: {
        supplier_name?: string | null;
    } | null;
    items?: Array<{
        delivery_item_id: number;
        item_name: string;
        unit?: string | null;
        expected_quantity: number;
        received_quantity: number;
        damaged_quantity: number;
        missing_quantity: number;
        accepted_quantity: number;
        item_condition: string;
        has_discrepancy: boolean;
        remarks?: string | null;
    }>;
    verification_summary?: {
        item_count: number;
        has_discrepancy: boolean;
        recommended_status: 'APPROVED' | 'REJECTED';
        message: string;
    };
};

type VerificationForm = {
    verification_date: string;
    verification_status: '' | 'APPROVED' | 'REJECTED';
    discrepancy_notes: string;
    remarks: string;
    evidence_file: File | null;
    evidence_files: File[];
};

type Props = {
    delivery: Delivery;
};

const todayDate = (): string => {
    const date = new Date();
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return offsetDate.toISOString().split('T')[0];
};

export default function DeliveryVerificationCreate({ delivery }: Props) {
    const minimumVerificationDate = delivery.actual_delivery_date ?? undefined;

    const { data, setData, post, processing, errors } = useForm<VerificationForm>({
        verification_date: todayDate(),
        verification_status: delivery.verification_summary?.recommended_status ?? '',
        discrepancy_notes: '',
        remarks: '',
        evidence_file: null,
        evidence_files: [],
    });

    const isRejected = data.verification_status === 'REJECTED';
    const hasDiscrepancy = delivery.verification_summary?.has_discrepancy ?? false;

    const handleFilesSelected = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newFiles = Array.from(e.target.files ?? []);
        const validFiles = newFiles.filter((f) => f.size <= 10 * 1024 * 1024);
        if (validFiles.length < newFiles.length) {
            alert('Some files exceed the 10MB limit and were skipped.');
        }
        const combined = [...data.evidence_files, ...validFiles];
        setData((prev) => ({
            ...prev,
            evidence_files: combined,
            evidence_file: combined[0] ?? null,
        }));
        e.target.value = '';
    };

    const handleRemoveFile = (indexToRemove: number) => {
        const updated = data.evidence_files.filter((_, idx) => idx !== indexToRemove);
        setData((prev) => ({
            ...prev,
            evidence_files: updated,
            evidence_file: updated[0] ?? null,
        }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!hasDiscrepancy && data.verification_status === 'REJECTED') {
            return;
        }
        if (data.verification_status === 'REJECTED' && !data.discrepancy_notes.trim()) {
            return;
        }
        post(route('inventory.delivery-verifications.store', delivery.delivery_id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Verify Delivery {delivery.delivery_code}</h2>}>
            <Head title={`Verify ${delivery.delivery_code}`} />

            <div className="space-y-6 py-8">
                <Link
                    href={route('inventory.deliveries.show', delivery.delivery_id)}
                    className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                >
                    Back to Delivery Details
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-4">
                        <Info label="Delivery Code" value={delivery.delivery_code} />
                        <Info label="Purchase Order" value={delivery.purchase_order?.po_number ?? '-'} />
                        <Info label="Supplier" value={delivery.supplier?.supplier_name ?? '-'} />
                        <Info label="Actual Delivery Date" value={delivery.actual_delivery_date ?? '-'} />
                    </div>
                    {delivery.verification_summary ? (
                        <div className={`mt-4 rounded-lg border px-4 py-3 text-sm ${delivery.verification_summary.has_discrepancy ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
                            <p className="font-semibold">Recommended Status: {humanizeEnum(delivery.verification_summary.recommended_status)}</p>
                            <p className="mt-1">{delivery.verification_summary.message}</p>
                        </div>
                    ) : null}
                </section>

                {delivery.items && delivery.items.length > 0 ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-1 text-sm font-semibold text-slate-900">Item Reconciliation</h3>
                        <p className="mb-3 text-sm text-slate-600">Compare purchase order quantity against actual received, damaged, and missing quantities before verification.</p>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Item</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Expected</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Received</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Damaged</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Missing</th>
                                        <th className="px-4 py-2 text-right font-semibold text-slate-700">Accepted</th>
                                        <th className="px-4 py-2 text-left font-semibold text-slate-700">Decision Signal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {delivery.items.map((item) => (
                                        <tr key={item.delivery_item_id} className={item.has_discrepancy ? 'bg-amber-50/60' : ''}>
                                            <td className="px-4 py-2 text-slate-700">{item.item_name}</td>
                                            <td className="px-4 py-2 text-right text-slate-700">{formatQty(item.expected_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right text-slate-800">{formatQty(item.received_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right text-rose-700">{formatQty(item.damaged_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right text-amber-700">{formatQty(item.missing_quantity, item.unit)}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-slate-800">{formatQty(item.accepted_quantity, item.unit)}</td>
                                            <td className="px-4 py-2">
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.has_discrepancy ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700'}`}>
                                                    {item.has_discrepancy ? 'Discrepancy' : 'Matches PO'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}

                <div className="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField label="Verification Date" required error={errors.verification_date}>
                                <input
                                    type="date"
                                    min={minimumVerificationDate}
                                    max={todayDate()}
                                    value={data.verification_date}
                                    onChange={(e) => setData('verification_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <p className="mt-1 text-xs text-slate-500">Verification date cannot be before the actual delivery date and cannot be future-dated.</p>
                            </FormField>

                            <FormField label="Verification Status" required error={errors.verification_status}>
                                <select
                                    value={data.verification_status}
                                    onChange={(e) => setData('verification_status', e.target.value as VerificationForm['verification_status'])}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                >
                                    <option value="">Select status</option>
                                    <option value="APPROVED" disabled={hasDiscrepancy}>
                                        Approved {hasDiscrepancy ? '(Disabled — Discrepancies exist)' : ''}
                                    </option>
                                    <option value="REJECTED" disabled={!hasDiscrepancy}>
                                        Rejected {!hasDiscrepancy ? '(Disabled — No discrepancies found)' : ''}
                                    </option>
                                </select>
                                <p className="mt-1 text-xs text-slate-500">
                                    {!hasDiscrepancy
                                        ? 'All items match purchase order quantities with zero damages or missing items. This delivery cannot be rejected and must be approved.'
                                        : 'Damaged or missing items were identified. Discrepancies must be rejected for Procurement Unit follow-up.'}
                                </p>
                            </FormField>
                        </div>

                        <FormField
                            label={isRejected ? 'Discrepancy Notes / Findings' : 'Inspection Notes (Optional)'}
                            required={isRejected}
                            error={errors.discrepancy_notes || errors.remarks}
                        >
                            <textarea
                                rows={4}
                                value={data.discrepancy_notes}
                                required={isRejected}
                                onChange={(e) => {
                                    setData('discrepancy_notes', e.target.value);
                                    setData('remarks', e.target.value);
                                }}
                                className={`w-full rounded-lg border px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none ${
                                    isRejected && !data.discrepancy_notes.trim() ? 'border-amber-300' : 'border-slate-300'
                                }`}
                                placeholder={isRejected ? 'Describe the discrepancies found (e.g. damaged packaging, missing units, broken seals)...' : 'Optional inspection notes regarding delivery condition...'}
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                {isRejected
                                    ? 'Required: Detail the specific damages, missing items, or physical condition observed during verification.'
                                    : 'Optional inspection notes regarding delivery condition.'}
                            </p>
                        </FormField>

                        {(isRejected || hasDiscrepancy) ? (
                            <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
                                <p className="text-sm font-semibold text-rose-800">⚠ Evidence Required for Discrepancy</p>
                                <p className="mt-1 text-xs text-rose-700">
                                    When rejecting a delivery due to discrepancies (damaged or missing items), you <strong>must</strong> attach evidence (photo of damaged goods, count sheet, etc.) before submitting the verification.
                                </p>
                            </div>
                        ) : null}

                        <FormField label={`Evidence Attachments ${isRejected ? '' : '(Optional)'}`} required={isRejected} error={errors.evidence_file}>
                            <input
                                type="file"
                                multiple
                                className={`w-full rounded-lg border px-4 py-2.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100 ${isRejected && data.evidence_files.length === 0 ? 'border-rose-300 bg-rose-50/50' : 'border-slate-300'}`}
                                onChange={handleFilesSelected}
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp"
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                {isRejected
                                    ? 'Required: Upload photo or document evidence showing the delivery discrepancy (Max 10MB per file).'
                                    : 'Optional: Attach supporting evidence if applicable (Max 10MB per file).'}
                            </p>

                            {data.evidence_files.length > 0 && (
                                <div className="mt-3 space-y-1.5">
                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                        Selected Attachments ({data.evidence_files.length}):
                                    </p>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {data.evidence_files.map((file, idx) => (
                                            <div key={idx} className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs shadow-2xs">
                                                <div className="flex items-center gap-2 min-w-0 pr-2">
                                                    <span className="text-slate-400">📎</span>
                                                    <span className="truncate font-medium text-slate-700" title={file.name}>{file.name}</span>
                                                    <span className="shrink-0 text-[10px] text-slate-400">({formatFileSize(file.size)})</span>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveFile(idx)}
                                                    className="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                                    title="Remove attachment"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </FormField>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Verifying...' : 'Verify Delivery'}
                            </button>
                            <Link href={route('inventory.deliveries.show', delivery.delivery_id)} className="rounded-lg bg-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-semibold text-slate-800">{value}</p>
        </div>
    );
}

function formatQty(value: number, unit?: string | null): string {
    return `${Number(value).toFixed(2)}${unit ? ` ${unit}` : ''}`;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function FormField({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-semibold text-slate-700">
                {label}
                {required ? <span className="ml-1 text-rose-600">*</span> : null}
            </label>
            {children}
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}





