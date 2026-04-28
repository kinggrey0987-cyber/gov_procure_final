import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type SmartRefreshOptions = {
    only?: string[];
    enabled?: boolean;
    refreshOnMount?: boolean;
    refreshOnFocus?: boolean;
    pollIntervalMs?: number;
    onRefresh?: () => Promise<void> | void;
};

type SmartRefreshState = {
    isRefreshing: boolean;
    lastRefreshedAt: Date | null;
    refreshNow: () => Promise<void>;
};

export default function useSmartRefresh({
    only,
    enabled = true,
    refreshOnMount = true,
    refreshOnFocus = true,
    pollIntervalMs,
    onRefresh,
}: SmartRefreshOptions): SmartRefreshState {
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastRefreshedAt, setLastRefreshedAt] = useState<Date | null>(null);
    const inFlightRef = useRef(false);
    const lastRequestedUrlRef = useRef<string | null>(null);

    const runInertiaReload = useCallback(async () => {
        const requestUrl = typeof window === 'undefined' ? null : window.location.href;
        lastRequestedUrlRef.current = requestUrl;

        await new Promise<void>((resolve) => {
            router.reload({
                only,
                async: true,
                onFinish: () => resolve(),
            });
        });

        if (requestUrl && typeof window !== 'undefined' && window.location.href !== requestUrl) {
            return;
        }
    }, [only]);

    const refreshNow = useCallback(async () => {
        if (!enabled || inFlightRef.current) {
            return;
        }

        inFlightRef.current = true;
        setIsRefreshing(true);

        try {
            if (onRefresh) {
                await onRefresh();
            } else {
                await runInertiaReload();
            }

            if (!lastRequestedUrlRef.current || typeof window === 'undefined' || window.location.href === lastRequestedUrlRef.current) {
                setLastRefreshedAt(new Date());
            }
        } finally {
            inFlightRef.current = false;
            setIsRefreshing(false);
        }
    }, [enabled, onRefresh, runInertiaReload]);

    useEffect(() => {
        if (enabled && refreshOnMount) {
            void refreshNow();
        }
    }, [enabled, refreshOnMount, refreshNow]);

    useEffect(() => {
        if (!enabled || !refreshOnFocus || typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        const handleFocus = () => {
            if (document.visibilityState === 'visible') {
                void refreshNow();
            }
        };

        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                void refreshNow();
            }
        };

        window.addEventListener('focus', handleFocus);
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            window.removeEventListener('focus', handleFocus);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
    }, [enabled, refreshOnFocus, refreshNow]);

    useEffect(() => {
        if (!enabled || !pollIntervalMs || pollIntervalMs <= 0) {
            return;
        }

        const intervalId = window.setInterval(() => {
            void refreshNow();
        }, pollIntervalMs);

        return () => {
            window.clearInterval(intervalId);
        };
    }, [enabled, pollIntervalMs, refreshNow]);

    return {
        isRefreshing,
        lastRefreshedAt,
        refreshNow,
    };
}
