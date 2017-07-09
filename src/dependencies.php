<?php
// DIC configuration

$container = $app->getContainer();

$container['prefix'] = "/share";

$container['upload_directory'] = __DIR__ . '../public/uploads';

$container['keygen'] = function () {
    return bin2hex(random_bytes(16));
};

$container['view'] = function (\Slim\Container $c) {
    $settings = $c->get('settings')['view'];
    $view = new \Slim\Views\Twig($settings['template_path'], $settings['twig']);
    $view->parserExtensions = [
        new \Slim\Views\TwigExtension(),
        new \Twig_Extension_Debug()
    ];
    // Add extensions
    $view->addExtension(new Slim\Views\TwigExtension($c->get('router'), $c->get('request')->getUri()));
    // add global
    $view->offsetSet('prefix', "/share");
    return $view;
};

$container['logger'] = function() {
    $logger = new \Monolog\Logger('novashare');
    //$file_handler = new \Monolog\Handler\StreamHandler("logs/app.log");
    //$logger->pushHandler($file_handler);
    return $logger;
};

$container['session'] = function ($c) {
    return new \SlimSession\Helper;
};

$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('templates', ['debug' => true ]);

    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));
    $view->addExtension(new Twig_Extension_Debug());

    return $view;
};

$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

$container['session'] = function () {
    return new \SlimSession\Helper;
};

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};
