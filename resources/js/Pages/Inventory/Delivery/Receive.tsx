import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react';

type DeliveryItem = {
    delivery_item_id: number;
    expected_quantity: number;
    received_quantity: number;
    damaged_quantity?: number;
    item_condition: string;
    item?: {
        item_name: string;
        item_id: number;
        default_unit?: string;
        pieces_per_box?: number;
        sub_unit?: string;
    } | null;
};

type Delivery = {
    delivery_id: number;
    delivery_code: string;
    actual_delivery_date?: string | null;
    expected_arrival_date?: string | null;
    delivery_receipt_no?: string | null;
    sales_invoice_no?: string | null;
    sales_invoice_date?: string | null;
    sales_invoice_amount?: number | null;
    estimated_po_total?: number | null;
    remarks?: string | null;
    has_existing_dr?: boolean;
    has_existing_si?: boolean;
    purchase_order?: {
        purchase_order_id: number;
        po_number: string;
        placed_date?: string | null;
    } | null;
    supplier?: {
        supplier_name: string;
    } | null;
    items: DeliveryItem[];
};

type ReceiveFormData = {
    _method: string;
    actual_delivery_date: string;
    delivery_receipt_no: string;
    sales_invoice_no: string;
    sales_invoice_date: string;
    sales_invoice_amount: string | number;
    delivery_receipt_file: File | null;
    sales_invoice_file: File | null;
    remarks: string;
    items: Array<{
        delivery_item_id: number;
        received_quantity: number | string;
        damaged_quantity: number | string;
    }>;
};

type Props = {
    delivery: Delivery;
};

const todayDate = (): string => {
    const date = new Date();
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return offsetDate.toISOString().split('T')[0];
};

