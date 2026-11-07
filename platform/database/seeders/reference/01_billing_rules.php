<?php

use Illuminate\Support\Facades\DB;

// Qlobal billing qaydaları sətri (testlərdə TRUNCATE-dən sonra bərpa olunur)
return function () {
    DB::connection('pgsql_owner')->table('billing_rules')->insertOrIgnore(['course_id' => null, 'created_at' => now(), 'updated_at' => now()]);
};
