<?php

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

if (($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '') !== ($_ENV['DEPLOY_HOOK_TOKEN'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}

$root = dirname(__DIR__);
$output = [];

exec("cd {$root} && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts 2>&1", $output);
exec("cd {$root} && php bin/console cache:clear --env=prod --no-debug 2>&1", $output);
exec("cd {$root} && php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>&1", $output);

http_response_code(200);
echo implode("\n", $output);
