<?php

namespace Nawasara\Alerting\Tests;

use Nawasara\Alerting\Models\AlertRule;
use Nawasara\Alerting\Models\AlertState;
use Nawasara\Alerting\Services\NotifyDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Spam / konten ilegal punya topik Telegram sendiri (16 Oktober 2026).
 *
 * Yang membersihkan halaman judol adalah pengelola situs OPD, bukan admin
 * jaringan. Di Kritis ia bercampur dengan agen mati; di Keamanan tenggelam di
 * antara ratusan blokir IP.
 */
class ContentTopicTest extends TestCase
{
    private function topic(string $key, string $severity): string
    {
        $dispatcher = (new \ReflectionClass(NotifyDispatcher::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(NotifyDispatcher::class, 'topicFor');

        $state = (new AlertState)->forceFill(['severity' => $severity, 'context' => []]);
        $rule = AlertRule::make(['key' => $key, 'severity' => $severity, 'category' => 'security']);

        return $method->invoke($dispatcher, $state, $rule);
    }

    public function test_content_rules_go_to_konten_even_when_critical(): void
    {
        $this->assertSame('konten', $this->topic('secscan.file.spam', 'critical'));
        $this->assertSame('konten', $this->topic('secscan.site.compromised', 'critical'));
        $this->assertSame('konten', $this->topic('secscan.site.suspicious', 'warning'));
    }

    public function test_webshell_and_agent_down_stay_in_kritis(): void
    {
        $this->assertSame('kritis', $this->topic('secscan.file.malicious', 'critical'));
        $this->assertSame('kritis', $this->topic('secscan.agent.offline', 'critical'));
    }

    public function test_ip_blocks_stay_in_keamanan(): void
    {
        $this->assertSame('keamanan', $this->topic('secscan.ip.autoblocked', 'warning'));
    }
}
