<?php
use Slim\Factory\AppFactory;
use App\Controllers\AccountsController;
use App\Controllers\TransactionsController;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

// Middleware per leggere i dati inviati via POST/PUT (JSON o Form)
$app->addBodyParsingMiddleware();

// GRUPPO ACCOUNT
$app->group('/accounts/{id}', function ($group) {
    
    // Rotte gestite da AccountsController
    $group->get('/balance', [AccountsController::class, 'getBalance']);
    $group->get('/balance/convert/fiat', [AccountsController::class, 'convertToFiat']);
    $group->get('/balance/convert/crypto', [AccountsController::class, 'convertToCrypto']);

    // Rotte gestite da TransactionsController
    $group->group('/transactions', function ($trans) {
        $trans->get('', [TransactionsController::class, 'list']);
        $trans->get('/{tid}', [TransactionsController::class, 'getOne']);
        $trans->put('/{tid}', [TransactionsController::class, 'update']);
        $trans->delete('/{tid}', [TransactionsController::class, 'delete']);
    });

    // Operazioni di deposito/prelievo
    $group->post('/deposits', [TransactionsController::class, 'deposit']);
    $group->post('/withdrawals', [TransactionsController::class, 'withdraw']);
});

$app->run();
