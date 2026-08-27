<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Official Public Disclosure Package - {{ $disclosure->title }}</title>
    <style>
        @page {
            margin: 22px 28px 26px 28px;
        }
        body {
            color: #0f172a;
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        /* Letterhead Table */
        .letterhead-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .letterhead-logo {
            width: 65px;
            text-align: center;
            vertical-align: middle;
        }
        .letterhead-logo img {
            width: 56px;
            height: 56px;
            border-radius: 50%;
        }
        .letterhead-content {
            text-align: center;
            vertical-align: middle;
            padding: 0 8px;
        }
        .letterhead-country {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #334155;
            margin: 0;
        }
        .letterhead-province {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #334155;
            margin: 1px 0 0 0;
        }
        .letterhead-municipality {
            font-size: 12.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0f172a;
            margin: 2px 0 0 0;
        }
        .letterhead-office {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #991b1b;
            margin: 2px 0 0 0;
        }
        .letterhead-sub {
            font-size: 8px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        
        /* Letterhead Divider */
        .divider-double {
            margin: 4px 0 8px 0;
            border-top: 2px solid #0f172a;
            border-bottom: 1px solid #94a3b8;
            height: 2px;
        }

        /* Top Tracking Bar */
        .tracking-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5px;
        }
        .tracking-left {
            text-align: left;
            font-weight: bold;
            color: #475569;
        }
        .tracking-right {
            text-align: right;
            color: #475569;
        }
        .tracking-right strong {
            color: #0f172a;
            font-size: 9px;
        }

        /* Title Box */
        .doc-title-box {
            text-align: center;
            background-color: #f8fafc;
            border: 1.5px solid #0f172a;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .doc-title-main {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0f172a;
            margin: 0;
        }
        .doc-title-sub {
            font-size: 8.5px;
            font-weight: bold;
            color: #991b1b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 2px 0 0 0;
        }
        .doc-title-date {
            font-size: 8px;
            color: #475569;
            margin: 2px 0 0 0;
        }

        /* Control Parameters Matrix Table */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9px;
        }
        .matrix-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            vertical-align: top;
            width: 50%;
        }
        .matrix-label {
            display: block;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            margin-bottom: 1px;
        }
        .matrix-value {
            display: block;
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
        }
        .status-badge {
            display: inline-block;
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 2px;
            padding: 1px 4px;
            font-size: 8px;
            font-weight: bold;
        }

        /* Section Styling */
        .section-header {
            background-color: #f1f5f9;
            border-left: 3px solid #991b1b;
            padding: 3px 6px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #0f172a;
            margin: 10px 0 5px 0;
        }
        
        .narrative-box {
            background-color: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 6px 8px;
            font-size: 9px;
            line-height: 1.45;
            color: #334155;
            margin-bottom: 8px;
        }

        /* Source Traceability Card */
        .source-card {
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .source-header-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
        }
        .source-header-table td {
            padding: 4px 6px;
            font-size: 9px;
            font-weight: bold;
        }
        .source-badge {
            background-color: #991b1b;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 2px;
            text-transform: uppercase;
            margin-right: 4px;
        }
        
        /* Details Grid inside source */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        .details-table th {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 3px 5px;
            text-align: left;
            font-weight: bold;
            color: #475569;
            width: 25%;
            text-transform: uppercase;
            font-size: 7.5px;
        }
        .details-table td {
            border: 1px solid #e2e8f0;
            padding: 3px 5px;
            color: #1e293b;
        }

        /* Itemized Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-top: 3px;
        }
        .items-table th {
            background-color: #e2e8f0;
            border: 1px solid #cbd5e1;
            padding: 3px 5px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #1e293b;
        }
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 3px 5px;
            color: #1e293b;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Attestation Box */
        .attestation-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #0f172a;
            padding: 5px 8px;
            font-size: 8px;
            color: #475569;
            margin: 10px 0 8px 0;
            font-style: italic;
            page-break-inside: avoid;
        }

        /* Signatory Table */
        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            page-break-inside: avoid;
        }
        .signatory-cell {
            width: 50%;
            vertical-align: top;
            padding: 0 10px;
        }
        .signatory-label {
            font-size: 8px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 22px;
        }
        .signatory-line {
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 2px;
        }
        .signatory-name {
            font-size: 10.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .signatory-title {
            font-size: 8.5px;
            font-weight: bold;
            color: #991b1b;
            margin-top: 1px;
        }
        .signatory-office {
            font-size: 8px;
            color: #64748b;
            margin-top: 1px;
        }

        /* Footer */
        .doc-footer {
            border-top: 1px solid #cbd5e1;
            margin-top: 14px;
            padding-top: 4px;
            font-size: 7.5px;
            color: #64748b;
            width: 100%;
        }
        .doc-footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-footer-table td {
            padding: 0;
        }
    </style>
</head>
<body>
    <!-- Official Philippine Government Letterhead -->
    <table class="letterhead-table">
        <tr>
            <td class="letterhead-logo">
                @if (!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="EB Magalona Seal">
                @endif
            </td>
            <td class="letterhead-content">
                <p class="letterhead-country">Republic of the Philippines</p>
                <p class="letterhead-province">Province of Negros Occidental</p>
                <h1 class="letterhead-municipality">Municipality of Enrique B. Magalona</h1>
                <p class="letterhead-office">Office of the Municipal Mayor</p>
                <p class="letterhead-sub">GovSupply Connect – Municipal Procurement & Transparency Management System</p>
            </td>
            <td class="letterhead-logo">
                @if (!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="EB Magalona Seal">
                @endif
            </td>
        </tr>
    </table>

    <!-- Double Rule Separator -->
    <div class="divider-double"></div>

    <!-- Statutory Tracking Header -->
    <table class="tracking-table">
        <tr>
            <td class="tracking-left">
                DILG Full Disclosure Policy (FDP) Compliance • RA 7160 / RA 9184
            </td>
            <td class="tracking-right">
                Tracking No: <strong>{{ $trackingCode ?? ('EBM-FDP-DISC-' . str_pad((string)$disclosure->disclosure_id, 4, '0', STR_PAD_LEFT)) }}</strong>
            </td>
        </tr>
    </table>

    <!-- Official Document Title Header -->
    <div class="doc-title-box">
        <h2 class="doc-title-main">Full Disclosure Policy (FDP) Compliance Report</h2>
        <p class="doc-title-sub">Report on Fund Utilization and Status of Program/Project/Activity (PPA) Implementation</p>
        <p class="doc-title-date">Published Period: {{ optional($disclosure->published_at ?? $generatedAt)->format('F d, Y') }} • Fiscal Year {{ optional($disclosure->published_at ?? $disclosure->created_at)->format('Y') }}</p>
    </div>

    <!-- Control Parameters & Identification Grid -->
    <table class="matrix-table">
        <tr>
            <td>
                <span class="matrix-label">Disclosure Title / Program Name</span>
                <span class="matrix-value">{{ $disclosure->title }}</span>
            </td>
            <td>
                <span class="matrix-label">Official Publication Status</span>
                <span class="matrix-value"><span class="status-badge">{{ $statusLabel }}</span> (Official Public Document)</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="matrix-label">Disclosure Category / Classification</span>
                <span class="matrix-value">{{ $typeLabel }}</span>
            </td>
            <td>
                <span class="matrix-label">Publication Date</span>
                <span class="matrix-value">{{ optional($disclosure->published_at)->format('F d, Y') ?? 'Draft / In-Review' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="matrix-label">Procuring Entity / LGU</span>
                <span class="matrix-value">Municipality of Enrique B. Magalona</span>
            </td>
            <td>
                <span class="matrix-label">System Tracking ID</span>
                <span class="matrix-value">EBM-DISC-{{ str_pad((string)$disclosure->disclosure_id, 4, '0', STR_PAD_LEFT) }}</span>
            </td>
        </tr>
    </table>

    <!-- Section I: Executive Summary Narrative -->
    <div class="section-header">I. Executive Disclosure Narrative & Legal Basis</div>
    <div class="narrative-box">
        {{ $disclosure->description ?: 'This disclosure record is officially published in strict compliance with the Full Disclosure Policy (FDP) of the Department of the Interior and Local Government (DILG) and Republic Act No. 7160 (Local Government Code of 1991), ensuring transparency and accountability in the utilization of municipal funds and procurement transactions.' }}
    </div>

    <!-- Section II: Procurement Traceability & Itemized Schedule -->
    <div class="section-header">II. Sourced Procurement & Program Implementation Schedule</div>
    @forelse ($sources as $source)
        <div class="source-card">
            <table class="source-header-table">
                <tr>
                    <td>
                        <span class="source-badge">{{ $source['role'] }}</span>
                        <span>{{ $source['label'] }}</span>
                    </td>
                </tr>
            </table>

            @if (count($source['details']) > 0)
                <table class="details-table">
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
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Item Description & Specification</th>
                            <th style="width: 25%; text-align: center;">Quantity / Unit</th>
                            <th style="width: 25%; text-align: right;">Cost / Total (PHP)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($source['items'] as $item)
                        <tr>
                            <td>{{ $item['item'] }}</td>
                            <td style="text-align: center;">{{ $item['quantity'] }}</td>
                            <td style="text-align: right; font-weight: bold;">{{ $item['cost'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="narrative-box">
            No itemized source traceability records were attached to this public disclosure package.
        </div>
    @endforelse

    <!-- Statutory Certification Box -->
    <div class="attestation-box">
        <strong>Statutory Attestation:</strong> We hereby certify that we have reviewed the contents of this public disclosure package and attest to the veracity, completeness, and correctness of the procurement records and fund utilization status contained herein, published pursuant to the DILG Full Disclosure Policy.
    </div>

    <!-- Official Signatory Block -->
    <table class="signatory-table">
        <tr>
            <td class="signatory-cell">
                <div class="signatory-label">Prepared By:</div>
                <div class="signatory-line">
                    <div class="signatory-name">Local Finance Committee / BAC</div>
                </div>
                <div class="signatory-title">Procurement & Oversight Secretariat</div>
                <div class="signatory-office">Municipality of Enrique B. Magalona</div>
            </td>
            <td class="signatory-cell">
                <div class="signatory-label">Attested & Certified Correct:</div>
                <div class="signatory-line">
                    <div class="signatory-name">{{ $mayorName ?? 'Mayor Jeron' }}</div>
                </div>
                <div class="signatory-title">{{ $mayorRole ?? 'Municipal Mayor / Local Chief Executive' }}</div>
                <div class="signatory-office">Office of the Municipal Mayor</div>
            </td>
        </tr>
    </table>

    <!-- Document Footer -->
    <div class="doc-footer">
        <table class="doc-footer-table">
            <tr>
                <td style="text-align: left;">
                    GovSupply Connect • Municipality of Enrique B. Magalona
                </td>
                <td style="text-align: center;">
                    DILG FDP Compliance Document
                </td>
                <td style="text-align: right;">
                    Generated on {{ $generatedAt->format('M d, Y h:i A') }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
