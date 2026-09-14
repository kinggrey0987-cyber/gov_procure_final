import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const IDLE_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
const WARNING_WINDOW_MS = 60 * 1000; // 60 seconds warning before logout
const STORAGE_KEY = 'govsupply_session_last_active';
const THROTTLE_INTERVAL_MS = 3000; // Throttle activity writes to 3 seconds

export default function SessionTimeoutGuard() {
    const [secondsRemaining, setSecondsRemaining] = useState<number | null>(null);
    const [showWarning, setShowWarning] = useState<boolean>(false);
    const lastWriteRef = useRef<number>(Date.now());
    const isLoggingOutRef = useRef<boolean>(false);

    const recordActivity = () => {
        const now = Date.now();
        if (now - lastWriteRef.current > THROTTLE_INTERVAL_MS) {
            lastWriteRef.current = now;
            try {
                localStorage.setItem(STORAGE_KEY, now.toString());
            } catch {
                // Ignore storage errors (e.g. storage disabled)
            }
        }
    };

    const handleStayLoggedIn = () => {
        const now = Date.now();
        lastWriteRef.current = now;
        try {
            localStorage.setItem(STORAGE_KEY, now.toString());
        } catch {
            // Ignore
        }
        setShowWarning(false);
        setSecondsRemaining(null);

        // Ping the server to refresh the backend session cookie
        router.reload({ only: [] });
    };

    const handleLogout = () => {
        if (isLoggingOutRef.current) return;
        isLoggingOutRef.current = true;
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // Ignore
        }
        setShowWarning(false);
        router.post(route('logout'));
    };

    useEffect(() => {
        // Initialize last active timestamp if not already set
        const existing = localStorage.getItem(STORAGE_KEY);
        if (!existing) {
            try {
                localStorage.setItem(STORAGE_KEY, Date.now().toString());
            } catch {
                // Ignore
            }
        }

        // Register user activity listeners
        const activityEvents = ['mousedown', 'keydown', 'touchstart', 'scroll', 'mousemove'];
        const onActivity = () => recordActivity();

        activityEvents.forEach((evt) => {
            window.addEventListener(evt, onActivity, { passive: true });
        });

        // Cross-tab synchronization listener
        const onStorage = (event: StorageEvent) => {
            if (event.key === STORAGE_KEY && event.newValue) {
                const updatedTime = Number(event.newValue);
                if (Date.now() - updatedTime < IDLE_TIMEOUT_MS - WARNING_WINDOW_MS) {
                    setShowWarning(false);
                    setSecondsRemaining(null);
                }
            }
        };
        window.addEventListener('storage', onStorage);

        // Check on tab focus or visibility change (e.g., wake from sleep or tab switch)
        const checkImmediate = () => {
            const lastActiveStr = localStorage.getItem(STORAGE_KEY);
            const lastActive = lastActiveStr ? Number(lastActiveStr) : Date.now();
            const elapsed = Date.now() - lastActive;

            if (elapsed >= IDLE_TIMEOUT_MS) {
                handleLogout();
            }
        };

        window.addEventListener('focus', checkImmediate);
        document.addEventListener('visibilitychange', checkImmediate);

        // Interval timer running every second to update countdown & trigger warning/logout
        const timer = window.setInterval(() => {
            if (isLoggingOutRef.current) return;

            const lastActiveStr = localStorage.getItem(STORAGE_KEY);
            const lastActive = lastActiveStr ? Number(lastActiveStr) : Date.now();
            const elapsed = Date.now() - lastActive;
            const remainingMs = IDLE_TIMEOUT_MS - elapsed;

            if (remainingMs <= 0) {
                // Time is up -> Logout immediately
                handleLogout();
            } else if (remainingMs <= WARNING_WINDOW_MS) {
                // Within 60-second warning window
                setShowWarning(true);
                setSecondsRemaining(Math.ceil(remainingMs / 1000));
            } else {
                // Still active
                if (showWarning) {
                    setShowWarning(false);
                    setSecondsRemaining(null);
                }
            }
        }, 1000);

        return () => {
            activityEvents.forEach((evt) => {
                window.removeEventListener(evt, onActivity);
            });
            window.removeEventListener('storage', onStorage);
            window.removeEventListener('focus', checkImmediate);
            document.removeEventListener('visibilitychange', checkImmediate);
            window.clearInterval(timer);
        };
    }, []);

    if (!showWarning || secondsRemaining === null) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm transition-all animate-fadeIn">
            <div className="w-full max-w-md overflow-hidden rounded-2xl border border-amber-300 bg-white p-6 shadow-2xl transition-all sm:p-7">
                <div className="flex items-start gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div className="flex-1">
                        <p className="text-xs font-semibold uppercase tracking-wider text-amber-700">Security Inactivity Notice</p>
                        <h3 className="mt-0.5 text-lg font-bold text-slate-900">Your Session Is Expiring</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            You have been inactive for nearly 15 minutes. For municipal data security and audit compliance, you will be automatically logged out in:
                        </p>

                        <div className="mt-4 flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50/80 py-3">
                            <span className="text-3xl font-extrabold tabular-nums tracking-tight text-amber-800">
                                {secondsRemaining}s
                            </span>
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors sm:w-auto"
                    >
                        Log Out Now
                    </button>
                    <button
                        type="button"
                        onClick={handleStayLoggedIn}
                        className="inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors sm:w-auto"
                    >
                        Keep Me Signed In
                    </button>
                </div>
            </div>
        </div>
    );
}
