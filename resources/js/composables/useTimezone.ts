const COOKIE_NAME = 'timezone';

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name: string, value: string, days = 365): void {
    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
}

/**
 * Reports the browser's IANA timezone to the backend via a cookie, mirroring
 * how the "appearance" cookie works (resources/js/composables/useAppearance.ts).
 * The backend (TrackUserActivity middleware) reads this to compute streak,
 * night-owl, and early-bird day/hour boundaries in the user's own timezone
 * instead of the server's.
 */
export function initializeTimezoneCookie(): void {
    if (typeof window === 'undefined' || typeof Intl === 'undefined') {
        return;
    }

    let timezone: string;
    try {
        timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return;
    }

    if (!timezone || getCookie(COOKIE_NAME) === timezone) {
        return;
    }

    setCookie(COOKIE_NAME, timezone);
}
