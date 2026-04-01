<?php
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

// Funzione helper per la connessione (da personalizzare con i tuoi dati)
function getConnection() {
    $host = "localhost"; $user = "root"; $pass = ""; $db = "bank_db";
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) die("Connessione fallita");
    return $conn;
}

// Funzione helper per calcolare il saldo (Richiesta dalle Regole di Business)
function getBalance($conn, $account_id) {
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END) as total 
        FROM transactions WHERE account_id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (float)($result['total'] ?? 0);
}

// 1. REGISTRARE DEPOSITI
$app->post('/accounts/{id}/deposits', function (Request $request, Response $response, array $args) {
    $conn = getConnection();
    $data = $request->getParsedBody();
    $account_id = $args['id'];
    $amount = $data['amount'] ?? 0;

    if ($amount <= 0) {
        $response->getBody()->write(json_encode(["error" => "L'importo deve essere > 0"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $conn->prepare("INSERT INTO transactions (type, amount, description, account_id) VALUES ('deposit', ?, ?, ?)");
    $stmt->bind_param("dsi", $amount, $data['description'], $account_id);
    $stmt->execute();

    $response->getBody()->write(json_encode(["message" => "Deposito eseguito", "id" => $stmt->insert_id]));
    return $response->withHeader('Content-Type', 'application/json');
});

// 2. REGISTRARE PRELIEVI (Con controllo saldo)
$app->post('/accounts/{id}/withdrawals', function (Request $request, Response $response, array $args) {
    $conn = getConnection();
    $data = $request->getParsedBody();
    $account_id = $args['id'];
    $amount = $data['amount'] ?? 0;

    $currentBalance = getBalance($conn, $account_id);

    if ($amount <= 0 || $amount > $currentBalance) {
        $msg = $amount <= 0 ? "Importo non valido" : "Saldo insufficiente (Disponibile: $currentBalance)";
        $response->getBody()->write(json_encode(["error" => $msg]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $conn->prepare("INSERT INTO transactions (type, amount, description, account_id) VALUES ('withdrawal', ?, ?, ?)");
    $stmt->bind_param("dsi", $amount, $data['description'], $account_id);
    $stmt->execute();

    $response->getBody()->write(json_encode(["message" => "Prelievo eseguito"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// 3. LISTA MOVIMENTI
$app->get('/accounts/{id}/transactions', function (Request $request, Response $response, array $args) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $args['id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $response->getBody()->write(json_encode($res));
    return $response->withHeader('Content-Type', 'application/json');
});

// 4. DETTAGLIO SINGOLO MOVIMENTO
$app->get('/accounts/{id}/transactions/{t_id}', function (Request $request, Response $response, array $args) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    $stmt->bind_param("ii", $args['t_id'], $args['id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    $response->getBody()->write(json_encode($res ?: ["error" => "Movimento non trovato"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// 5. SALDO ATTUALE
$app->get('/accounts/{id}/balance', function (Request $request, Response $response, array $args) {
    $conn = getConnection();
    $balance = getBalance($conn, $args['id']);
    $response->getBody()->write(json_encode(["account_id" => $args['id'], "balance" => $balance, "currency" => "EUR"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// 6. CONVERSIONE FIAT (Frankfurter)
$app->get('/accounts/{id}/balance/convert/fiat', function (Request $request, Response $response, array $args) {
    $to = $request->getQueryParams()['to'] ?? 'USD';
    $conn = getConnection();
    $balance = getBalance($conn, $args['id']);

    $json = file_get_contents("https://frankfurter.app");
    $data = json_decode($json, true);

    $res = [
        "provider" => "Frankfurter",
        "original_balance" => $balance,
        "converted_balance" => $data['rates'][$to] ?? 0,
        "currency" => $to
    ];

    $response->getBody()->write(json_encode($res));
    return $response->withHeader('Content-Type', 'application/json');
});

// 7. CONVERSIONE CRYPTO (Binance)
$app->get('/accounts/{id}/balance/convert/crypto', function (Request $request, Response $response, array $args) {
    $to = strtoupper($request->getQueryParams()['to'] ?? 'BTC'); // es: BTC
    $conn = getConnection();
    $balance = getBalance($conn, $args['id']);

    // Binance usa coppie come BTCEUR
    $symbol = "{$to}EUR";
    $json = @file_get_contents("https://binance.com");
    
    if (!$json) {
        $response->getBody()->write(json_encode(["error" => "Coppia $symbol non supportata"]));
        return $response->withStatus(400);
    }

    $data = json_decode($json, true);
    $price = (float)$data['price'];
    
    $res = [
        "provider" => "Binance",
        "original_balance_eur" => $balance,
        "crypto_price_eur" => $price,
        "converted_amount" => round($balance / $price, 8),
        "target" => $to
    ];

    $response->getBody()->write(json_encode($res));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
