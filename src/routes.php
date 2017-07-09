<?php
// Routes

$app->get($app->prefix.'/', function ($request, $response, $args) use($app) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    if ($this->session->get("logged_in")) {
        $statement = $this->db->prepare('SELECT file_name FROM images WHERE uploader=:userid');
        $statement->execute(array('userid' => $this->session->get("userid")));
        $images = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $this->view->render($response, 'index.html.twig', array(
            "logged_in" => $this->session->get("logged_in"),
            "username" => $this->session->get("username"),
            "is_admin" => $this->session->get("is_admin"),
            "images" => $images
        ));
    }
    return $response->withStatus(302)->withHeader('Location', '/login');
});

$app->get($app->prefix.'/user/resetkey', function ($request, $response, $args) use ($app) {
    if ($this->session->get('logged_in')) {
        $newkey = $this->keygen;
        $statement = $this->db->prepare('UPDATE users SET apikey=:newkey WHERE id=:userid');
        $statement->execute(array('userid' => $this->session->get("userid"), "newkey" => $newkey));
        return $response->write($newkey);
    } else {
        return $response->write("Not allowed to access this resource.");
    }
});

$app->get($app->prefix.'/user/apikey', function ($request, $response, $args) use ($app) {
    if ($this->session->get('logged_in')) {
        $statement = $this->db->prepare('SELECT apikey FROM users WHERE id=:userid');
        $statement->execute(array('userid' => $this->session->get("userid")));
        $results = $statement->fetch(PDO::FETCH_ASSOC);
        return $response->write($results['apikey']);
    } else {
        return $response->write("Not allowed to access this resource.");
    }
});

$app->get($app->prefix.'/user/sharex', function ($request, $response, $args) use ($app) {
    if ($this->session->get('logged_in')) {
        $statement = $this->db->prepare('SELECT apikey FROM users WHERE id=:userid');
        $statement->execute(array('userid' => $this->session->get("userid")));
        $results = $statement->fetch(PDO::FETCH_ASSOC);
        $sharex = '{"Name": "NovaShare","DestinationType": "None",  "RequestType": "POST","RequestURL": "http://novacraft.me/share/uploader","FileFormName": "image","Arguments": {"apikey": "' . $results['apikey'] . '"},"ResponseType": "Text"}"';
        return $response->withHeader('Content-Type', 'application/force-download')
                        ->withHeader('Content-Type', 'application/octet-stream')
                        ->withHeader('Content-Type', 'application/download')
                        ->withHeader('Content-Description', 'File Transfer')
                        ->withHeader('Content-Disposition', 'attachment; filename="novashare.sxcu"')
                        ->withHeader('Expires', '0')
                        ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                        ->withHeader('Pragma', 'public')
                        ->write($sharex);
    } else {
        return $response->write("Not allowed to access this resource.");
    }
});

$app->get($app->prefix.'/user/changepass', function ($request, $response, $args) use ($app) {
    if ($this->session->get('logged_in')) {
        $params = $request->getQueryParams();

        $statement = $this->db->prepare('UPDATE users SET password=:newpass WHERE id=:userid');
        $statement->execute(array('userid' => $this->session->get("userid"), "newpass" => password_hash($params['password'])));
    } else {
        return $response->write("Not allowed to access this resource.");
    }
});

// todo: change password

$app->get($app->prefix.'/login', function ($request, $response) use ($app) {

    if ($this->session->get("logged_in")) return $response->withStatus(302)->withHeader('Location', $prefix.'/');

    return $this->view->render($response, 'login.html.twig', array());
});

$app->post($app->prefix.'/login', function ($request, $response) use ($app) {

    if ($this->session->get("logged_in")) {
        return $response->withStatus(302)->withHeader('Location', $this->prefix.'/');
    }

    if ($request->isPost()) {
        $username = $request->getParam('username');
        $password = $request->getParam('password');
        if (!isset($username) || !isset($password))  {
            $app->flash->addMessage('error', 'You did not fill out all the fields.');
            return $app->render('login.html.twig', array('username' => $username));
        }

        $statement = $this->db->prepare('SELECT id, password, role FROM users WHERE username=:username');
        $statement->execute(array('username' => $username));
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($statement->rowCount() === 0) {
            $app->flash->addMessage("error", "Invalid username or password. Please try again.");
        }

        if (!password_verify($password, $user["password"])) {
            $app->flash->addMessage("error", "Invalid username or password. Please try again.");
        } else {
            $this->session->set("username", $username);
            $this->session->set("logged_in", true);
            $this->session->set("userid", $user["id"]);
            if ($user["role"] == 2)
                $this->session->set("is_admin", true);
            else
                $this->session->set("is_admin", false);
            return $response->withStatus(302)->withHeader('Location', $this->prefix.'/');
        }
    }

    return $app->render('login.twig', array('username' => $username));
});

$app->get($app->prefix.'/register', function ($request, $response) use ($app) {

    if ($this->session->get("logged_in")) return $response->withStatus(302)->withHeader('Location', $this->prefix.'/');

    return $this->view->render($response, 'register.html.twig', array());
});

$app->post($app->prefix.'/register', function ($request, $response) use ($app) {

    if ($this->session->get("logged_in")) {
        return $response->withStatus(302)->withHeader('Location', $this->prefix.'/');
    }

    if ($request->isPost()) {
        $username = $request->getParam('username');
        $email = $request->getParam('email');
        $password = $request->getParam('password');
        $confirmpass = $request->getParam('confirmpass');
        if (!isset($username) || !isset($password) || !isset($email) || !isset($confirmpass))  {
            $app->flash->addMessage('error', 'You did not fill out all the fields.');
            return $app->render('login.html.twig', array('username' => $username, "email" => $email));
        }

        $statement = $this->db->prepare('INSERT INTO users (username, email, role, password, apikey) VALUES (:username, :email, 1, :password, :apikey)');
        try {
            $statement->execute(array(
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'apikey' => $this->keygen
            ));
        } catch (\PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $this->flash->addMessage("error", "A user with that username or email already exists.");
            }
        }

        $this->flash->addMessage("success", "Successfully created an account. You can now log in.");
        return $response->withStatus(302)->withHeader('Location', $this->prefix.'/login');
    }

    return $app->render('register.twig.html', array());
});

$app->get($app->prefix.'/logout', function ($request, $response) use ($app) {
    $this->session->destroy();
    return $response->withStatus(302)->withHeader('Location', '/');
});

$app->post($app->prefix.'/uploader', function (\Slim\Http\Request $request, $response, $args) use ($app) {

    $apikey = $request->getParam('apikey');

    $statement = $this->db->prepare('SELECT id FROM users WHERE apikey=:apikey');
    $statement->execute(array('apikey' => $apikey));
    $users = $statement->fetch(PDO::FETCH_ASSOC);

    if ($statement->rowCount() === 0) {
        return $response->withJson(array("error" => "Invalid apikey, check your settings."), 403);
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR .  ".." . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "uploads";

    $uploadedFiles = $request->getUploadedFiles();

    $uploadedFile = $uploadedFiles['image'];

    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(12)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.12s', $basename, $extension);

        $statement = $this->db->prepare('INSERT INTO images(file_name, date_uploaded, uploader) VALUES (:filename, NOW(), :uploaderId)');
        $statement->execute(array(
            'filename' => $filename,
            'uploaderId' => $users["id"]
        ));

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
        $response->write("http://$_SERVER[HTTP_HOST]/uploads/$filename");
    } else {
        $response->withJson(array("error"=>"Failed to upload, check server logs."), 500);
    }
});
