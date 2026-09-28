import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { ReactNode } from 'react';

type Variant = 'danger' | 'warning' | 'success' | 'primary';

type Props = {
    show: boolean;
    title: string;
    message: ReactNode;
    confirmText?: string;
    cancelText?: string;
    variant?: Variant;
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

export default function ConfirmationModal({
    show,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'primary',
    processing = false,
    onConfirm,
    onClose,
}: Props) {
    const renderIcon = () => {
        switch (variant) {
            case 'danger':
                return (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 sm:h-10 sm:w-10">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </div>
                );
            case 'warning':
                return (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 sm:h-10 sm:w-10">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                );
            case 'success':
                return (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 sm:h-10 sm:w-10">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                );
            case 'primary':
            default:
                return (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 sm:h-10 sm:w-10">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                );
        }
    };

    const renderConfirmButton = () => {
        if (variant === 'danger') {
            return (
                <DangerButton
                    type="button"
                    disabled={processing}
                    onClick={onConfirm}
                    className="inline-flex w-full justify-center sm:w-auto"
                >
                    {processing ? (
                        <span className="inline-flex items-center gap-2">
                            <svg className="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            Processing...
                        </span>
                    ) : (
                        confirmText
                    )}
                </DangerButton>
            );
        }

        if (variant === 'warning') {
            return (
                <button
                    type="button"
                    disabled={processing}
                    onClick={onConfirm}
                    className="inline-flex w-full items-center justify-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-900 disabled:opacity-25 sm:w-auto"
                >
                    {processing ? (
                        <span className="inline-flex items-center gap-2">
                            <svg className="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            Processing...
                        </span>
                    ) : (
                        confirmText
                    )}
                </button>
            );
        }

        if (variant === 'success') {
            return (
                <button
                    type="button"
                    disabled={processing}
                    onClick={onConfirm}
                    className="inline-flex w-full items-center justify-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-emerald-700 focus:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-900 disabled:opacity-25 sm:w-auto"
                >
                    {processing ? (
                        <span className="inline-flex items-center gap-2">
                            <svg className="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            Processing...
                        </span>
                    ) : (
                        confirmText
                    )}
                </button>
            );
        }

        return (
            <PrimaryButton
                type="button"
                disabled={processing}
                onClick={onConfirm}
                className="inline-flex w-full justify-center sm:w-auto"
            >
                {processing ? (
                    <span className="inline-flex items-center gap-2">
                        <svg className="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                        Processing...
                    </span>
                ) : (
                    confirmText
                )}
            </PrimaryButton>
        );
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="p-6">
                <div className="sm:flex sm:items-start">
                    {renderIcon()}
                    <div className="mt-3 w-full flex-1 min-w-0 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 className="text-lg font-semibold leading-6 text-slate-900" id="modal-title">
                            {title}
                        </h3>
                        <div className="mt-2 text-sm text-slate-600">
                            {typeof message === 'string' ? <p>{message}</p> : message}
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton
                        type="button"
                        disabled={processing}
                        onClick={onClose}
                        className="inline-flex w-full justify-center sm:w-auto"
                    >
                        {cancelText}
                    </SecondaryButton>
                    {renderConfirmButton()}
                </div>
            </div>
        </Modal>
    );
}
