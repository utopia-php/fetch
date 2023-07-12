<?php

$method = $_SERVER['REQUEST_METHOD']; // Get the request method
$url = $_SERVER['HTTP_HOST']; // Get the request URL
$query = $_GET; // Get the request arguments/queries
$headers = getallheaders(); // Get request headers
$body = file_get_contents("php://input"); // Get the request body

$resp = [
  'method' => $method,
  'url' => $url,
  'query' => $query,
  'body' => $body,
  'headers' => json_encode($headers)
];

echo json_encode($resp);
