(() => {
    'use strict';
    const timeZone = document.currentScript?.dataset.timezone || 'Asia/Manila';
    const clock = new Intl.DateTimeFormat('en-CA', {
        timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    });
    const display = new Intl.DateTimeFormat('en-PH', {
        timeZone: 'UTC', year: 'numeric', month: 'short', day: 'numeric',
        hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true,
    });
    function nowInput(now = new Date()) {
        const parts = Object.fromEntries(clock.formatToParts(now).map(p => [p.type, p.value]));
        return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}`;
    }
    function format(value) {
        if (!value) return '—';
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!match) return '—';
        const [year, month, day, hour, minute, second] = match.slice(1).map(v => Number(v || 0));
        if (year < 1000) return '—';
        // The database returns local wall time. UTC here preserves those fields
        // when the browser itself is configured for a different timezone.
        const date = new Date(Date.UTC(year, month - 1, day, hour, minute, second));
        if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day
            || date.getUTCHours() !== hour || date.getUTCMinutes() !== minute || date.getUTCSeconds() !== second) return '—';
        return display.format(date);
    }
    window.AppDateTime = Object.freeze({timeZone, nowInput, format});
})();
