type Props = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    allLabel?: string;
    yearAllLabel?: string;
    monthsBack?: number;
};

export default function MonthFilterSelect({
    value,
    onChange,
    className,
    allLabel = 'All months',
    yearAllLabel = 'All years',
    monthsBack = 24,
}: Props) {
    const { monthValue, yearValue } = parseMonthValue(value);
    const monthOptions = buildMonthOptions();
    const yearOptions = buildYearOptions(monthsBack, value);

    const updateMonth = (nextMonthValue: string) => {
        if (!nextMonthValue || !yearValue) {
            onChange('');

            return;
        }

        onChange(`${yearValue}-${nextMonthValue}`);
    };

    const updateYear = (nextYearValue: string) => {
        if (!nextYearValue || !monthValue) {
            onChange('');

            return;
        }

        onChange(`${nextYearValue}-${monthValue}`);
    };

    return (
        <div className="grid gap-2 md:grid-cols-2">
            <select value={monthValue} onChange={(event) => updateMonth(event.target.value)} className={className}>
                <option value="">{allLabel}</option>
                {monthOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <select value={yearValue} onChange={(event) => updateYear(event.target.value)} className={className}>
                <option value="">{yearAllLabel}</option>
                {yearOptions.map((year) => (
                    <option key={year} value={year}>
                        {year}
                    </option>
                ))}
            </select>
        </div>
    );
}

function parseMonthValue(value: string) {
    const [yearValue = '', monthValue = ''] = value.split('-');

    return { monthValue, yearValue };
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

function buildYearOptions(monthsBack: number, currentValue: string) {
    const now = new Date();
    const currentYear = now.getFullYear();
    const oldestDate = new Date(currentYear, now.getMonth() - Math.max(monthsBack - 1, 0), 1);
    const oldestYear = oldestDate.getFullYear();
    const [selectedYear] = currentValue.split('-').map(Number);
    const endYear = Number.isFinite(selectedYear) ? Math.min(oldestYear, selectedYear) : oldestYear;
    const years: number[] = [];

    for (let year = currentYear; year >= endYear; year -= 1) {
        years.push(year);
    }

    return years;
}
