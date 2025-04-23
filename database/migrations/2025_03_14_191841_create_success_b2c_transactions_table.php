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
        Schema::create('success_b2c_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id');
            $table->string('transaction_id');
            $table->string('originator_conversation_id');
            $table->integer('result_code');
            $table->string('result_desc');
            $table->decimal('amount', 10, 2);
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->timestamp('transaction_date');
            $table->string('account_reference')->nullable(); // From your requestData
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('success_b2c_transactions');
    }
};
