<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The default table read by Imran\DynamicConfig\Drivers\DatabaseDriver.
     * `key` uses Laravel dot-notation (e.g. "services.stripe.key") and is
     * unique so a single row maps to exactly one config path.
     */
    public function up(): void
    {
        Schema::create('app_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_configs');
    }
};
