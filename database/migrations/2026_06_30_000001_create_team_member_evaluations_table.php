<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_member_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(3);
            $table->unsignedTinyInteger('productivity_score')->default(3);
            $table->unsignedTinyInteger('teamwork_score')->default(3);
            $table->unsignedTinyInteger('communication_score')->default(3);
            $table->unsignedTinyInteger('reliability_score')->default(3);
            $table->text('summary')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'member_id']);
            $table->index(['evaluator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_member_evaluations');
    }
};
