<?php
// DIC configuration

use Sirius\Upload\Handler as UploadHandler;

$container = $app->getContainer();

$container['upload_directory'] = __DIR__ . '../public/uploads';

$container['keygen'] = function () {
    return bin2hex(random_bytes(16));
};

$container['view'] = function (\Slim\Container $c) {
    $settings = $c->get('settings')['view'];
    $view = new \Slim\Views\Twig($settings['template_path'], $settings['twig']);
    // Add extensions
    $view->addExtension(new Slim\Views\TwigExtension($c->get('router'), $c->get('request')->getUri()));
    $view->addExtension(new Twig_Extension_Profiler($c['twig_profile']));
    $view->addExtension(new Twig_Extension_Debug());
    return $view;
};

$container['logger'] = function() {
    $logger = new \Monolog\Logger('novashare');
    //$file_handler = new \Monolog\Handler\StreamHandler("logs/app.log");
    //$logger->pushHandler($file_handler);
    return $logger;
};

$container['session'] = function () {
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

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param int|string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @return String containing either just a URL or a complete image tag
 * @source https://gravatar.com/site/implement/images/php/
 */
function get_gravatar($email, $s = 80, $d = 'identicon', $r = 'g') {
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5( strtolower( trim( $email ) ) );
    $url .= "?s=$s&d=$d&r=$r";
    return $url;
}
