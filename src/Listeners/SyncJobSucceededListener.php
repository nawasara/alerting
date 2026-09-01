<?php

namespace Nawasara\Alerting\Listeners;

use Illuminate\Support\Str;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Sync\Events\SyncJobSucceeded;

/**
 * Menyelesaikan alert kegagalan sync begitu sinkronisasinya pulih.
 *
 * ## Kenapa ini perlu ada
 *
 * Alert kegagalan sync tidak punya jalan untuk selesai. Ia menyala saat gagal,
 * lalu **menyala selamanya** — termasuk setelah percobaan berikutnya berhasil
 * beberapa menit kemudian. Tidak ada yang menutupnya, karena nawasara/sync
 * dulu hanya memancarkan kegagalan.
 *
 * Di produksi (2 September 2026): 5.894 state masih `firing`, semuanya dari
 * kegagalan yang sudah lama pulih, dan yang terbaru pun sudah sepuluh hari
 * berlalu. Kerugiannya bukan pada jumlah surelnya, melainkan pada lencana
 * "firing" yang berhenti berarti apa-apa — operator berhenti membacanya, dan
 * kegagalan yang benar-benar baru ikut tenggelam.
 *
 * Pasangannya [SyncJobFinalFailedListener], dan keduanya HARUS memakai
 * penyusun kunci yang sama — itulah sebabnya kunci itu dibangun di satu tempat
 * (`SyncJobFinalFailedListener::targetIdFor()`) dan dipakai kedua sisi. Kunci
 * yang berbeda sedikit saja membuat pemulihan menunjuk ke alert yang tidak
 * pernah ada, dan gejalanya persis seperti tidak ada perbaikan sama sekali.
 */
class SyncJobSucceededListener
{
    public function handle(SyncJobSucceeded $event): void
    {
        $tracker = $event->tracker;
        $service = $tracker->service ?: 'unknown';

        // Cermin dari penjagaan di listener kegagalan: jangan pernah bereaksi
        // pada sync milik alerting sendiri.
        if (Str::startsWith($service, 'alerting')) {
            return;
        }

        $ruleKey = "sync.job.failed.{$service}";

        // ⚠️ Aturan sync didaftarkan MALAS — baru ada setelah kegagalan
        // pertama DI PROSES ITU, dan daftarnya hanya hidup di memori. Dua
        // akibatnya, dan keduanya harus ditangani:
        //
        //   1. Alerter::resolve() MELEMPAR UnknownAlertRule bila aturannya
        //      belum terdaftar. Tanpa penjagaan, tiap sync yang BERHASIL akan
        //      melempar galat — perbaikan banjir notifikasi malah merusak
        //      sinkronisasinya sendiri.
        //   2. Pekerja antrean yang belum pernah melihat kegagalan tidak akan
        //      pernah punya aturannya, sehingga alert yang menyala dari PROSES
        //      LAIN tak pernah bisa dipulihkan — dan gejalanya persis seperti
        //      perbaikan ini tidak berfungsi sama sekali.
        //
        // Karena itu aturannya didaftarkan di sini juga, bukan sekadar
        // dilewati. Mendaftarkannya murah dan tidak menimbulkan efek samping:
        // aturan hanya menjelaskan CARA memberitakan, bukan menyalakan apa pun.
        SyncJobFinalFailedListener::ensureRule($service);

        // Idempoten: menyelesaikan sesuatu yang tidak menyala, atau yang tidak
        // pernah ada, adalah operasi kosong.
        Alerter::resolve(
            $ruleKey,
            'SyncJob',
            SyncJobFinalFailedListener::targetIdFor($tracker),
        );
    }
}
