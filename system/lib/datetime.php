<?php
// All stored DATETIME values use the application's local timezone.
function normalizeDateTime($value, string $label = 'Date and time', ?string $default = null, bool $allowDateOnly = false): ?string {
    if ($value === null || $value === '') return $default;
    if (!is_string($value)) throw new InvalidArgumentException("{$label} must be a valid date and time.");
    $value = trim($value);
    if ($value === '') return $default;
    if ($allowDateOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) $value .= ' 00:00:00';
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $value)) $value .= ':00';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) || substr($value, 0, 4) < '1000') {
        throw new InvalidArgumentException("{$label} must include a valid date and time.");
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        throw new InvalidArgumentException("{$label} must be a valid date and time.");
    }
    return $value;
}

function normalizeDateTimeRange($from, $to, ?string $defaultFrom = null, ?string $defaultTo = null): array {
    $start = normalizeDateTime($from, 'From', $defaultFrom, true);
    $end = normalizeDateTime($to, 'To', $defaultTo, true);
    // Older bookmarked date-only filters still include the entire last day.
    if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', trim($to))) {
        $end = substr($end, 0, 10) . ' 23:59:59';
    }
    if ($start !== null && $end !== null && $start > $end) {
        throw new InvalidArgumentException('From must be earlier than or equal to To.');
    }
    return [$start, $end];
}

function formatDateTime(?string $value): string {
    try { $value = normalizeDateTime($value, 'Date and time', null, true); }
    catch (InvalidArgumentException $e) { return '—'; }
    return $value === null ? '—' : DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value)->format('M j, Y, g:i:s A');
}

// API adapters return validation errors before a transaction starts.
function inputDateTime($value, string $label, ?string $default = null, bool $required = false): ?string {
    try {
        $result = normalizeDateTime($value, $label, $default);
        if ($required && $result === null) throw new InvalidArgumentException("{$label} is required.");
        return $result;
    } catch (InvalidArgumentException $e) {
        jsonResponse(false, $e->getMessage());
    }
}

function inputDateTimeRange($from, $to, ?string $defaultFrom = null, ?string $defaultTo = null): array {
    try { return normalizeDateTimeRange($from, $to, $defaultFrom, $defaultTo); }
    catch (InvalidArgumentException $e) { jsonResponse(false, $e->getMessage()); }
}
