<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class bankingControll
{
  public function lista(Request $request, Response $response, $args){
    $id = $args['id'];

    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'db');
    $result = $mysqli_connection->query("SELECT * FROM transactions WHERE id = '$id'");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }
}