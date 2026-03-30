<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AlunniController.php';

$app = AppFactory::create();

$app->get('/test', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Test page");
    return $response;
});

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Ciao, $name");
    return $response;
});
$app->post('/accounts/{id}/deposita', function (Request $request, Response $response) {

    $conn = getConnection();
    $data = $request->getParsedBody();
    $account_id = $args['id'];

    $stmt = $conn->prepare("INSERT INTO transactions (type, amount,description,account_id) VALUES (?, ?, ?,?)");
    $stmt->bind_param("sisi","deposit", $data['amount'], $data['description'],$account_id);
    $stmt->execute();

    $response->getBody()->write(json_encode([
        "message" => "Transazione eseguita",
        "id" => $stmt->insert_id
    ]));

    $stmt = $conn->prepare("UPDATE accounts a SET currency = (select SUM(t.amount) from transactions t where t.account_id = ? ) WHERE a.id = ?");
    $stmt->bind_param("ii",$account_id,$account_id);
    $stmt->execute();

    return $response->withHeader('Content-Type', 'application/json');
});
$app->post('/accounts/{id}/preleva', function (Request $request, Response $response) {

    $conn = getConnection();
    $data = $request->getParsedBody();
    $account_id = $args['id'];

    $stmt = $conn->prepare("INSERT INTO transactions (type, amount,description,account_id) VALUES (?, ?, ?,?)");
    $stmt->bind_param("sisi", "withdrawal", $data['amount'], $data['description'],$account_id);
    $stmt->execute();

    $response->getBody()->write(json_encode([
        "message" => "Prelevio eseguito",
        "id" => $stmt->insert_id
    ]));
    $stmt = $conn->prepare("UPDATE accounts a SET currency = (select SUM(t.amount) from transactions t where t.account_id = ? ) WHERE a.id = ?");
    $stmt->bind_param("ii",$account_id,$account_id);
    $stmt->execute();

    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/accounts/{id}/movimenti', function (Request $request, Response $response) {

    $conn = getConnection();
    $data = $request->getParsedBody();
    $account_id = $args['id'];

    $stmt = $conn->prepare("SELECT * from transactions t WHERE t.account_id = ?");
    $stmt->bind_param("i",$account_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $movimenti = $result->fetch_assoc();

    $response->getBody()->write(json_encode($movimenti));

    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/accounts/{id}/saldo', function (Request $request, Response $response) {

    $conn = getConnection();
    $data = $request->getParsedBody();
    $account_id = $args['id'];

    $stmt = $conn->prepare("SELECT currency from account a WHERE a.id = ?");
    $stmt->bind_param("i",$account_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $saldo = $result->fetch_assoc();

    $response->getBody()->write(json_encode($saldo));

    return $response->withHeader('Content-Type', 'application/json');
});


$app->get('/accounts/{id}/transazioni', "bankingControll:lista");


// ottenere l'elenco dettagliato di un movimento

$app->run();
