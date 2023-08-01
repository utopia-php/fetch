<?php

$method = $_SERVER['REQUEST_METHOD']; // Get the request method
$url = $_SERVER['HTTP_HOST']; // Get the request URL
$query = $_GET; // Get the request arguments/queries
$headers = getallheaders(); // Get request headers
$body = file_get_contents("php://input"); // Get the request body
$files = $_FILES; // Get the request files

$curPageName = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'],"/")+1); 

if($curPageName == 'redirect'){
  header('Location: http://localhost:8000/redirectedPage');
  exit;
}
$resp = [
  'method' => $method,
  'url' => $url,
  'query' => $query,
  'body' => $body,
  'headers' => json_encode($headers),
  'files' => json_encode($files),
  'page' => $curPageName,
];

echo json_encode($resp);
