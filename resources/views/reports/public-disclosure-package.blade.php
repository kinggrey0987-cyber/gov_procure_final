<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Official Public Disclosure Package</title>
    <style>
        @page { margin: 34px 38px; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        .header { border-bottom: 3px solid #b91c1c; padding-bottom: 12px; margin-bottom: 18px; }
        .kicker { color: #b91c1c; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: 21px; line-height: 1.2; margin: 4px 0 6px; }
        h2 { color: #991b1b; font-size: 13px; margin: 18px 0 8px; text-transform: uppercase; }
        h3 { font-size: 12px; margin: 0; }
        .muted { color: #475569; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid td { border: 1px solid #e2e8f0; padding: 8px; vertical-align: top; width: 50%; }
        .label { color: #334155; display: block; font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .value { display: block; font-size: 12px; font-weight: 700; margin-top: 3px; }
        .summary { background: #fff7f7; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 12px; }
        .source { border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 12px; padding: 10px; page-break-inside: avoid; }
        .badge { background: #fef2f2; border-radius: 999px; color: #b91c1c; display: inline-block; font-size: 9px; font-weight: 700; margin-right: 6px; padding: 2px 7px; }
        .details { width: 100%; border-collapse: collapse; margin-top: 9px; }
        .details th, .details td { border: 1px solid #e2e8f0; padding: 6px 7px; text-align: left; vertical-align: top; }
        .details th { background: #f8fafc; color: #334155; font-size: 9px; letter-spacing: .04em; text-transform: uppercase; }
        .items { margin: 8px 0 0 17px; padding: 0; }
        .items li { margin-bottom: 3px; }
        .footer { border-top: 1px solid #e2e8f0; color: #64748b; font-size: 9px; margin-top: 20px; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="kicker">GovSupply Connect Transparency Portal</div>
        <h1>Official Public Disclosure Package</h1>
        <div class="muted">Frozen public-safe report generated from approved procurement source records.</div>
    </div>

    <table class="grid">
        <tr>
            <td><span class="label">Disclosure ID</span><span class="value">{{ $disclosure->disclosure_id }}</span></td>
            <td><span class="label">Published At</span><span class="value">{{ optional($disclosure->published_at)->format('F d, Y') ?? 'Not published yet' }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Type</span><span class="value">{{ $typeLabel }}</span></td>
            <td><span class="label">Status</span><span class="value">{{ $statusLabel }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Title</span><span class="value">{{ $disclosure->title }}</span></td>
        </tr>
    </table>

    <h2>Public Summary</h2>
    <div class="summary">{{ $disclosure->description ?: 'No public summary was provided.' }}</div>

    <h2>Procurement Traceability</h2>
    @forelse ($sources as $source)
        <div class="source">
            <div><span class="badge">{{ $source['role'] }}</span><h3 style="display:inline;">{{ $source['label'] }}</h3></div>

            @if (count($source['details']) > 0)
                <table class="details">
                    <tbody>
                    @foreach ($source['details'] as $detail)
                        <tr>
                            <th>{{ $detail['label'] }}</th>
                            <td>{{ $detail['value'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            @if (count($source['items']) > 0)
                <table class="details">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($source['items'] as $item)
                        <tr>
                            <td>{{ $item['item'] }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>{{ $item['cost'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="muted">No source traceability records are available for this package.</p>
    @endforelse

    <div class="footer">
        Generated {{ $generatedAt->format('F d, Y h:i A') }}. This report is intended for public transparency and excludes internal operational details.
    </div>
</body>
</html>
