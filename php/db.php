<?php
// db.php

use Psr\Http\Message\ResponseInterface as Response;

// 1. Funzione per la connessione al database
function getConnection() {
    $host = "my_mariadb";
    $user = "root";
    $pass = "ciccio";
    $db   = "scuola";

    $mysqli = new mysqli($host, $user, $pass, $db);

    if ($mysqli->connect_error) {
        // In una API, sarebbe meglio restituire un errore JSON, 
        // ma per ora manteniamo la tua logica
        die("DB Connection failed: " . $mysqli->connect_error);
    }

    return $mysqli;
}

// 2. Helper per le risposte JSON
function jsonResponse(Response $response, $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data));
    return $response->withHeader("Content-Type", "application/json")->withStatus($status);
}

// 3. Recupero Account
function getAccount($conn, int $accountId) {
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// 4. Recupero Transazione specifica
function getTransaction($conn, int $accountId, int $transactionId) {
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    $stmt->bind_param("ii", $transactionId, $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
