<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — OMET Finance</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #0F172A;
            font-size: 10px;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #0F172A;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 { margin: 0; font-size: 14px; }
        .header .meta { color: #475569; font-size: 9px; margin-top: 2px; }
        .header .range { color: #0F172A; font-weight: bold; margin-top: 4px; font-size: 10px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #1E293B;
            padding: 4px 6px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #E2E8F0;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #0F172A;
        }
        td { font-size: 10px; }
        tr.subtotal td { background: #FEF3C7; font-weight: bold; }
        tr.grand td   { background: #0F172A; color: #fff; font-weight: bold; }
        tr.group   td { background: #F1F5F9; font-weight: bold; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
        .footer { color: #94A3B8; font-size: 8px; text-align: center; margin-top: 14px; }
        .summary-grid {
            width: 100%;
            border: 1px solid #1E293B;
            border-collapse: collapse;
        }
        .summary-grid td { padding: 8px 10px; border: 1px solid #1E293B; font-size: 11px; }
        .summary-grid td.label { background: #F1F5F9; font-weight: bold; width: 40%; }
        .summary-grid td.value { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>OMET Finance — {{ $title }}</h1>
        <p class="meta">Generated {{ now()->format('M j, Y · g:i A') }}</p>
        <p class="range">{{ $range }}</p>
    </div>

    @php
        // Detect total-row markers in the flat $rows so we can style them.
        $isGrand = fn ($cells) => collect($cells)->contains(fn ($c) => is_string($c) && (str_starts_with($c, 'GRAND TOTAL') || str_starts_with($c, 'Grand Total')));
        $isSub   = fn ($cells) => collect($cells)->contains(fn ($c) => is_string($c) && str_starts_with($c, 'Subtotal'));
    @endphp

    <table>
        <thead>
            <tr>
                @foreach ($headings as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr @class([
                    'grand'    => $isGrand($row),
                    'subtotal' => ! $isGrand($row) && $isSub($row),
                ])>
                    @foreach ($row as $i => $cell)
                        <td style="{{ $i === count($row) - 1 ? 'text-align: right; font-variant-numeric: tabular-nums;' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footer">OMET Finance System · Confidential · Do not redistribute</p>
</body>
</html>
