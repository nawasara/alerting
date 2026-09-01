<?php

namespace Nawasara\Alerting\Tests;

use Nawasara\Alerting\Listeners\SyncJobFinalFailedListener;
use Nawasara\Sync\Models\SyncJob;
use PHPUnit\Framework\TestCase;

/**
 * Kunci identitas alert kegagalan sync.
 *
 * Alert dikenali dari gabungan (rule_key, target_type, target_id). Kalau
 * `target_id` berubah tiap percobaan, tiap kegagalan melahirkan alert BARU
 * alih-alih menyalakan ulang yang sudah ada — dan akibatnya berantai:
 *
 *   - alert lama tak pernah bisa dipulihkan (tak ada yang menunjuk ke sana),
 *   - acknowledge tak berguna: kegagalan berikutnya membuat state baru,
 *   - tiap state menua sendiri sampai batas max_renotify.
 *
 * Di produksi (2 September 2026) itu menumpuk jadi 5.894 state `firing` dan
 * 29.208 surel. Yang diuji di sini justru sifat yang membosankan — bahwa kunci
 * yang sama menghasilkan nilai yang sama — karena persis itu yang dulu tidak
 * dipenuhi, dan tidak ada satu pun galat yang menandainya.
 */
class SyncAlertTargetIdTest extends TestCase
{
    private function tracker(array $attrs = []): SyncJob
    {
        $t = new SyncJob;
        $t->forceFill(array_merge([
            'id' => random_int(1, 999999),   // BERBEDA tiap percobaan
            'service' => 'database-monitor',
            'action' => 'sync_inventory',
            'target_type' => 'DbServer',
            'target_id' => 'kmf-db',
        ], $attrs));

        return $t;
    }

    /** Inti perbaikannya: dua percobaan atas hal yang sama = satu identitas. */
    public function test_dua_percobaan_gagal_menghasilkan_kunci_yang_sama(): void
    {
        $a = SyncJobFinalFailedListener::targetIdFor($this->tracker(['id' => 101]));
        $b = SyncJobFinalFailedListener::targetIdFor($this->tracker(['id' => 202]));

        $this->assertSame($a, $b, 'Kunci tidak boleh bergantung pada id percobaan.');
    }

    /** Id baris percobaan tidak boleh ikut ke dalam kunci. */
    public function test_kunci_tidak_memuat_id_percobaan(): void
    {
        $kunci = SyncJobFinalFailedListener::targetIdFor($this->tracker(['id' => 987654]));

        $this->assertStringNotContainsString('987654', $kunci);
        $this->assertSame('database-monitor:sync_inventory:DbServer:kmf-db', $kunci);
    }

    /** Hal yang BERBEDA harus tetap terpisah — jangan sampai over-merge. */
    public function test_sasaran_berbeda_tetap_terpisah(): void
    {
        $dasar = SyncJobFinalFailedListener::targetIdFor($this->tracker());

        $berbeda = [
            'layanan lain' => ['service' => 'keycloak'],
            'aksi lain' => ['action' => 'sync_users'],
            'jenis sasaran lain' => ['target_type' => 'Realm'],
            'sasaran lain' => ['target_id' => 'db-02'],
        ];

        foreach ($berbeda as $nama => $attrs) {
            $this->assertNotSame(
                $dasar,
                SyncJobFinalFailedListener::targetIdFor($this->tracker($attrs)),
                "Kegagalan dengan $nama seharusnya jadi alert tersendiri."
            );
        }
    }

    /** Kolom kosong tidak boleh membuat dua hal berbeda tampak sama. */
    public function test_kolom_kosong_tetap_menghasilkan_kunci_stabil(): void
    {
        $kunci = SyncJobFinalFailedListener::targetIdFor($this->tracker([
            'target_type' => null,
            'target_id' => null,
        ]));

        $this->assertSame('database-monitor:sync_inventory:-:-', $kunci);

        // Dan tetap berbeda dari sasaran yang benar-benar ada.
        $this->assertNotSame(
            $kunci,
            SyncJobFinalFailedListener::targetIdFor($this->tracker()),
        );
    }
}
