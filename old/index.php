<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

// slim configuration
$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host'] = "us-cdbr-iron-east-04.cleardb.net";
$config['db']['user'] = "b53709aeab0eca";
$config['db']['pass'] = "b4d912a7";
$config['db']['dbname'] = "heroku_d087f0750fc40cf";
$config['debug'] = true;

error_reporting(E_ERROR);

$app = new \Slim\App(["settings" => $config]);
session_start();

$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware);

$app->add(new \Slim\Middleware\Session([
    'name' => 'novashare',
    'autorefresh' => true,
    'lifetime' => '1 day'
]));

$container = $app->getContainer();

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

$app->get('/', function (Request $request, Response $response) {
    $this->logger->addInfo($request->getUri());

    if (isset($this->session->get('access_token')['access_token'])) {
        return $response->withStatus(302)->withHeader('Location', '/home');
    }
    return $this->view->render($response, 'index.html',
    ["login_url" => $this->google->createAuthUrl(),
    "signed_in" => $this->session->get('signed_in'),
    "messages" => $this->flash->getMessages()]);
});

$app->get('/failed-setup', function (Request $request, Response $response) {
    $this->logger->addInfo("A user failed to login because of a Google refresh_token issue.");
    $this->flash->addMessage("Error", 'There was an error signing in due to your refresh_token being invalid. Please go to <a target="_blank" href="https://myaccount.google.com/permissions">your Google account preferences</a>, remove "FCTS IRP Book Manager" from the list of applications that you have approved, and sign in again.');
    return $response->withStatus(302)->withHeader('Location', '/');
});

$app->get('/auth', function (Request $request, Response $response) {
    $this->logger->addInfo("Authorizing user.");
    $params = $request->getQueryParams();
    // check if error was returned from google
    if (isset($params['code'])) {
        $this->google->authenticate($params['code']);
        /*if (empty($this->emails)) {
            // allow the first user to sign in so they can add/remove others.
            $statement = $this->db->prepare('INSERT INTO allowed_emails (email) VALUES (:email)');
            $statement->execute(array(
                "email" => $this->plus->people->get('me')->getEmails()[0]['value']
            ));
        } else if (!in_array($this->plus->people->get('me')->getEmails()[0]['value'], $this->emails)) {
            $this->flash->addMessage("Error", "You are not allowed to access this resource. This incident has been logged.");
            $this->logger->addinfo(var_export($this->emails));
            $statement = $this->db->prepare('INSERT INTO audit_log (type, date_occured, `text`) VALUES (:type, CURDATE(), :txt)');
            $statement->execute(array(
                "type" => "Unauthorized Login",
                "txt" => "The user " . $this->plus->people->get('me')->getEmails()[0]['value'] . " tried to login but was not on the authorized users list.",
            ));
            return $response->withStatus(302)->withHeader('Location', '/');
        }*/
        $this->session->set('signed_in', true);
        $this->session->set('id', $this->plus->people->get('me')->getId());
        $this->session->set('access_token', $this->google->getAccessToken());
        $statement = $this->db->prepare('SELECT id FROM `google_teachers` WHERE id LIKE :id');
        $statement->execute(array('id' => $this->plus->people->get('me')->getId()));
        if ($statement->rowCount() === 0) {
            $statement = $this->db->prepare('INSERT INTO google_teachers (id, firstname, lastname, email, refresh_token) VALUES (:id, :firstname, :lastname, :email, :refresh_token)');
            $name = explode(" ", $this->plus->people->get('me')->getDisplayName());
            if (!$this->google->getRefreshToken()) {
                $this->session->destroy();
                return $response->withStatus(302)->withHeader('Location', '/failed-setup');
            }
            $statement->execute(array(
                'id' => $this->plus->people->get('me')->getId(),
                'firstname' => $name[0],
                'lastname' => $name[1],
                'email' => $this->plus->people->get('me')->getEmails()[0]['value'],
                'refresh_token' => $this->google->getRefreshToken()));
            $this->session->set('access_token', $this->google->getAccessToken());
            $this->session->set('refresh_token', $this->google->getRefreshToken());
        } else {
            $statement = $this->db->prepare('SELECT refresh_token FROM google_teachers WHERE id=:id');
            $statement->execute(array('id' => $this->plus->people->get('me')->getId()));
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);
            $this->session->set('access_token', $this->google->getAccessToken());
            if ($this->google->getRefreshToken()) {
                $statement = $this->db->prepare('UPDATE google_teachers SET refresh_token=:token WHERE id=:id');
                $statement->execute(array(
                    'id' => $this->plus->people->get('me')->getId(),
                    'token' => $this->google->getRefreshToken()
                ));
                $this->session->set('access_token', $this->google->getAccessToken());
                $this->session->set('refresh_token', $this->google->getRefreshToken());
            }
            $this->session->set('refresh_token', $results[0]);
        }
        return $response->withStatus(302)->withHeader('Location', '/home');
    } else {
        $this->flash->addMessage("Error", "There was an error signing in. Please try again.");
        return $response->withStatus(302)->withHeader('Location', '/');
    }
});
$app->get('/logout', function (Request $request, Response $response) {
    $this->logger->addInfo("Logging out user.");
    $this->flash->addMessage("Success", "You have successfully been signed out.");
    $this->session->destroy();
    return $response->withStatus(302)->withHeader('Location', '/');
});

