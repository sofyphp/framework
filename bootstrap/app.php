<?php

declare(strict_types=1);

use Sofy\Core\Application;

$app = new Application(dirname(__DIR__));

// Detect locale from cookie; validated against the supported list
$_sofyLocale = $_COOKIE['app_locale'] ?? 'en';
if (!in_array($_sofyLocale, ['en', 'ru'], true)) {
    $_sofyLocale = 'en';
}
\Sofy\Support\Lang::setLocale($_sofyLocale);
unset($_sofyLocale);

/*
|--------------------------------------------------------------------------
| Регистрация синглтонов и привязок
|--------------------------------------------------------------------------
| Здесь регистрируйте сервисы, которые должны быть доступны через контейнер.
|
| Пример:
|   $app->singleton(\Main\Services\MailService::class, fn() => new \Main\Services\MailService(
|       env('MAIL_HOST'), env('MAIL_PORT')
|   ));
*/

/*
|--------------------------------------------------------------------------
| Модули
|--------------------------------------------------------------------------
| Автоматически обнаруживает все модули из директории modules/.
| Каждый модуль вызывает register() до загрузки роутов и boot().
*/
$app->loadModules();

/*
|--------------------------------------------------------------------------
| Загрузка роутов и БД
|--------------------------------------------------------------------------
*/
$app->boot();

return $app;
