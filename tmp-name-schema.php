<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = Illuminate\Support\Facades\DB::select(
    "SELECT character_maximum_length AS name_max
     FROM information_schema.columns
     WHERE table_schema = current_schema()
       AND table_name = 'users' AND column_name = 'name'"
);
echo 'users.name='.$rows[0]->name_max.PHP_EOL;
$views = Illuminate\Support\Facades\DB::select(
    "SELECT c.relname, c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relname IN ('deleted_sk_officials', 'deleted_sk_federations')"
);
foreach ($views as $view) {
    echo $view->relname.' kind='.$view->relkind.PHP_EOL;
}
echo (preg_match('/^(?!\s)[\p{L}.\-\']+(?: [\p{L}.\-\']+)*$/u', 'ÑUÑEZ') ? 'enye-ok' : 'enye-fail').PHP_EOL;
