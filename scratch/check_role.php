<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::connection('pgsql')->statement("SELECT set_config('app.current_school_id', '19', false)");
DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false)");

$testEval = DB::connection('pgsql')->select("
    SELECT id, school_id, 
           get_current_school_id() as fn_school_id, 
           current_setting('app.bypass_rls', true) as bypass_setting,
           session_user as s_user,
           (school_id = get_current_school_id()) as match_school,
           (current_setting('app.bypass_rls', true) = 'on' AND session_user = 'app_system') as match_bypass
    FROM students
    LIMIT 2
");

var_dump($testEval);
