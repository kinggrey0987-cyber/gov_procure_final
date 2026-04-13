import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';

type Props = {
    show: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    confirmToneClass: string;
    targetLabel?: string;
    remarks?: string;
    remarksRequired?: boolean;
    remarksLabel?: string;
    remarksPlaceholder?: string;
    remarksError?: string | null;
    processing?: boolean;
    onRemarksChange?: (value: string) => void;
    onCancel: () => void;
    onConfirm: () => void;
};

export default function PpmpDecisionModal({
    show,
    title,
    message,
    confirmLabel,
    confirmToneClass,
    targetLabel,
    remarks = '',
    remarksRequired = false,
    remarksLabel = 'Remarks',
    remarksPlaceholder = 'Enter remarks',
    remarksError,
    processing = false,
    onRemarksChange,
    onCancel,
    onConfirm,
}: Props) {
    return (
        <Modal show={show} onClose={onCancel} maxWidth="md">
            <div className="p-6">
                <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
                <p className="mt-2 text-sm text-slate-600">{message}</p>

                {targetLabel ? (
                    <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{targetLabel}</p>
                ) : null}

                {remarksRequired ? (
                    <div className="mt-5">
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">{remarksLabel}</label>
                        <textarea
                            value={remarks}
                            onChange={(event) => onRemarksChange?.(event.target.value)}
                            className="h-28 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-slate-400 focus:outline-none"
                            placeholder={remarksPlaceholder}
                            aria-required={remarksRequired}
                        />
                        {remarksError ? <InputError message={remarksError} className="mt-1" /> : null}
                    </div>
                ) : null}

                <div className="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={processing}
                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing || (remarksRequired && !remarks.trim())}
                        className={`rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-60 ${confirmToneClass}`}
                    >
                        {processing ? 'Submitting...' : confirmLabel}
                    </button>
                </div>
            </div>
        </Modal>
    );
}