<?php
// Routes

$app->get('/', function ($request, $response, $args) use($app) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    //return $this->view->render($response, 'index.html.twig', $args);
    return $response->withStatus(302)->withHeader('Location', '/login');
});

// todo: web interface
// todo: change password
// todo: reset password
// todo: change apikey
// todo: profile page with their uploaded pictures

$app->get('/login', function ($request, $response) use ($app) {

    if ($this->session->get("logged_in")) $this->redirect("/");

    return $this->view->render($response, 'login.html.twig', array());
});

$app->post('/login', function ($request, $response) use ($app) {

    if ($this->session->get("logged_in")) {
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    if ($request->isPost()) {
        $username = $request->post('username');
        $password = $request->post('password');
        if (!isset($username) || !isset($password))  {
            $app->flash->addMessage('error', 'You did not fill out all the fields.');
            return $app->render('login.html.twig', array('username' => $username));
        }

        $statement = $app->db->prepare('SELECT password, role FROM users WHERE username=:username');
        $statement->execute(array('username' => $username));
        $user = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($user->rowCount() === 0) {
            $app->flash->addMessage("error", "Invalid username or password. Please try again.");
        }

        if (!password_verify($password, $user["password"])) {
            $app->flash->addMessage("error", "Invalid username or password. Please try again.");
        } else {
            $this->session->set("username", $username);
            $this->session->set("logged_in", true);
            if ($user["role"] === 2)
                $app->session->set("is_admin", true);
            else
                $app->session->set("is_admin", false);
            return $response->withStatus(302)->withHeader('Location', '/');
        }
    }

    return $app->render('login.twig', array('username' => $username));
});

$app->get('/logout', function () use ($app) {
    $app->session->pop();
    $app->redirect('/');
});

$app->post('/uploader', function (\Slim\Http\Request $request, $response, $args) use ($app) {

    $apikey = $app->request->post('apikey');

    $statement = $app->db->prepare('SELECT id FROM users WHERE apikey=:apikey');
    $statement->execute(array('apikey' => $apikey));
    $users = $statement->fetchAll(PDO::FETCH_ASSOC);

    if ($users->rowCount() === 0) {
        return $response->withJson(array("error" => "Not allowed to upload, invalid apikey"), 403);
    }

    $directory = $this->get('upload_directory');

    $uploadedFiles = $request->getUploadedFiles();

    $uploadedFile = $uploadedFiles['image'];

    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(12)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.12s', $basename, $extension);

        $statement = $app->db->prepare('INSERT INTO images(file_name, date_uploaded, uploader) VALUES (:filename, NOW(), :uploaderId)');
        $statement->execute(array(
            'filename' => $filename,
            'uploaderId' => $users[0]
        ));

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
        $response->write("http://$_SERVER[HTTP_HOST]/upload/$filename");
    } else {
        $response->withJson(array("error"=>"Failed to upload, check server logs."), 500);
    }
});