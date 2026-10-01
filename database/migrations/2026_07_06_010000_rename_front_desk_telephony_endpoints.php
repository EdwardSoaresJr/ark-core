<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephony_endpoints')) {
            return;
        }

        $renames = [
            'Front Desk Right SIP' => 'Front Desk Right',
            'Front Desk Left SIP' => 'Front Desk Left',
            'Service Desk SIP' => 'Front Desk Left',
            'Front Desk SIP' => 'Front Desk Right',
            'Service Desk' => 'Front Desk Left',
            'Front Desk' => 'Front Desk Right',
        ];

        foreach ($renames as $from => $to) {
            DB::table('telephony_endpoints')
                ->where('name', $from)
                ->update(['name' => $to]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('telephony_endpoints')) {
            return;
        }

        $renames = [
            'Front Desk Right' => 'Front Desk SIP',
            'Front Desk Left' => 'Service Desk SIP',
        ];

        foreach ($renames as $from => $to) {
            DB::table('telephony_endpoints')
                ->where('name', $from)
                ->where('type', 'sip')
                ->update(['name' => $to]);
        }
    }
};