export default function DeliveryReceive({ delivery }: Props) {
    const toWholeQuantity = (value: string | number): number => {
        if (value === '') {
            return 0;
        }

        return Math.max(Math.trunc(Number(value) || 0), 0);
    };

    const toQuantityInput = (value: string | number): string => {
        const digits = String(value).replace(/\D/g, '');

        if (digits === '') {
            return '';
        }

        return digits.replace(/^0+(?=\d)/, '');
    };

    const [drPreview, setDrPreview] = useState<string | null>(null);
    const [siPreview, setSiPreview] = useState<string | null>(null);

    const initialDeliveryDate = delivery.actual_delivery_date || todayDate();

    const { data, setData, post, processing, errors, transform } = useForm<ReceiveFormData>({
        _method: 'PUT',
        actual_delivery_date: initialDeliveryDate,
        delivery_receipt_no: delivery.delivery_receipt_no || '',
        sales_invoice_no: delivery.sales_invoice_no || '',
        sales_invoice_date: delivery.sales_invoice_date || initialDeliveryDate,
        sales_invoice_amount: delivery.sales_invoice_amount !== undefined && delivery.sales_invoice_amount !== null
            ? String(delivery.sales_invoice_amount)
            : (delivery.estimated_po_total ? String(delivery.estimated_po_total) : ''),
        delivery_receipt_file: null,
        sales_invoice_file: null,
        remarks: delivery.remarks || '',
        items: delivery.items.map(item => ({
            delivery_item_id: item.delivery_item_id,
            received_quantity: toQuantityInput(item.received_quantity || 0),
            damaged_quantity: toQuantityInput(item.damaged_quantity || 0),
        })),
    });

    const handleItemChange = (index: number, field: 'received_quantity' | 'damaged_quantity', value: string | number) => {
        const updatedItems = [...data.items];
        updatedItems[index][field] = toQuantityInput(value);
        setData('items', updatedItems);
    };

    const handleDrFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('delivery_receipt_file', file);
        if (file && file.type.startsWith('image/')) {
            setDrPreview(URL.createObjectURL(file));
        } else {
            setDrPreview(null);
        }
    };

    const handleSiFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('sales_invoice_file', file);
        if (file && file.type.startsWith('image/')) {
            setSiPreview(URL.createObjectURL(file));
        } else {
            setSiPreview(null);
        }
    };

    const lineWarnings = data.items.flatMap((item, index) => {
        const deliveryItem = delivery.items[index];
        const itemName = deliveryItem?.item?.item_name ?? `Item ${index + 1}`;
        const expectedQuantity = Number(deliveryItem?.expected_quantity ?? 0);
        const receivedQuantity = Number(item.received_quantity || 0);
        const damagedQuantity = Number(item.damaged_quantity || 0);
        const warnings: string[] = [];

        if (receivedQuantity > expectedQuantity) {
            warnings.push(`${itemName}: Total delivered (${receivedQuantity}) cannot exceed expected PO quantity (${expectedQuantity}).`);
        }

        if (damagedQuantity > receivedQuantity) {
            warnings.push(`${itemName}: Damaged quantity (${damagedQuantity}) cannot exceed total delivered (${receivedQuantity}).`);
        }

        if (damagedQuantity > 0 && damagedQuantity === receivedQuantity && expectedQuantity > receivedQuantity) {
            warnings.push(
                `Notice for ${itemName}: You entered Total Delivered = ${receivedQuantity} and Damaged = ${damagedQuantity}. This results in 0 Accepted units and ${expectedQuantity - receivedQuantity} Missing units. If the supplier physically brought all ${expectedQuantity} items (some good and some damaged), enter ${expectedQuantity} in Total Delivered and ${damagedQuantity} in Damaged.`
            );
        }

        return warnings;
    });

    const hasLineWarnings = lineWarnings.length > 0;

    const [fileError, setFileError] = useState<string | null>(null);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setFileError(null);

        if (!delivery.has_existing_dr && !data.delivery_receipt_file) {
            setFileError('Mandatory: Scanned copy or camera photo of the Supplier Delivery Receipt (DR) is required.');
            return;
        }

        if (!delivery.has_existing_si && !data.sales_invoice_file) {
            setFileError('Mandatory: Scanned copy or camera photo of the Supplier Sales Invoice (SI) is required.');
            return;
        }

        transform((formData) => ({
            ...formData,
            _method: 'PUT',
            items: formData.items.map((item) => ({
                ...item,
                received_quantity: toWholeQuantity(item.received_quantity),
                damaged_quantity: toWholeQuantity(item.damaged_quantity),
            })),
        }));

        post(route('inventory.deliveries.update', delivery.delivery_id), {
            forceFormData: true,
            onFinish: () => transform((formData) => formData),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Receive Delivery {delivery.delivery_code}</h2>}>
            <Head title={`Receive ${delivery.delivery_code}`} />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-6">
                    {/* Delivery Summary Banner */}
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="Delivery Code" value={delivery.delivery_code} />
                            <Info label="PO Number" value={delivery.purchase_order?.po_number ?? '-'} />
                            <Info label="Supplier" value={delivery.supplier?.supplier_name ?? '-'} />
                            <Info label="Expected Arrival" value={delivery.expected_arrival_date ?? '-'} />
                        </div>
                    </div>

                    {/* Receiving Form */}
                    <form onSubmit={submit} className="space-y-6">
                        {/* 1. Supplier Billing & Proof of Delivery (COA Audit Compliance Card) */}
                        <div className="overflow-hidden rounded-xl border border-indigo-200 bg-white shadow-sm">
                            <div className="border-b border-indigo-100 bg-indigo-50/60 px-6 py-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 className="text-base font-bold text-indigo-950 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Supplier Commercial Documents & Proof of Delivery
                                        </h3>
                                        <p className="mt-1 text-xs text-indigo-800">
                                            Mandatory under COA Circular 2023-004 and Local Government Accounting Manual for Inspection & Acceptance Report (IAR) processing.
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800 border border-indigo-200">
                                        Audit Compliance
                                    </span>
                                </div>
                            </div>

                            <div className="p-6 space-y-6">
                                {/* Number Inputs */}
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <FormField label="Supplier Delivery Receipt (DR) No." required error={errors.delivery_receipt_no}>
                                        <input
                                            type="text"
                                            value={data.delivery_receipt_no}
                                            onChange={(e) => setData('delivery_receipt_no', e.target.value)}
                                            placeholder="e.g., DR-2026-0814 or 89201"
                                            className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-900 placeholder-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                        />
                                        <p className="mt-1 text-xs text-slate-500">Official Delivery Receipt number printed on the supplier's physical delivery receipt.</p>
                                    </FormField>

                                    <FormField label="Supplier Sales Invoice (SI) No." required error={errors.sales_invoice_no}>
                                        <input
                                            type="text"
                                            value={data.sales_invoice_no}
                                            onChange={(e) => setData('sales_invoice_no', e.target.value)}
                                            placeholder="e.g., SI-2026-4402 or 44102"
                                            className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-900 placeholder-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                        />
                                        <p className="mt-1 text-xs text-slate-500">Official BIR-registered Sales Invoice number issued by the winning supplier.</p>
                                    </FormField>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <FormField label="Sales Invoice Date" error={errors.sales_invoice_date}>
                                        <input
                                            type="date"
                                            max={todayDate()}
                                            value={data.sales_invoice_date}
                                            onChange={(e) => setData('sales_invoice_date', e.target.value)}
                                            className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                        />
                                        <p className="mt-1 text-xs text-slate-500">Date indicated on the supplier's Sales Invoice.</p>
                                    </FormField>

                                    <FormField label="Billed Invoice Amount (₱)" error={errors.sales_invoice_amount}>
                                        <div className="relative">
                                            <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-slate-500">₱</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={data.sales_invoice_amount}
                                                onChange={(e) => setData('sales_invoice_amount', e.target.value)}
                                                placeholder="0.00"
                                                className="w-full rounded-lg border border-slate-300 pl-8 pr-4 py-2.5 text-sm font-semibold text-slate-900 placeholder-slate-400 focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">Total gross amount billed on the supplier's invoice.</p>
                                    </FormField>
                                </div>

                                {/* Scanned File Upload Area */}
                                <div className="border-t border-slate-200 pt-5">
                                    <div className="mb-3 flex items-center justify-between">
                                        <p className="text-xs font-bold uppercase tracking-wider text-slate-600">Scanned Attachments (Audit Readiness)</p>
                                        <span className="text-xs font-semibold text-rose-600">* DR and SI photos are mandatory for inspection compliance</span>
                                    </div>
                                    {fileError && (
                                        <div className="mb-4 rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs font-bold text-rose-700 flex items-center gap-2">
                                            <span>⚠️</span>
                                            <span>{fileError}</span>
                                        </div>
                                    )}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {/* Upload Delivery Receipt */}
                                        <div className={`rounded-xl border border-dashed p-4 transition ${!delivery.has_existing_dr && !data.delivery_receipt_file ? 'border-rose-300 bg-rose-50/30' : 'border-slate-300 bg-slate-50/70 hover:border-indigo-400'}`}>
                                            <div className="flex items-start justify-between">
                                                <div className="space-y-1">
                                                    <p className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                                        <span>Scanned Delivery Receipt (DR)</span>
                                                        {!delivery.has_existing_dr ? (
                                                            <span className="rounded bg-rose-100 border border-rose-300 px-1.5 py-0.5 text-[10px] font-black text-rose-700 uppercase">* Required</span>
                                                        ) : (
                                                            <span className="rounded bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">✓ On File</span>
                                                        )}
                                                    </p>
                                                    <p className="text-[11px] text-slate-500">Take a photo with your phone camera or browse file</p>
                                                </div>
                                                <span className="text-lg">📷</span>
                                            </div>

                                            {drPreview ? (
                                                <div className="mt-3 relative inline-block">
                                                    <img src={drPreview} alt="Receipt Preview" className="h-24 w-auto rounded border object-contain bg-white shadow-sm" />
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setData('delivery_receipt_file', null);
                                                            setDrPreview(null);
                                                        }}
                                                        className="absolute -top-1.5 -right-1.5 rounded-full bg-rose-600 text-white p-0.5 text-xs shadow hover:bg-rose-700"
                                                    >
                                                        ✕
                                                    </button>
                                                </div>
                                            ) : data.delivery_receipt_file ? (
                                                <div className="mt-2 flex items-center justify-between rounded bg-white px-2.5 py-1.5 border text-xs text-slate-700">
                                                    <span className="truncate">{data.delivery_receipt_file.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => setData('delivery_receipt_file', null)}
                                                        className="ml-2 text-rose-600 font-bold hover:underline"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>
                                            ) : null}

                                            <div className="mt-3">
                                                <input
                                                    type="file"
                                                    accept="image/*,application/pdf"
                                                    capture="environment"
                                                    onChange={handleDrFileChange}
                                                    className="w-full text-xs text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-700 cursor-pointer"
                                                />
                                            </div>
                                            {errors.delivery_receipt_file && (
                                                <p className="mt-1 text-xs text-rose-600">{errors.delivery_receipt_file}</p>
                                            )}
                                        </div>

                                        {/* Upload Sales Invoice */}
                                        <div className={`rounded-xl border border-dashed p-4 transition ${!delivery.has_existing_si && !data.sales_invoice_file ? 'border-rose-300 bg-rose-50/30' : 'border-slate-300 bg-slate-50/70 hover:border-indigo-400'}`}>
                                            <div className="flex items-start justify-between">
                                                <div className="space-y-1">
                                                    <p className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                                        <span>Scanned Sales Invoice (SI)</span>
                                                        {!delivery.has_existing_si ? (
                                                            <span className="rounded bg-rose-100 border border-rose-300 px-1.5 py-0.5 text-[10px] font-black text-rose-700 uppercase">* Required</span>
                                                        ) : (
                                                            <span className="rounded bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">✓ On File</span>
                                                        )}
                                                    </p>
                                                    <p className="text-[11px] text-slate-500">Take a photo with your phone camera or browse file</p>
                                                </div>
                                                <span className="text-lg">🧾</span>
                                            </div>

                                            {siPreview ? (
                                                <div className="mt-3 relative inline-block">
                                                    <img src={siPreview} alt="Invoice Preview" className="h-24 w-auto rounded border object-contain bg-white shadow-sm" />
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setData('sales_invoice_file', null);
                                                            setSiPreview(null);
                                                        }}
                                                        className="absolute -top-1.5 -right-1.5 rounded-full bg-rose-600 text-white p-0.5 text-xs shadow hover:bg-rose-700"
                                                    >
                                                        ✕
                                                    </button>
                                                </div>
                                            ) : data.sales_invoice_file ? (
                                                <div className="mt-2 flex items-center justify-between rounded bg-white px-2.5 py-1.5 border text-xs text-slate-700">
                                                    <span className="truncate">{data.sales_invoice_file.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => setData('sales_invoice_file', null)}
                                                        className="ml-2 text-rose-600 font-bold hover:underline"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>
                                            ) : null}

                                            <div className="mt-3">
                                                <input
                                                    type="file"
                                                    accept="image/*,application/pdf"
                                                    capture="environment"
                                                    onChange={handleSiFileChange}
                                                    className="w-full text-xs text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-700 cursor-pointer"
                                                />
                                            </div>
                                            {errors.sales_invoice_file && (
                                                <p className="mt-1 text-xs text-rose-600">{errors.sales_invoice_file}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 2. Items Table */}
                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 bg-slate-50 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-800">Physical Items Inspection</h3>
                                <p className="mt-1 text-sm text-slate-600">Count and inspect the items delivered. Item condition is automatically calculated based on damaged and missing counts.</p>
                            </div>
                            <div className="border-b border-indigo-100 bg-indigo-50/60 px-6 py-3 text-xs text-indigo-950 flex items-start gap-2.5">
                                <span className="text-base leading-none">💡</span>
                                <div className="space-y-0.5">
                                    <p className="font-bold">Dock Intake Counting Guide:</p>
                                    <p className="text-indigo-900 leading-relaxed">
                                        • <strong>Packaging Unit Policy:</strong> All counts are in whole packaging units (e.g., box, pad, ream) as contracted. Damaged units must be recorded as whole boxes for contractor warranty replacement under COA rules.<br />
                                        • <strong>Total Delivered:</strong> Gross dock count of units delivered by supplier truck (good + damaged).<br />
                                        • <strong>Damaged:</strong> Count of broken or defective units. System computes <span className="font-semibold text-emerald-800">Net Accepted</span> (<code className="rounded bg-white/80 px-1 py-0.5 font-mono text-indigo-900">Delivered - Damaged</code>) and <span className="font-semibold text-amber-800">Missing Shortage</span> (<code className="rounded bg-white/80 px-1 py-0.5 font-mono text-indigo-900">Expected - Delivered</code>).
                                    </p>
                                </div>
                            </div>
                            {hasLineWarnings ? (
                                <div className="border-b border-amber-200 bg-amber-50 px-6 py-3 text-sm font-semibold text-amber-800">
                                    <ul className="list-disc space-y-1 pl-5">
                                        {lineWarnings.map((warning) => (
                                            <li key={warning}>{warning}</li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-5 py-3 text-left font-semibold text-slate-700">Item Description</th>
                                            <th className="px-4 py-3 text-center font-semibold text-slate-700">Expected (PO)</th>
                                            <th className="px-4 py-3 text-center font-semibold text-slate-700">
                                                <div>Total Delivered</div>
                                                <div className="text-[10px] font-normal text-slate-500">Gross Dock Count</div>
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-slate-700">
                                                <div>Damaged / Defective</div>
                                                <div className="text-[10px] font-normal text-rose-500">Broken Units</div>
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-slate-700">
                                                <div>Net Accepted</div>
                                                <div className="text-[10px] font-normal text-emerald-600">To Inventory</div>
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-slate-700">
                                                <div>Missing / Shortage</div>
                                                <div className="text-[10px] font-normal text-amber-600">Undelivered</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {delivery.items.map((item, index) => {
                                            const expected = toWholeQuantity(item.expected_quantity);
                                            const received = toWholeQuantity(data.items[index]?.received_quantity || 0);
                                            const damaged = toWholeQuantity(data.items[index]?.damaged_quantity || 0);
                                            const accepted = Math.max(received - damaged, 0);
                                            const missing = Math.max(expected - received, 0);
                                            const unit = item.item?.default_unit || 'unit';
                                            const pPerBox = item.item?.pieces_per_box ?? 1;
                                            const subUnit = item.item?.sub_unit || 'pcs';

                                            return (
                                                <tr key={item.delivery_item_id} className="hover:bg-slate-50/50">
                                                    <td className="px-5 py-4 font-medium text-slate-800">
                                                        <div className="font-semibold text-slate-900">{item.item?.item_name ?? 'Unknown Item'}</div>
                                                        <div className="mt-0.5 text-xs text-slate-500 font-normal">
                                                            Unit: <strong className="text-slate-700 uppercase">{unit}</strong>
                                                            {pPerBox > 1 && (
                                                                <span className="ml-1 text-slate-500 font-medium">({pPerBox} {subUnit}/{unit})</span>
                                                            )}
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                const updatedItems = [...data.items];
                                                                updatedItems[index].received_quantity = String(expected);
                                                                updatedItems[index].damaged_quantity = '0';
                                                                setData('items', updatedItems);
                                                            }}
                                                            className="mt-1 text-[11px] font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                                        >
                                                            Pre-fill full expected ({expected} {unit})
                                                        </button>
                                                    </td>
                                                    <td className="px-4 py-4 text-center font-semibold text-slate-700">
                                                        <div>{expected}</div>
                                                        <div className="text-[10px] font-normal text-slate-500">{unit}</div>
                                                    </td>
                                                    <td className="px-4 py-4 text-center">
                                                        <div className="inline-flex items-center gap-1">
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="0"
                                                                inputMode="numeric"
                                                                value={data.items[index]?.received_quantity ?? ''}
                                                                onChange={(e) => handleItemChange(index, 'received_quantity', e.target.value)}
                                                                className="w-20 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                                            />
                                                            <span className="text-[11px] text-slate-500 font-medium">{unit}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-4 text-center">
                                                        <div className="inline-flex items-center gap-1">
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="0"
                                                                inputMode="numeric"
                                                                value={data.items[index]?.damaged_quantity ?? ''}
                                                                onChange={(e) => handleItemChange(index, 'damaged_quantity', e.target.value)}
                                                                className="w-20 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm font-bold text-rose-700 focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                                                            />
                                                            <span className="text-[11px] text-rose-600 font-medium">{unit}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-4 text-center">
                                                        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ${accepted > 0 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300' : 'bg-slate-100 text-slate-500'}`}>
                                                            {accepted} {unit}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-4 text-center">
                                                        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ${missing > 0 ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-300' : 'bg-slate-50 text-slate-400'}`}>
                                                            {missing} {unit}
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* 3. Delivery Date and Remarks */}
                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <FormField label="Actual Delivery Date" required error={errors.actual_delivery_date}>
                                <input
                                    type="date"
                                    min={delivery.purchase_order?.placed_date ?? undefined}
                                    max={todayDate()}
                                    value={data.actual_delivery_date}
                                    onChange={(e) => setData('actual_delivery_date', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <p className="mt-1 text-xs text-slate-500">Date when the delivery physically arrived at the municipal facility.</p>
                            </FormField>

                            <div className="mt-4">
                                <FormField label="Delivery Remarks" error={errors.remarks}>
                                    <textarea
                                        rows={3}
                                        value={data.remarks}
                                        onChange={(e) => setData('remarks', e.target.value)}
                                        placeholder="e.g., Items delivered in good order and condition, complete packaging..."
                                        className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                                    />
                                </FormField>
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing || hasLineWarnings}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Confirming Receipt...' : 'Confirm Delivery Receipt'}
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

function Info({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 font-semibold text-slate-800">{value}</p>
        </div>
    );
}

function FormField({
    label,
    required = false,
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
                {required ? <span className="ml-1 text-rose-600 font-bold">*</span> : null}
            </label>
            {children}
            {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}
