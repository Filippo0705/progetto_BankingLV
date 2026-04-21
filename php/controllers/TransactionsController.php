<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController {
    
    // GET /accounts/{id}/transactions
    public function list(Request $request, Response $response, array $args) {
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
    }

    // GET /accounts/{id}/transactions/{tid}
    public function getOne(Request $request, Response $response, array $args) {
        $conn = getConnection();
        $accountId = (int)$args["id"]; 
        $transactionId = (int)$args["tid"];

        $transaction = getTransaction($conn, $accountId, $transactionId);

        if (!$transaction) {
            return jsonResponse($response, ["error" => "Transaction not found"], 404);
        }

        return jsonResponse($response, $transaction);
    }

    // POST /accounts/{id}/deposits
    public function deposit(Request $request, Response $response, array $args) {
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

        $stmt = $conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'deposit', ?, ?)");
        $stmt->bind_param("iis", $accountId, $amount, $description);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE accounts SET currency = currency + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $accountId);
        $stmt->execute();

        return jsonResponse($response, [
            "message" => "Deposit created",
            "transaction_id" => $conn->insert_id,
            "new_balance" => (int)$account["currency"] + (int)$amount
        ], 201);
    }

    // POST /accounts/{id}/withdrawals
    public function withdraw(Request $request, Response $response, array $args) {
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

        $stmt = $conn->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'withdrawal', ?, ?)");
        $stmt->bind_param("iis", $accountId, $amount, $description);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE accounts SET currency = currency - ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $accountId);
        $stmt->execute();

        return jsonResponse($response, [
            "message" => "Withdrawal created",
            "transaction_id" => $conn->insert_id,
            "new_balance" => $balance - (int)$amount
        ], 201);
    }

    // PUT /accounts/{id}/transactions/{tid}
    public function update(Request $request, Response $response, array $args) {
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
    }

    // DELETE /accounts/{id}/transactions/{tid}
    public function delete(Request $request, Response $response, array $args) {
        $conn = getConnection();
        $accountId = (int)$args["id"];
        $transactionId = (int)$args["tid"];

        $transaction = getTransaction($conn, $accountId, $transactionId);
        if (!$transaction) {
            return jsonResponse($response, ["error" => "Transaction not found"], 404);
        }

        $stmt = $conn->prepare("SELECT id FROM transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $last = $result->fetch_assoc();

        if (!$last || (int)$last["id"] !== $transactionId) {
            return jsonResponse($response, ["error" => "You can delete only the last transaction"], 422);
        }

        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND account_id = ?");
        $stmt->bind_param("ii", $transactionId, $accountId);
        $stmt->execute();

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
    }
}
