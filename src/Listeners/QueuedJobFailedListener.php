<?php

namespace Nawasara\Alerting\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Str;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;

/**
 * Jaring terakhir: SETIAP job antrean yang gagal habis-habisan.
 *
 * ## Kenapa perlu, padahal sudah ada sync.job.failed
 *
 * `sync.job.failed.{service}` hanya menjaring job turunan `AbstractSyncJob`.
 * Job lain lolos begitu saja — dan justru beberapa di antaranya yang paling
 * berbahaya bila diam:
 *
 *   CheckSlaJob            batas waktu laporan warga berhenti dihitung
 *   CheckVerificationDueJob  pemeriksaan tak pernah ditagih
 *   CheckCapacityJob       peringatan disk penuh hilang — job yang justru
 *                          dibuat untuk mencegah basis data mati
 *   EvaluateIncidentJob    blokir otomatis berhenti dinilai
 *
 * Diperiksa 4 September 2026: **1.320 baris di `failed_jobs`**, terbaru hari
 * itu juga, tanpa satu pun peringatan sepanjang riwayatnya.
 *
 * Ironinya paling tajam pada `CheckCapacityJob`: ia ada supaya disk penuh
 * ketahuan lebih awal, tetapi bila ia sendiri mati, tidak ada yang tahu bahwa
 * pengawasannya berhenti.
 *
 * ## Kunci alert = KELAS job, bukan percobaannya
 *
 * Satu job yang gagal seratus kali menyalakan SATU peringatan yang
 * `fire_count`-nya naik, bukan seratus peringatan yang tak satu pun dapat
 * dipulihkan. Pelajaran dari `sync.job.failed` yang sempat menumpuk 5.278
 * state karena kuncinya memakai id percobaan.
 */
class QueuedJobFailedListener
{
    public function handle(JobFailed $event): void
    {
        if (! config('nawasara-alerting.queue_failure.enabled', true)) {
            return;
        }

        $jobName = $event->job->resolveName();

        // Job milik alerting sendiri tidak boleh memicu alert — pengiriman
        // notifikasi yang gagal lalu menyalakan notifikasi baru adalah cara
        // tercepat membuat antrean berputar melawan dirinya sendiri.
        if (Str::contains($jobName, ['Alerting', 'Notification'])) {
            return;
        }

        // Sync sudah punya jaringnya sendiri, lengkap dengan pemulihan saat
        // sinkronisasi berhasil lagi. Membiarkan keduanya menyala berarti dua
        // peringatan untuk satu kegagalan.
        if (Str::contains($jobName, 'Nawasara\Sync')) {
            return;
        }

        $ruleKey = 'queue.job.failed';

        if (! Alerter::hasRule($ruleKey)) {
            Alerter::registerRule(AlertRule::make([
                'key' => $ruleKey,
                'severity' => config('nawasara-alerting.queue_failure.severity', 'critical'),
                'category' => 'antrean',

                // Sekali sehari per job. Job yang rusak akan terus gagal tiap
                // kali dijadwalkan; mengabarkannya tiap kali hanya membuat
                // yang BARU rusak ikut tenggelam.
                'cooldown_minutes' => (int) config('nawasara-alerting.queue_failure.cooldown_minutes', 1440),
                'description' => 'Job antrean gagal setelah seluruh percobaan habis',
                'subject_template' => 'Job gagal: {context.job_pendek} — {context.error_short}',
            ]));
        }

        $pesan = $event->exception?->getMessage() ?? '';
        $kelas = $event->exception ? $event->exception::class : null;

        // MaxAttemptsExceededException hampir tidak pernah berarti "job-nya
        // rusak" — ia berarti job DIBUNUH TIMEOUT, dan pesannya ("has been
        // attempted too many times") tidak menyebutkan itu sama sekali.
        //
        // Laravel memakai nilai TERKECIL antara --timeout pekerja dan $timeout
        // job. Tanpa petunjuk ini, pembacanya akan mencari bug di dalam job
        // padahal yang perlu dinaikkan adalah batas waktunya.
        $petunjuk = $kelas === MaxAttemptsExceededException::class
            ? ' — kemungkinan dibunuh timeout, bukan galat di dalam job; '
                .'periksa $timeout job dan --timeout pekerja antrean'
            : '';

        Alerter::fire($ruleKey, 'QueuedJob', $jobName, [
            'label' => $jobName,

            // Nama kelas berkualifikasi penuh memenuhi baris subjek di ponsel
            // dan yang terpotong justru bagian akhirnya — padahal itu yang
            // membedakan CheckSlaJob dari CheckCapacityJob.
            'job_pendek' => class_basename($jobName),
            'job' => $jobName,
            'antrean' => $event->job->getQueue(),
            'percobaan' => $event->job->attempts(),
            'error_short' => Str::limit($pesan, 120, '…').$petunjuk,
            'exception' => $kelas,
        ]);
    }
}
