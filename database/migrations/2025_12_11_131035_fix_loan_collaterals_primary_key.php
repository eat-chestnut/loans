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
        Schema::table('loan_collaterals', function (Blueprint $table) {
            // Drop existing primary key
            $table->dropPrimary();
            
            // Make collateral_id NOT NULL
            $table->integer('collateral_id')->nullable(false)->change();
            
            // Add composite primary key
            $table->primary(['loan_id', 'collateral_id']);
            
            // Add index for collateral_id for better performance
            $table->index('collateral_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_collaterals', function (Blueprint $table) {
            // Drop composite primary key
            $table->dropPrimary();
            
            // Drop index on collateral_id
            $table->dropIndex(['collateral_id']);
            
            // Make collateral_id nullable again
            $table->integer('collateral_id')->nullable()->change();
            
            // Add back the old primary key
            $table->primary('loan_id');
        });
    }
};
