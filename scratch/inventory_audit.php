<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::connection('pgsql_system')->select("
    SELECT table_name 
    FROM information_schema.tables 
    WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
    ORDER BY table_name;
");

$tableList = array_map(fn($t) => $t->table_name, $tables);

echo "Total tables in public schema: " . count($tableList) . "\n\n";

foreach ($tableList as $t) {
    // Check if school_id column exists in table
    $hasSchoolId = DB::connection('pgsql_system')->getSchemaBuilder()->hasColumn($t, 'school_id');
    
    // Check if RLS is enabled on table
    $rlsInfo = DB::connection('pgsql_system')->select("
        SELECT relrowsecurity, relforcerowsecurity 
        FROM pg_class 
        WHERE relname = '{$t}'
    ");
    $rlsEnabled = !empty($rlsInfo) ? ($rlsInfo[0]->relrowsecurity ? 'YES' : 'NO') : 'N/A';

    // Check policies on table
    $policies = DB::connection('pgsql_system')->select("
        SELECT polname 
        FROM pg_policy p 
        JOIN pg_class c ON c.oid = p.polrelid 
        WHERE c.relname = '{$t}'
    ");
    $policyNames = implode(', ', array_map(fn($p) => $p->polname, $policies));

    echo sprintf(
        "%-32s | school_id: %-3s | RLS: %-3s | Policies: %s\n",
        $t,
        $hasSchoolId ? 'YES' : 'NO',
        $rlsEnabled,
        $policyNames ?: 'None'
    );
}
