<?php

if (($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '') !== getenv('DEPLOY_HOOK_TOKEN')) {
    http_response_code(403);
    exit('Forbidden');
}

$root = dirname(__DIR__);
$output = [];

exec("cd {$root} && php bin/console cache:clear --env=prod --no-debug 2>&1", $output);
exec("cd {$root} && php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>&1", $output);

http_response_code(200);
echo implode("\n", $output);
