@php
/** @var \Nawasara\Alerting\Models\AlertState $state */
/** @var \Nawasara\Alerting\Contracts\AlertRuleDefinition $rule */
/** @var string $kind */
/** @var array $context */
/** @var string $action_url */

$kindLabel = match ($kind) {
    'fired' => 'FIRED',
    'renotified' => 'STILL FIRING',
    'resolved' => 'RESOLVED',
    default => strtoupper($kind),
};
$severityColor = match ($state->severity) {
    'critical' => '#dc2626',
    'warning' => '#d97706',
    'info' => '#0284c7',
    default => '#525252',
};
$badgeBg = $kind === 'resolved' ? '#16a34a' : $severityColor;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $kindLabel }} — {{ $rule->key() }}</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;color:#1f2937;">
<div style="max-width:600px;margin:0 auto;padding:24px;background:#ffffff;">
    <div style="background:{{ $badgeBg }};color:#ffffff;padding:12px 16px;border-radius:6px 6px 0 0;font-size:14px;font-weight:bold;letter-spacing:0.5px;">
        {{ $kindLabel }} · {{ strtoupper($state->severity) }}
    </div>

    <div style="border:1px solid #e5e7eb;border-top:none;padding:20px;border-radius:0 0 6px 6px;">
        <h2 style="margin:0 0 8px 0;font-size:18px;">{{ $rule->description() ?: $rule->key() }}</h2>
        <p style="margin:0 0 16px 0;color:#6b7280;font-size:13px;">
            Rule: <code>{{ $rule->key() }}</code>
            @if ($state->target_type)
                · Target: <code>{{ $state->target_type }}#{{ $state->target_id }}</code>
            @endif
        </p>

        @if ($kind === 'resolved')
            <p style="background:#dcfce7;color:#166534;padding:12px;border-radius:4px;margin:0 0 16px 0;font-size:14px;">
                ✓ This alert has been resolved at {{ optional($state->resolved_at)->toDateTimeString() }}.
            </p>
        @else
            <table style="width:100%;border-collapse:collapse;margin:0 0 16px 0;font-size:13px;">
                <tr style="background:#f9fafb;">
                    <td style="padding:8px;border:1px solid #e5e7eb;width:35%;color:#6b7280;">Fired at</td>
                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ optional($state->fired_at)->toDateTimeString() ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #e5e7eb;color:#6b7280;">Fire count</td>
                    <td style="padding:8px;border:1px solid #e5e7eb;">{{ $state->fire_count }}</td>
                </tr>
                @if ($state->last_notified_at && $kind === 'renotified')
                    <tr style="background:#f9fafb;">
                        <td style="padding:8px;border:1px solid #e5e7eb;color:#6b7280;">Last notified before</td>
                        <td style="padding:8px;border:1px solid #e5e7eb;">{{ $state->last_notified_at->diffForHumans() }}</td>
                    </tr>
                @endif
            </table>
        @endif

        @if (! empty($context))
            <div style="margin:0 0 16px 0;">
                <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Context</div>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    @foreach ($context as $key => $value)
                        <tr style="{{ $loop->odd ? 'background:#f9fafb;' : '' }}">
                            <td style="padding:6px 8px;border:1px solid #e5e7eb;width:35%;color:#6b7280;">{{ $key }}</td>
                            <td style="padding:6px 8px;border:1px solid #e5e7eb;font-family:Consolas,monospace;font-size:12px;">
                                {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div style="margin:24px 0 0 0;text-align:center;">
            <a href="{{ $action_url }}"
                style="display:inline-block;padding:10px 20px;background:#1f2937;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;">
                View in Nawasara
            </a>
        </div>

        @if ($kind !== 'resolved')
            <p style="margin:20px 0 0 0;padding:12px;background:#f9fafb;border-left:3px solid #d1d5db;color:#6b7280;font-size:12px;">
                Click "Acknowledge" in Nawasara to stop further re-notifications without marking the underlying issue resolved.
                If this is noise, "Silence" the alert for a maintenance window.
            </p>
        @endif
    </div>

    <p style="text-align:center;margin:16px 0 0 0;color:#9ca3af;font-size:11px;">
        Sent by Nawasara Alerting — category: {{ $rule->category() }}
    </p>
</div>
</body>
</html>
