<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .header { border-bottom: 3px solid {{ $themeColor }}; padding-bottom: 12px; margin-bottom: 24px; }
        .brand { font-size: 22px; font-weight: bold; color: {{ $themeColor }}; }
        .meta { margin-top: 16px; }
        .amount { font-size: 18px; font-weight: bold; margin: 24px 0; }
        .footer { margin-top: 40px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $brandName }}</div>
        <div>Subscription Invoice</div>
    </div>

    <div class="meta">
        <div><strong>Invoice #:</strong> {{ $invoice->id }}</div>
        <div><strong>Church:</strong> {{ $church?->name ?? 'N/A' }}</div>
        <div><strong>Plan:</strong> {{ $plan?->name ?? 'N/A' }}</div>
        <div><strong>Due date:</strong> {{ $invoice->due_date->toDateString() }}</div>
        <div><strong>Status:</strong> {{ $invoice->status->value }}</div>
    </div>

    <div class="amount">
        {{ number_format((float) $invoice->amount, 2) }} {{ $invoice->currency }}
    </div>

    <div class="footer">
        {{ filled($footerText ?? null) ? $footerText : 'Hope Works Platform Billing' }} — generated {{ now()->toDateTimeString() }}
    </div>
</body>
</html>
