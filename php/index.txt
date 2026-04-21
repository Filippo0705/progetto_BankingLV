<?php

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

function getConnection() {
    $host = "my_mariadb";
    $user = "root";
    $pass = "ciccio";
    $db   = "scuola";

    $mysqli = new mysqli($host, $user, $pass, $db);

    if ($mysqli->connect_error) {
        die("DB Connection failed: " . $mysqli->connect_error);
    }

    return $mysqli;
}

function jsonResponse(Response $response, $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data));
    return $response->withHeader("Content-Type", "application/json")->withStatus($status);
}

function getAccount($conn, int $accountId) {
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getTransaction($conn, int $accountId, int $transactionId) {
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    $stmt->bind_param("ii", $transactionId, $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}


// ==========================================
// GET lista movimenti
// GET /accounts/1/transactions
// ==========================================
$app->get("/accounts/{id}/transactions", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];

    $account = getAccount($conn, $accountId);
    if (!$account) {
        return jsonResponse($response, ["error" => "Account not found"], 404);
    }

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();

    $result = $stmt->get_result();
    $transactions = [];

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    return jsonResponse($response, [
        "account_id" => $accountId,
        "transactions" => $transactions
    ]);
});


// ==========================================
// GET dettaglio movimento
// GET /accounts/1/transactions/5
// ==========================================
$app->get("/accounts/{id}/transactions/{tid}", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"]; 
    $transactionId = (int)$args["tid"];

    $transaction = getTransaction($conn, $accountId, $transactionId);

    if (!$transaction) {
        return jsonResponse($response, ["error" => "Transaction not found"], 404);
    }

    return jsonResponse($response, $transaction);
});


// ==========================================
// POST deposito
// POST /accounts/1/deposits
// ==========================================
$app->post("/accounts/{id}/deposits", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];
    $data = $request->getParsedBody();

    $account = getAccount($conn, $accountId);
    if (!$account) {
        return jsonResponse($response, ["error" => "Account not found"], 404);
    }

    $amount = (float)($data["amount"] ?? 0);
    $description = trim($data["description"] ?? "");

    if ($amount <= 0) {
        return jsonResponse($response, ["error" => "Amount must be greater than 0"], 400);
    }

    if ($description === "") {
        return jsonResponse($response, ["error" => "Description is required"], 400);
    }

    // inserisco transazione
    $stmt = $conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'deposit', ?, ?)");
    $stmt->bind_param("iis", $accountId, $amount, $description);
    $stmt->execute();

    // aggiorno saldo (currency)
    $stmt = $conn->prepare("UPDATE accounts SET currency = currency + ? WHERE id = ?");
    $stmt->bind_param("ii", $amount, $accountId);
    $stmt->execute();

    return jsonResponse($response, [
        "message" => "Deposit created",
        "transaction_id" => $conn->insert_id,
        "new_balance" => (int)$account["currency"] + (int)$amount
    ], 201);
});


// ==========================================
// POST prelievo
// POST /accounts/1/withdrawals
// ==========================================
$app->post("/accounts/{id}/withdrawals", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];
    $data = $request->getParsedBody();

    $account = getAccount($conn, $accountId);
    if (!$account) {
        return jsonResponse($response, ["error" => "Account not found"], 404);
    }

    $amount = (float)($data["amount"] ?? 0);
    $description = trim($data["description"] ?? "");

    if ($amount <= 0) {
        return jsonResponse($response, ["error" => "Amount must be greater than 0"], 400);
    }

    if ($description === "") {
        return jsonResponse($response, ["error" => "Description is required"], 400);
    }

    $balance = (int)$account["currency"];

    if ($amount > $balance) {
        return jsonResponse($response, [
            "error" => "Insufficient funds",
            "current_balance" => $balance
        ], 422);
    }

    // inserisco transazione
    $stmt = $conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'withdrawal', ?, ?)");
    $stmt->bind_param("iis", $accountId, $amount, $description);
    $stmt->execute();

    // aggiorno saldo (currency)
    $stmt = $conn->prepare("UPDATE accounts SET currency = currency - ? WHERE id = ?");
    $stmt->bind_param("ii", $amount, $accountId);
    $stmt->execute();

    return jsonResponse($response, [
        "message" => "Withdrawal created",
        "transaction_id" => $conn->insert_id,
        "new_balance" => $balance - (int)$amount
    ], 201);
});


// ==========================================
// PUT modifica descrizione
// PUT /accounts/1/transactions/5
// ==========================================
$app->put("/accounts/{id}/transactions/{tid}", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];
    $transactionId = (int)$args["tid"];

    $data = $request->getParsedBody();
    $description = trim($data["description"] ?? "");

    if ($description === "") {
        return jsonResponse($response, ["error" => "Description is required"], 400);
    }

    $transaction = getTransaction($conn, $accountId, $transactionId);
    if (!$transaction) {
        return jsonResponse($response, ["error" => "Transaction not found"], 404);
    }

    $stmt = $conn->prepare("UPDATE transactions SET description = ? WHERE id = ? AND account_id = ?");
    $stmt->bind_param("sii", $description, $transactionId, $accountId);
    $stmt->execute();

    return jsonResponse($response, [
        "message" => "Transaction updated",
        "transaction_id" => $transactionId,
        "new_description" => $description
    ]);
});


