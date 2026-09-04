<?php

namespace Nawasara\Alerting\Facades;

use Illuminate\Support\Facades\Facade;
use Nawasara\Alerting\Services\AlerterImpl;

/**
 * @method static \Nawasara\Alerting\Models\AlertState fire(string $ruleKey, ?string $targetType = null, ?string $targetId = null, array $context = [])
 * @method static \Nawasara\Alerting\Models\AlertState|null resolve(string $ruleKey, ?string $targetType = null, ?string $targetId = null)
 * @method static \Nawasara\Alerting\Models\AlertState acknowledge(int $stateId, ?\Illuminate\Foundation\Auth\User $user = null)
 * @method static \Nawasara\Alerting\Models\AlertState silence(int $stateId, int $minutes, ?\Illuminate\Foundation\Auth\User $user = null, ?string $reason = null)
 * @method static bool isFiring(string $ruleKey, ?string $targetType = null, ?string $targetId = null)
 * @method static \Illuminate\Support\Collection firing()
 * @method static void registerRule(object $rule)
 *
 * @see AlerterImpl
 */
class Alerter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'alerter';
    }
}
