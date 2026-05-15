<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // Business Profile
            $table->string('business_name');
            $table->string('registration_number')->unique()->nullable();
            $table->string('status'); // Managed by spatie/laravel-model-states
            $table->decimal('commission_rate', 5, 2)->default(10.00);

            // Payment Credentials (ABA, Bakong, Wing)
            $table->string('aba_merchant_id')->nullable();
            $table->string('aba_api_key')->nullable();
            $table->string('bakong_account_id')->nullable(); // e.g., seangly@aba
            $table->string('bakong_merchant_name')->nullable();
            $table->string('wing_account_number')->nullable();
            $table->enum('preferred_payout_method', ['aba', 'bakong', 'wing'])->default('bakong');

            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
