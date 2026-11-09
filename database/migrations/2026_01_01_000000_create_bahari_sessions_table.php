<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahari_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 10);
            $table->uuid('subject')->index();
            $table->string('user_id')->index();
            $table->text('tokens');
            $table->text('profile');
            $table->timestamp('access_expires_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahari_sessions');
    }
};