$app->get('/home', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html',
    ["person" => $this->plus->people->get('me'),
    "signed_in" => $this->session->get('signed_in'),
    "messages" => $this->flash->getMessages(),
    "title" => "Dashboard"]);
});

$app->get('/pop', function (Request $request, Response $response) {
    $this->session->destroy();
    return $response->withStatus(302)->withHeader('Location', '/');
});

$app->get('/search/books', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (isset($params['query'])) {
        $statement = $this->db->prepare('SELECT `title`, `author`, `pages` FROM `books` WHERE title LIKE concat("%", :query, "%") OR author LIKE concat("%", :query, "%") LIMIT 10');
        $statement->execute(array('query' => $params['query']));
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $response->withJson($results);
    } else {
        $data = array('error' => "invalid_query", "params" => $params);
        return $response->withJson($data, 400);
    }
});

$app->get('/search/students', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (isset($params['query'])) {
        $statement = $this->db->prepare('SELECT * FROM students WHERE studentid LIKE concat("%", :query, "%") OR firstname LIKE concat("%", :query, "%") OR lastname LIKE concat("%", :query, "%") OR email LIKE concat("%", :query, "%") LIMIT 10');
        $statement->execute(array('query' => $params['query']));
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $response->withJson($results);
    } else {
        $data = array('error' => "invalid_query", "params" => $params);
        return $response->withJson($data, 400);
    }
});

$app->get('/students/get', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (isset($params['query'])) {
        $statement = $this->db->prepare('SELECT * FROM students WHERE studentid=:query LIMIT 10');
        $statement->execute(array('query' => $params['query']));
        if ($statement->rowCount() === 0) {
            $data = array('error' => "student_not_found");
            return $response->withJson($data, 400);
        }
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $response->withJson($results);
    } else {
        $data = array('error' => "invalid_query", "params" => $params);
        return $response->withJson($data, 400);
    }
});

$app->get('/teachers/list', function (Request $request, Response $response, $params) {
    $statement = $this->db->prepare('SELECT id,firstname,lastname,email FROM google_teachers');
    //$statement->execute(array('query' => $params['query']));
    if ($statement->rowCount() === 0) {
        $data = array('error' => "no_teachers_found");
        return $response->withJson($data, 400);
    }
    $results = $statement->fetchAll(PDO::FETCH_ASSOC);
    return $response->withJson($results);
});

$app->get('/books/get', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (isset($params['query'])) {
        $statement = $this->db->prepare('SELECT * FROM books WHERE id=:query LIMIT 1');
        $statement->execute(array('query' => $params['query']));
        if ($statement->rowCount() === 0) {
            $data = array('error' => "book_not_found");
            return $response->withJson($data, 400);
        }
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $response->withJson($results);
    } else {
        $data = array('error' => "invalid_query", "params" => $params);
        return $response->withJson($data, 400);
    }
});

$app->get('/students/booksread', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (isset($params['query'])) {
        $statement = $this->db->prepare('SELECT `booksread`.`studentid`, `booksread`.`bookid`, `booksread`.`teacherid`, `booksread`.`approvaldate`, `booksread`.`conferencedate`, `books`.`title`, `books`.`author`, `google_teachers`.`lastname` FROM `booksread` LEFT JOIN `google_teachers` ON `booksread`.`teacherid` = `google_teachers`.`id` LEFT JOIN `books` ON `booksread`.`bookid` = `books`.`id` WHERE booksread.studentid=:query');
        $statement->execute(array('query' => $params['query']));
        if ($statement->rowCount() === 0) {
            $data = array('error' => "student_not_found");
            return $response->withJson($data, 404);
        }
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $response->withJson($results);
    } else {
        $data = array('error' => "invalid_query", "params" => $params);
        return $response->withJson($data, 400);
    }
});

