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
        // Modify the enum to include 'super_admin' and 'therapist'
        \DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'cleaner', 'staff', 'super_admin', 'therapist') DEFAULT 'customer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        \DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'cleaner', 'staff') DEFAULT 'customer'");
    }
};
