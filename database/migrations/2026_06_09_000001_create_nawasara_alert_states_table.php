<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_alert_states', function (Blueprint $table) {
            $table->id();

            // Identity: a rule firing against an optional target.
            // (rule_key, target_type, target_id) is the natural key.
            $table->string('rule_key', 191);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable();

            $table->enum('status', ['ok', 'firing'])->default('firing');
            $table->enum('severity', ['critical', 'warning', 'info'])->index();

            $table->timestamp('fired_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();

            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('silenced_until')->nullable();
            $table->foreignId('silenced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('silenced_reason', 255)->nullable();

            // fire_count grows over the lifetime of the firing state — useful
            // for escalation policies that read "how many times have we yelled
            // about this without resolution".
            $table->unsignedInteger('fire_count')->default(1);

            // Free-form snapshot from the caller — node name, disk percent,
            // HTTP error count, whatever the rule wants to surface in the
            // email body / UI detail.
            $table->json('context')->nullable();

            $table->timestamps();

            $table->unique(
                ['rule_key', 'target_type', 'target_id'],
                'alert_states_natural_key',
            );

            // EscalateStaleAlertsJob query: firing states ordered by next
            // re-notify candidacy.
            $table->index(['status', 'last_notified_at'], 'alert_states_renotify_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_alert_states');
    }
};