$app->post('/students/booksread/save', function (Request $request, Response $response) {
    $params = $request->getParsedBody();
    try {
        $statement = $this->db->prepare('UPDATE booksread (teacherid, approvaldate, conferencedate, numofpages, abandoned, conf_grade, conf_notes, notes, rr_grade) VALUES () WHERE brid=:brid');
        $statement->execute(array(
        'brid' => $params["id"],
        'teacherid' => $params["studentname"],
        'approvaldate' => $params["approvaldate"],
        'conferencedate' => $params["conferencedate"],
        'numofpages' => $params["numofpages"],
        'abandoned' => $params["abandoned"],
        'conf_grade' => $params["conf_grade"],
        'conf_notes' => $params["conf_notes"],
        'notes' => $params["notes"],
        'rr_grade' => $params["rr_grade"]));
    } catch (PDOException $ex) {
        $data = array('error' => "internal_server_error", "ex" => $ex->getMessage());
        return $response->withJson($data, 500);
    }
    $data = array('error' => "operation_success");
    return $response->withJson($data, 500);
});

$app->get('/students', function (Request $request, Response $response) {

    return $this->view->render($response, 'students.html',
    ["person" => $this->plus->people->get('me'),
    "signed_in" => $this->session->get('signed_in'),
    "messages" => $this->flash->getMessages(),
    "title" => "Dashboard - Students"]);
});

$app->get('/students/add', function (Request $request, Response $response) {

    return $this->view->render($response, 'addastudent.html',
    ["person" => $this->plus->people->get('me'),
    "signed_in" => $this->session->get('signed_in'),
    "messages" => $this->flash->getMessages(),
    "title" => "Dashboard - Add a Student"]);
});

$app->post('/students/add', function (Request $request, Response $response) {
    $params = $request->getParsedBody();

    try {
        $statement = $this->db->prepare('INSERT INTO students (studentid, firstname, lastname, email, graduation, grade) VALUES (:studentid, :firstname, :lastname, :email, :graduation, :grade)');
        $statement->execute(array(
            'studentid' => $params["studentid"],
            'firstname' => explode(" ", $params["studentname"])[0],
            'lastname' => explode(" ", $params["student"])[1],
            'email' => $params["studentemail"],
            'graduation' => $params["studentgradyear"],
            'grade' => $params["studentgrade"]));
    } catch (PDOException $ex) {
        $this->flash->addMessage("Error", $ex->getMessage());
        return $response->withStatus(302)->withHeader('Location', '/books/create');
    }
    $this->flash->addMessage("Success", "You have successfully created a book.");
    return $response->withStatus(302)->withHeader('Location', '/books/create');
});

$app->get('/books', function (Request $request, Response $response) {

    return $this->view->render($response, 'books.html',
    ["person" => $this->plus->people->get('me'),
    "signed_in" => $this->session->get('signed_in'),
    "messages" => $this->flash->getMessages(),
    "title" => "Dashboard - Books"]);
});

$app->get('/books/create', function (Request $request, Response $response) {
    $this->logger->addInfo($request->getUri());

    //$statement = $this->db->prepare('INSERT INTO books (title, author, pages) VALUES (:title, :author, :pages);');
    //$statement->execute(array('title' => $params['title'], 'author' => $params['author'], 'pages' => $params['pages']));

    return $this->view->render($response, 'createabook.html',
    ["person" => $this->plus->people->get('me'),
    "signed_in" => $this->session->get('signed_in'),
    "messages" => $this->flash->getMessages(),
    "title" => "Dashboard - Create a Book"]);
});

$app->post('/books/create', function (Request $request, Response $response, $params) {
    $statement = $this->db->prepare('INSERT INTO books (title, author, pages) VALUES (:title, :author, :pages);');
    $statement->execute(array('title' => $params['title'], 'author' => $params['author'], 'pages' => $params['pages']));
    $this->flash->addMessage("Success", "You have successfully created a book.");
    return $response->withStatus(302)->withHeader('Location', '/books/create');
});

$app->get('/emails', function (Request $request, Response $response) {
    $this->logger->addInfo($request->getUri());
    var_dump($this->emails);
    return $response;
});

$app->run();
