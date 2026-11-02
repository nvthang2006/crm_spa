<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commission_rules')
            ->where('notes', 'Quy tắc mặc định của cơ sở An Miên')
            ->update(['notes' => 'Quy tắc mặc định của cơ sở Sunny Beauty']);
    }

    public function down(): void
    {
        DB::table('commission_rules')
            ->where('notes', 'Quy tắc mặc định của cơ sở Sunny Beauty')
            ->update(['notes' => 'Quy tắc mặc định của cơ sở An Miên']);
    }
};
