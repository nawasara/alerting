<?php

namespace Nawasara\Alerting\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nawasara\Alerting\Contracts\AlertRuleDefinition;
use Nawasara\Alerting\Models\AlertState;
use Nawasara\Notification\Facades\Notify;

/**
 * Bridges the alerting state machine to nawasara/notification's channel
 * fan-out. Resolves audience, renders subject + body from the rule's
 * template/view, and calls Notify::to(...)->send() per channel configured
 * for the severity.
 *
 * Three kinds — 'fired', 'renotified', 'resolved' — drive subject prefix
 * and body view selection. Body view fallback chain:
 *   rule->bodyView() → alerting::emails.alert-{kind} → alerting::emails.alert-fired
 */
class NotifyDispatcher
{
    public function __construct(
        protected RecipientResolver $recipients,
    ) {}

    public function dispatch(AlertState $state, AlertRuleDefinition $rule, string $kind): void
    {
        if (! in_array($kind, ['fired', 'renotified', 'resolved'], true)) {
            throw new \InvalidArgumentException("Invalid dispatch kind: {$kind}");
        }

        $recipients = $this->recipients->resolveBySeverity($state->severity);
        if ($recipients->isEmpty()) {
            // No-one is configured to hear about this severity — log so
            // sysadmin sees it in the activity feed, but don't error.
            Log::warning('alerting: no recipients for severity '.$state->severity, [
                'rule' => $rule->key(),
                'kind' => $kind,
            ]);

            return;
        }

        $channels = config("nawasara-alerting.severity.{$state->severity}.channels", ['email']);

        $subject = $this->renderSubject($state, $rule, $kind);
        $body = $this->renderBody($state, $rule, $kind);
        $emails = $recipients->pluck('email')->filter()->all();

        try {
            Notify::to(...$emails)
                ->channel($channels)
                ->subject($subject)
                ->body($body)
                ->priority($state->severity)
                ->context([
                    'alert_state_id' => $state->id,
                    'rule_key' => $rule->key(),
                    'kind' => $kind,
                    'target_type' => $state->target_type,
                    'target_id' => $state->target_id,
                    'fire_count' => $state->fire_count,
                ])
                ->send();
        } catch (\Throwable $e) {
            // Swallow — never let a notification failure cascade back into
            // the caller (the AlertEvaluator). Log and move on.
            Log::error('alerting: notification dispatch failed', [
                'rule' => $rule->key(),
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function renderSubject(AlertState $state, AlertRuleDefinition $rule, string $kind): string
    {
        $prefix = match ($kind) {
            'renotified' => '[RE-NOTIFY] ',
            'resolved' => '[RESOLVED] ',
            default => '',
        };

        $template = $rule->subjectTemplate();
        $context = $state->context ?? [];

        // Replace {placeholders} — {severity}, {category}, {target_type},
        // {target_id}, {key}, and {context.X} for dot-notation lookups.
        $subject = preg_replace_callback('/\{([\w\.]+)\}/', function ($m) use ($state, $rule, $context) {
            $key = $m[1];

            return match (true) {
                $key === 'severity' => strtoupper($state->severity),
                $key === 'category' => $rule->category(),
                $key === 'key' => $rule->key(),
                $key === 'target_type' => (string) ($state->target_type ?? ''),
                $key === 'target_id' => (string) ($state->target_id ?? ''),
                Str::startsWith($key, 'context.') => $this->stringify(Arr::get($context, Str::after($key, 'context.'), '')),
                default => $m[0],
            };
        }, $template);

        return $prefix.$subject;
    }

    protected function renderBody(AlertState $state, AlertRuleDefinition $rule, string $kind): string
    {
        $candidates = array_filter([
            $rule->bodyView(),
            "nawasara-alerting::emails.alert-{$kind}",
            'nawasara-alerting::emails.alert-fired',
        ]);

        foreach ($candidates as $view) {
            if (view()->exists($view)) {
                return view($view, [
                    'state' => $state,
                    'rule' => $rule,
                    'kind' => $kind,
                    'context' => $state->context ?? [],
                    'action_url' => $this->actionUrl($state),
                ])->render();
            }
        }

        // Final fallback — plain summary so a missing template doesn't make
        // notifications silently empty.
        return sprintf(
            "%s\n\nRule: %s\nState: %s\nFire count: %d\nLast fired: %s\n\nDetails: %s",
            $rule->description() ?: $rule->key(),
            $rule->key(),
            $state->status,
            $state->fire_count,
            optional($state->fired_at)->toDateTimeString() ?? '-',
            $this->actionUrl($state),
        );
    }

    protected function actionUrl(AlertState $state): string
    {
        $base = rtrim((string) config('nawasara-alerting.email.action_url_base', config('app.url')), '/');

        return $base.'/nawasara-alerting/states?state='.$state->id;
    }

    protected function stringify(mixed $v): string
    {
        if (is_scalar($v)) {
            return (string) $v;
        }
        if ($v === null) {
            return '';
        }

        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
