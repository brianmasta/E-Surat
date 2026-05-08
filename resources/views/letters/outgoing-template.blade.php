<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Surat Keluar {{ $letter->number }}</title>
    <style>
        body {
            background: #e2e8f0;
            color: #0f172a;
            font-family: "Times New Roman", serif;
            margin: 0;
            padding: 24px;
        }

        .toolbar {
            font-family: Arial, sans-serif;
            margin: 0 auto 16px;
            max-width: 794px;
            text-align: right;
        }

        .toolbar button {
            background: #0f766e;
            border: 0;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            min-height: 40px;
            padding: 0 16px;
        }

        .page {
            background: #fff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
            margin: 0 auto;
            max-width: 794px;
            min-height: 1123px;
            padding: 56px 72px;
        }

        .letterhead {
            border-bottom: 3px double #0f172a;
            padding-bottom: 12px;
            text-align: center;
        }

        .agency-name {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .agency-unit {
            font-size: 18px;
            font-weight: 700;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .agency-meta {
            font-size: 13px;
            margin-top: 4px;
        }

        .meta {
            margin-top: 28px;
        }

        .meta-row {
            display: grid;
            gap: 8px;
            grid-template-columns: 90px 12px 1fr;
            margin-bottom: 4px;
        }

        .recipient {
            margin-top: 28px;
        }

        .body {
            line-height: 1.65;
            margin-top: 24px;
            text-align: justify;
            white-space: pre-line;
        }

        .signature {
            margin-left: auto;
            margin-top: 42px;
            text-align: center;
            width: 280px;
        }

        .signer-name {
            font-weight: 700;
            margin-top: 86px;
            text-decoration: underline;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .page {
                box-shadow: none;
                margin: 0;
                max-width: none;
                min-height: auto;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="page">
        <header class="letterhead">
            <div class="agency-name">{{ $agency['name'] ?? 'Instansi Pemerintah' }}</div>
            <div class="agency-unit">{{ $agency['unit'] ?? 'Unit Kerja' }}</div>
            <div class="agency-meta">{{ $agency['address'] ?? '' }}</div>
            <div class="agency-meta">
                {{ $agency['email'] ?? '' }}{{ ! empty($agency['phone']) ? ' | '.$agency['phone'] : '' }}
            </div>
        </header>

        <section class="meta">
            <div class="meta-row"><span>Nomor</span><span>:</span><span>{{ $letter->number }}</span></div>
            <div class="meta-row"><span>Sifat</span><span>:</span><span>{{ $letter->nature }}</span></div>
            <div class="meta-row"><span>Perihal</span><span>:</span><span>{{ $letter->subject }}</span></div>
        </section>

        <section class="recipient">
            <div>Yth. {{ $letter->external_party }}</div>
            <div>di tempat</div>
        </section>

        <section class="body">{{ $letter->outgoing_body }}</section>

        <section class="signature">
            <div>{{ $agency['city'] ?? 'Tempat' }}, {{ $letter->letter_date->translatedFormat('d F Y') }}</div>
            <div>{{ $letter->signer_title ?: 'Pimpinan' }}</div>
            <div class="signer-name">{{ $letter->signer_name ?: 'Nama Pejabat' }}</div>
        </section>
    </main>
</body>
</html>
