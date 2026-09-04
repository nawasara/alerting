<?php

namespace Nawasara\Alerting\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Jaring terakhir untuk job antrean yang gagal.
 *
 * `sync.job.failed.{service}` hanya menjaring turunan AbstractSyncJob; job lain
 * lolos sepenuhnya. Per 4 September 2026 `failed_jobs` berisi **1.320 baris**
 * tanpa satu pun peringatan sepanjang riwayatnya.
 *
 * Yang paling merugikan bila diam: `CheckSlaJob` (batas waktu laporan warga
 * berhenti dihitung) dan `CheckCapacityJob` — job yang justru dibuat untuk
 * mencegah basis data mati karena disk penuh. Bila ia sendiri mati, tidak ada
 * yang tahu bahwa pengawasannya berhenti.
 */
class QueuedJobFailureTest extends TestCase
{
    /** Cerminan penyaringan di QueuedJobFailedListener::handle(). */
    private function ditangani(string $job): bool
    {
        foreach (['Alerting', 'Notification'] as $sendiri) {
            if (str_contains($job, $sendiri)) {
                return false;
            }
        }

        return ! str_contains($job, 'Nawasara\\Sync');
    }

    /** Job yang selama ini lolos — inti perbaikannya. */
    public function test_job_di_luar_sync_ditangani(): void
    {
        foreach ([
            'Nawasara\Aspirations\Jobs\CheckSlaJob',
            'Nawasara\Aspirations\Jobs\CheckVerificationDueJob',
            'Nawasara\Proxmox\Jobs\CheckCapacityJob',
            'Nawasara\Secscan\Jobs\EvaluateIncidentJob',
        ] as $job) {
            $this->assertTrue($this->ditangani($job), "{$job} seharusnya ikut terjaring.");
        }
    }

    /**
     * Job sync DILEWATI — ia sudah punya jaringnya sendiri, lengkap dengan
     * pemulihan saat sinkronisasi berhasil lagi. Membiarkan keduanya menyala
     * berarti dua peringatan untuk satu kegagalan.
     */
    public function test_job_sync_dilewati(): void
    {
        $this->assertFalse($this->ditangani('Nawasara\Sync\Jobs\SyncFooJob'));
    }

    /**
     * Job alerting dan notification DILEWATI — mencegah lingkaran.
     *
     * Pengiriman notifikasi yang gagal lalu menyalakan notifikasi baru adalah
     * cara tercepat membuat antrean berputar melawan dirinya sendiri.
     */
    public function test_job_milik_alerting_sendiri_dilewati(): void
    {
        $this->assertFalse($this->ditangani('Nawasara\Notification\Jobs\SendNotificationJob'));
        $this->assertFalse($this->ditangani('Nawasara\Alerting\Jobs\EscalateStaleAlertsJob'));
    }

    /**
     * Nama kelas dipendekkan untuk subjek.
     *
     * Nama berkualifikasi penuh memenuhi baris subjek di ponsel dan yang
     * terpotong justru bagian akhirnya — padahal itu yang membedakan
     * CheckSlaJob dari CheckCapacityJob.
     */
    public function test_nama_job_dipendekkan_untuk_subjek(): void
    {
        $pendek = fn (string $j) => substr(strrchr($j, '\\') ?: $j, 1) ?: $j;

        $this->assertSame('CheckSlaJob', $pendek('Nawasara\Aspirations\Jobs\CheckSlaJob'));
        $this->assertNotSame(
            $pendek('Nawasara\Aspirations\Jobs\CheckSlaJob'),
            $pendek('Nawasara\Proxmox\Jobs\CheckCapacityJob'),
        );
    }

    /**
     * Job gagal masuk topik Sinkronisasi, BUKAN Kritis.
     *
     * Severity-nya `critical` dengan sengaja — job yang mati diam-diam
     * mematikan hal lain yang bergantung padanya. Tetapi tempatnya bukan di
     * Kritis: `ScanWordpressJob` yang gagal berulang adalah pekerjaan
     * pemeliharaan, bukan panggilan bangun tengah malam.
     *
     * Ketahuan dari produksi: alert pertama yang masuk justru mendarat di
     * Kritis, karena severity diperiksa lebih dulu daripada asalnya.
     */
    public function test_job_gagal_masuk_topik_sinkronisasi_bukan_kritis(): void
    {
        $topik = function (string $key, string $severity): string {
            if (str_starts_with($key, 'uptime.')) {
                return 'ketersediaan';
            }

            if (str_starts_with($key, 'queue.')) {
                return 'sinkronisasi';
            }

            return $severity === 'critical' ? 'kritis' : 'pengumuman';
        };

        $this->assertSame('sinkronisasi', $topik('queue.job.failed', 'critical'));
        $this->assertNotSame('kritis', $topik('queue.job.failed', 'critical'));

        // Yang memang layak membangunkan orang tetap di Kritis.
        $this->assertSame('kritis', $topik('secscan.agent.offline', 'critical'));
    }

    /**
     * MaxAttemptsExceededException berarti DIBUNUH TIMEOUT, bukan job rusak.
     *
     * Pesannya ("has been attempted too many times") tidak menyebutkan itu
     * sama sekali, sehingga pembacanya mencari bug di dalam job padahal yang
     * perlu dinaikkan adalah batas waktunya.
     */
    public function test_kegagalan_timeout_diberi_petunjuk(): void
    {
        $petunjuk = fn (string $kelas) => $kelas === 'Illuminate\Queue\MaxAttemptsExceededException'
            ? 'timeout'
            : '';

        $this->assertSame('timeout', $petunjuk('Illuminate\Queue\MaxAttemptsExceededException'));
        $this->assertSame('', $petunjuk('RuntimeException'));
    }
}
