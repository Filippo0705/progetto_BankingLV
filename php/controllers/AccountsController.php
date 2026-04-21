<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountsController {

    // GET /accounts/{id}/balance
    public function getBalance(Request $request, Response $response, array $args) {
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
    }

    // GET /accounts/{id}/balance/convert/fiat
    public function convertToFiat(Request $request, Response $response, array $args) {
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
    }

    // GET /accounts/{id}/balance/convert/crypto
    public function convertToCrypto(Request $request, Response $response, array $args) {
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

        $marketSymbol = $toCrypto . $fromCurrency;

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
    }
}