// ==========================================
// DELETE elimina movimento (regola: solo ultimo)
// DELETE /accounts/1/transactions/5
// ==========================================
$app->delete("/accounts/{id}/transactions/{tid}", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];
    $transactionId = (int)$args["tid"];

    $transaction = getTransaction($conn, $accountId, $transactionId);
    if (!$transaction) {
        return jsonResponse($response, ["error" => "Transaction not found"], 404);
    }

    // recupero ultimo movimento
    $stmt = $conn->prepare("SELECT id FROM transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $last = $result->fetch_assoc();

    if (!$last || (int)$last["id"] !== $transactionId) {
        return jsonResponse($response, ["error" => "You can delete only the last transaction"], 422);
    }

    // elimino movimento
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND account_id = ?");
    $stmt->bind_param("ii", $transactionId, $accountId);
    $stmt->execute();

    // aggiorno saldo (currency) tornando indietro
    if ($transaction["type"] === "deposit") {
        $stmt = $conn->prepare("UPDATE accounts SET currency = currency - ? WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE accounts SET currency = currency + ? WHERE id = ?");
    }

    $amount = (int)$transaction["amount"];
    $stmt->bind_param("ii", $amount, $accountId);
    $stmt->execute();

    return jsonResponse($response, [
        "message" => "Transaction deleted",
        "transaction_id" => $transactionId
    ]);
});


// ==========================================
// GET saldo
// GET /accounts/1/balance
// ==========================================
$app->get("/accounts/{id}/balance", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];

    $account = getAccount($conn, $accountId);
    if (!$account) {
        return jsonResponse($response, ["error" => "Account not found"], 404);
    }

    return jsonResponse($response, [
        "account_id" => $accountId,
        "balance" => (int)$account["currency"]
    ]);
});


// ==========================================
// Conversione FIAT con Frankfurter
// GET /accounts/1/balance/convert/fiat?to=USD
// NOTA: per il tuo DB assumiamo currency = saldo EUR
// ==========================================
$app->get("/accounts/{id}/balance/convert/fiat", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];
    $params = $request->getQueryParams();

    $to = strtoupper($params["to"] ?? "");

    if (!$to) {
        return jsonResponse($response, ["error" => "Missing target currency"], 400);
    }

    $account = getAccount($conn, $accountId);
    if (!$account) {
        return jsonResponse($response, ["error" => "Account not found"], 404);
    }

    // ASSUNZIONE: saldo sempre in EUR
    $from = "EUR";
    $balance = (float)$account["currency"];

    $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
    $json = @file_get_contents($url);

    if ($json === false) {
        return jsonResponse($response, ["error" => "External exchange API unavailable"], 502);
    }

    $data = json_decode($json, true);

    if (!isset($data["rates"][$to])) {
        return jsonResponse($response, ["error" => "Target currency not supported"], 400);
    }

    $rate = (float)$data["rates"][$to];
    $converted = round($balance * $rate, 2);

    return jsonResponse($response, [
        "account_id" => $accountId,
        "provider" => "Frankfurter",
        "conversion_type" => "fiat",
        "from_currency" => $from,
        "to_currency" => $to,
        "original_balance" => $balance,
        "rate" => $rate,
        "converted_balance" => $converted,
        "date" => $data["date"] ?? null
    ]);
});


// ==========================================
// Conversione CRYPTO con Binance
// GET /accounts/1/balance/convert/crypto?to=BTC
// NOTA: assumiamo saldo in EUR
// ==========================================
$app->get("/accounts/{id}/balance/convert/crypto", function(Request $request, Response $response, array $args) {
    $conn = getConnection();
    $accountId = (int)$args["id"];
    $params = $request->getQueryParams();

    $toCrypto = strtoupper($params["to"] ?? "");
    if (!$toCrypto) {
        return jsonResponse($response, ["error" => "Missing target crypto"], 400);
    }

    $account = getAccount($conn, $accountId);
    if (!$account) {
        return jsonResponse($response, ["error" => "Account not found"], 404);
    }

    $balance = (float)$account["currency"];
    $fromCurrency = "EUR";

    $marketSymbol = $toCrypto . $fromCurrency; // es: BTCEUR

    // controllo coppia esistente
    $exchangeInfoJson = @file_get_contents("https://api.binance.com/api/v3/exchangeInfo?symbol={$marketSymbol}");

    if ($exchangeInfoJson === false) {
        return jsonResponse($response, ["error" => "Binance API unavailable"], 502);
    }

    $exchangeInfo = json_decode($exchangeInfoJson, true);

    if (!isset($exchangeInfo["symbols"][0]) || $exchangeInfo["symbols"][0]["status"] !== "TRADING") {
        return jsonResponse($response, [
            "error" => "Crypto pair not supported",
            "market_symbol" => $marketSymbol
        ], 400);
    }

    // prezzo attuale
    $priceJson = @file_get_contents("https://api.binance.com/api/v3/ticker/price?symbol={$marketSymbol}");

    if ($priceJson === false) {
        return jsonResponse($response, ["error" => "Binance ticker unavailable"], 502);
    }

    $priceData = json_decode($priceJson, true);

    if (!isset($priceData["price"])) {
        return jsonResponse($response, ["error" => "Invalid Binance response"], 502);
    }

    $price = (float)$priceData["price"];

    if ($price <= 0) {
        return jsonResponse($response, ["error" => "Invalid crypto price"], 502);
    }

    // conversione: quantità crypto = saldo / prezzo
    $convertedAmount = round($balance / $price, 8);

    return jsonResponse($response, [
        "account_id" => $accountId,
        "provider" => "Binance",
        "conversion_type" => "crypto",
        "from_currency" => $fromCurrency,
        "to_crypto" => $toCrypto,
        "market_symbol" => $marketSymbol,
        "original_balance" => $balance,
        "price" => $price,
        "converted_amount" => $convertedAmount
    ]);
});

$app->run();