type Props = {
    month: string;
    year: string;
    onMonthChange: (value: string) => void;
    onYearChange: (value: string) => void;
    yearOptions: number[];
    className?: string;
    allMonthLabel?: string;
    allYearLabel?: string;
};

export default function MonthFilterSelect({
    month,
    year,
    onMonthChange,
    onYearChange,
    yearOptions,
    className,
    allMonthLabel = 'All months',
    allYearLabel = 'All years',
}: Props) {
    const monthOptions = buildMonthOptions();

    return (
        <div className="grid gap-2 md:grid-cols-2">
            <select value={month} onChange={(event) => onMonthChange(event.target.value)} className={className}>
                <option value="">{allMonthLabel}</option>
                {monthOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <select value={year} onChange={(event) => onYearChange(event.target.value)} className={className}>
                <option value="">{allYearLabel}</option>
                {yearOptions.map((year) => (
                    <option key={year} value={year}>
                        {year}
                    </option>
                ))}
            </select>
        </div>
    );
}

function buildMonthOptions() {
    return [
        { value: '01', label: 'January' },
        { value: '02', label: 'February' },
        { value: '03', label: 'March' },
        { value: '04', label: 'April' },
        { value: '05', label: 'May' },
        { value: '06', label: 'June' },
        { value: '07', label: 'July' },
        { value: '08', label: 'August' },
        { value: '09', label: 'September' },
        { value: '10', label: 'October' },
        { value: '11', label: 'November' },
        { value: '12', label: 'December' },
    ];
}





