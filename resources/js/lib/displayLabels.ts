export function humanizeEnum(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    return value
        .toLowerCase()
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}
