import TextInput from '@/Components/TextInput';
import { forwardRef, InputHTMLAttributes, useImperativeHandle, useRef, useState } from 'react';

type Props = InputHTMLAttributes<HTMLInputElement> & {
    isFocused?: boolean;
    wrapperClassName?: string;
};

export default forwardRef<HTMLInputElement, Props>(function PasswordInput(
    {
        className = '',
        wrapperClassName = '',
        isFocused = false,
        ...props
    },
    ref,
) {
    const [showPassword, setShowPassword] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => inputRef.current?.focus(),
    }) as HTMLInputElement);

    return (
        <div className={`relative ${wrapperClassName}`.trim()}>
            <TextInput
                {...props}
                ref={inputRef}
                type={showPassword ? 'text' : 'password'}
                isFocused={isFocused}
                className={`${className} pr-12`.trim()}
            />

            <button
                type="button"
                onClick={() => setShowPassword((current) => !current)}
                className="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-md text-slate-500 transition hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                title={showPassword ? 'Hide password' : 'Show password'}
            >
                {showPassword ? (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C7 20 2.73 16.89 1 12a18.45 18.45 0 0 1 5.06-6.94" />
                        <path d="M9.9 4.24A10.78 10.78 0 0 1 12 4c5 0 9.27 3.11 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                        <path d="M14.12 14.12a3 3 0 0 1-4.24-4.24" />
                        <path d="M1 1l22 22" />
                    </svg>
                ) : (
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                )}
            </button>
        </div>
    );
});