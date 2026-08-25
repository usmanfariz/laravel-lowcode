<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 100)->default('general');
            $table->string('key_name', 150)->unique();
            $table->text('value')->nullable();
            $table->enum('value_type', ['string', 'integer', 'boolean', 'json', 'file'])->default('string');
            $table->string('label', 150)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_public')->default(false); // boleh dibaca tanpa login
            $table->timestamps();

            $table->index('group_name');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 50);              // login, create, update, delete, export, print
            $table->string('module', 100)->nullable(); // forms.code / reports.code
            $table->string('table_name', 150)->nullable();
            $table->string('record_id', 100)->nullable();
            $table->string('description', 255)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['table_name', 'record_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
    }
};
