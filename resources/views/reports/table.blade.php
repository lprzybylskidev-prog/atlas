<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportName }}</title>
    <style>
        {!! $fontCss !!}

        @page {
            size: A4;
            margin: 16mm 14mm 18mm;

            @bottom-right {
                content: "Page " counter(page);
                color: #52525b;
                font-size: 8px;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #18181b;
            font-family: "Atlas Report", Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            background: #ffffff;
        }

        .print-toolbar {
            display: none;
        }

        header {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d4d4d8;
        }

        .logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .heading {
            flex: 1;
            min-width: 0;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 20px;
            line-height: 1.2;
        }

        .company {
            margin: 0 0 2px;
            color: #52525b;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px 16px;
            color: #3f3f46;
        }

        .meta span {
            font-weight: 700;
            color: #18181b;
        }

        .filters {
            margin: 10px 0 14px;
            padding: 8px;
            border: 1px solid #e4e4e7;
            background: #fafafa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        th,
        td {
            padding: 6px 7px;
            border: 1px solid #d4d4d8;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        th {
            color: #18181b;
            font-weight: 700;
            background: #e5e7eb;
        }

        tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .charts {
            margin-top: 16px;
            display: grid;
            gap: 12px;
            page-break-inside: avoid;
        }

        .chart {
            padding: 10px;
            border: 1px solid #e4e4e7;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .chart h2 {
            margin: 0 0 3px;
            font-size: 12px;
        }

        .chart p {
            margin: 0 0 8px;
            color: #52525b;
        }

        .chart-row {
            display: grid;
            grid-template-columns: 86px 1fr 62px;
            gap: 8px;
            align-items: center;
            margin-top: 5px;
        }

        .chart-label,
        .chart-value {
            overflow-wrap: anywhere;
        }

        .chart-value {
            text-align: right;
            font-weight: 700;
        }

        footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #d4d4d8;
            color: #52525b;
        }

        @media screen {
            body.browser-print {
                padding: 24px;
                background: #f4f4f5;
            }

            .browser-print main {
                max-width: 1120px;
                margin: 0 auto;
                padding: 28px;
                background: #ffffff;
                border: 1px solid #d4d4d8;
            }

            .browser-print .print-toolbar {
                display: flex;
                justify-content: flex-end;
                max-width: 1120px;
                margin: 0 auto 12px;
            }

            .print-toolbar button {
                border: 1px solid #18181b;
                background: #18181b;
                color: #ffffff;
                font: inherit;
                font-weight: 600;
                padding: 8px 12px;
                cursor: pointer;
            }
        }

        @media print {
            .print-toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body @class(['browser-print' => $browserPrint])>
    @if ($browserPrint)
        <div class="print-toolbar">
            <button type="button" onclick="window.print()">Print</button>
        </div>
    @endif

    <main>
        <header>
            @if ($logoDataUri !== null)
                <img class="logo" src="{{ $logoDataUri }}" alt="{{ $companyName }}">
            @endif
            <div class="heading">
                <p class="company">{{ $companyName }}</p>
                <h1>{{ $reportName }}</h1>
                <div class="meta">
                    <div><span>Module:</span> {{ $moduleKey }}</div>
                    <div><span>Active team:</span> {{ $activeTeamPublicId ?? 'none' }}</div>
                    <div><span>Generated at:</span> {{ $generatedAt }}</div>
                    <div><span>Generated by:</span> {{ $requestingUserPublicId }}</div>
                    <div><span>Release:</span> {{ $releaseVersion }}</div>
                    <div><span>Rules:</span> {{ $ruleVersion }}</div>
                </div>
            </div>
        </header>

        <section class="filters" aria-label="Report filters">
            <strong>Filters:</strong> {{ json_encode($filters, JSON_THROW_ON_ERROR) }}
            @if ($timeRange !== null)
                <br><strong>Time range:</strong> {{ json_encode($timeRange, JSON_THROW_ON_ERROR) }}
            @endif
        </section>

        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}">No rows</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if (count($charts) > 0)
            <section class="charts" aria-label="Report charts">
                @foreach ($charts as $chart)
                    @php
                        $values = [];
                        foreach ($chart['series'] as $series) {
                            foreach ($series['points'] as $point) {
                                $values[] = max(0, is_numeric($point['value']) ? (float) $point['value'] : 0.0);
                            }
                        }
                        $maxValue = max(1, ...$values);
                    @endphp
                    <article class="chart" aria-labelledby="chart-{{ $chart['key'] }}">
                        <h2 id="chart-{{ $chart['key'] }}">{{ $chart['title'] }}</h2>
                        @if ($chart['description'] !== null)
                            <p>{{ $chart['description'] }}</p>
                        @endif

                        @foreach ($chart['series'] as $series)
                            <strong>{{ $series['label'] }}</strong>
                            @foreach ($series['points'] as $point)
                                @php
                                    $value = max(0, is_numeric($point['value']) ? (float) $point['value'] : 0.0);
                                    $width = max(1, min(100, ($value / $maxValue) * 100));
                                @endphp
                                <div class="chart-row">
                                    <span class="chart-label">{{ $point['label'] }}</span>
                                    <svg viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true">
                                        <rect x="0" y="1" width="100" height="8" rx="2" fill="#e4e4e7"></rect>
                                        <rect x="0" y="1" width="{{ $width }}" height="8" rx="2" fill="#0f766e"></rect>
                                    </svg>
                                    <span class="chart-value">{{ number_format($value, 0, '.', ' ') }}{{ $chart['unit'] !== null ? ' '.$chart['unit'] : '' }}</span>
                                </div>
                            @endforeach
                        @endforeach
                    </article>
                @endforeach
            </section>
        @endif

        <footer>
            {{ $footerText }}
            @foreach ($totals as $total)
                {{ $total['label'] }}: {{ $total['value'] }}.
            @endforeach
        </footer>
    </main>
</body>
</html>
