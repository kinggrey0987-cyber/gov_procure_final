export function dateInputValue(value?: string | Date | null): string {
    if (!value) {
        return '';
    }

    if (value instanceof Date) {
        return formatLocalDate(value);
    }

    const rawValue = String(value).trim();
    const dateOnlyMatch = rawValue.match(/^(\d{4}-\d{2}-\d{2})/);

    if (dateOnlyMatch) {
        return dateOnlyMatch[1];
    }

    const parsedDate = new Date(rawValue);

    return Number.isNaN(parsedDate.getTime()) ? '' : formatLocalDate(parsedDate);
}

export function todayInputValue(): string {
    return formatLocalDate(new Date());
}

function formatLocalDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
