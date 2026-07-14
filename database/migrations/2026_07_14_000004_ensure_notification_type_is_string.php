<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE in_app_notifications MODIFY type VARCHAR(64) NOT NULL');

            return;
        }

        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->string('type', 64)->change();
        });
    }

    public function down(): void
    {
        // Notification types are intentionally extensible and should not be narrowed again.
    }
};
