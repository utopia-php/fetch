<?php

require_once 'vendor/autoload.php';

$url = "http://localhost:8000";
$method = "POST";
$body = [
    'name' => 'John Doe',
    'age' => 30,
];
$query = [
    'name' => 'John Doe',
    'age' => 30,
];
try {
    $resp = \Utopia\Fetch\Client::fetch(
        url: $url,
        method: $method,
        headers: [
        ],
        body: $body,
        query: $query
    );
} catch (\Utopia\Fetch\FetchException $e) {
    echo $e;
}

if ($resp->isOk()) {
    echo "Response is OK\n";
    $respBody = $resp->text();
    print_r($respBody);
} else {
    echo "Please configure your PHP inbuilt SERVER";
}
