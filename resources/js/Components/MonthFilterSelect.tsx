type Props = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    allLabel?: string;
    monthsBack?: number;
};

export default function MonthFilterSelect({ value, onChange, className, allLabel = 'All months', monthsBack = 24 }: Props) {
    const options = buildMonthOptions(monthsBack, value);

    return (
        <select value={value} onChange={(event) => onChange(event.target.value)} className={className}>
            <option value="">{allLabel}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

function buildMonthOptions(monthsBack: number, currentValue: string) {
    const formatter = new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        year: 'numeric',
    });
    const now = new Date();
    const options = Array.from({ length: monthsBack }, (_, index) => {
        const date = new Date(now.getFullYear(), now.getMonth() - index, 1);
        const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

        return {
            value,
            label: formatter.format(date),
        };
    });

    if (currentValue && !options.some((option) => option.value === currentValue)) {
        const [year, month] = currentValue.split('-').map(Number);
        const date = Number.isFinite(year) && Number.isFinite(month) ? new Date(year, month - 1, 1) : null;
        options.push({
            value: currentValue,
            label: date && !Number.isNaN(date.getTime()) ? formatter.format(date) : currentValue,
        });
    }

    return options;
}
