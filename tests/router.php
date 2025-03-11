<?php

$method = $_SERVER['REQUEST_METHOD']; // Get the request method
$url = $_SERVER['HTTP_HOST']; // Get the request URL
$query = $_GET; // Get the request arguments/queries
$headers = getallheaders(); // Get request headers
$body = file_get_contents("php://input"); // Get the request body
$files = $_FILES; // Get the request files

$stateFile = __DIR__ . '/state.json';

/**
 * Get the state from the state file
 * @return array<string, mixed>
 */
function getState(): array
{
    global $stateFile;
    if (file_exists($stateFile)) {
        $data = file_get_contents($stateFile);

        if ($data === false) {
            throw new \Exception('Failed to read state file');
        }

        return json_decode($data, true) ?? [];
    }
    return [];
}

/**
 * Set the state to the state file
 * @param array<string, mixed> $newState
 * @return void
 */
function setState(array $newState): void
{
    global $stateFile;
    file_put_contents($stateFile, json_encode($newState, JSON_PRETTY_PRINT));
}

$curPageName = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], "/") + 1);

if ($curPageName == 'redirect') {
    header('Location: http://localhost:8000/redirectedPage');
    exit;
} elseif ($curPageName == 'image') {
    $filename = __DIR__."/resources/logo.png";
    header("Content-disposition: attachment;filename=$filename");
    header("Content-type: application/octet-stream");
    readfile($filename);
    exit;
} elseif ($curPageName == 'text') {
    $filename = __DIR__."/resources/test.txt";
    header("Content-disposition: attachment;filename=$filename");
    header("Content-type: application/octet-stream");
    readfile($filename);
    exit;
} elseif ($curPageName == 'mock-retry') {
    $state = getState();
    $state['attempts'] = isset($state['attempts']) ? $state['attempts'] + 1 : 1;
    setState($state);

    if ($state['attempts'] <= 2) {
        http_response_code(503);
        throw new \Exception('Mock retry error');
    }

    $body = json_encode([
        'success' => true,
        'attempts' => $state['attempts']
    ]);
} elseif ($curPageName == 'mock-retry-401') {
    $state = getState();
    $state['attempts'] = isset($state['attempts']) ? $state['attempts'] + 1 : 1;
    setState($state);

    if ($state['attempts'] <= 2) {
        http_response_code(401);
        throw new \Exception('Mock retry error');
    }

    $body = json_encode([
        'success' => true,
        'attempts' => $state['attempts']
    ]);
} elseif ($curPageName == 'chunked') {
    // Set headers for chunked response
    header('Content-Type: text/plain');
    header('Transfer-Encoding: chunked');
    
    // Send chunks with delay
    $chunks = [
        "This is the first chunk\n",
        "This is the second chunk\n",
        "This is the final chunk\n"
    ];
    
    foreach ($chunks as $chunk) {
        echo $chunk;
        flush();
        usleep(100000); // 100ms delay between chunks
    }
    
    exit;
} elseif ($curPageName == 'chunked-json') {
    // Set headers for chunked JSON response
    header('Content-Type: application/json');
    header('Transfer-Encoding: chunked');
    
    // Send JSON chunks
    $chunks = [
        json_encode(['chunk' => 1, 'data' => 'First chunk']),
        json_encode(['chunk' => 2, 'data' => 'Second chunk']),
        json_encode(['chunk' => 3, 'data' => 'Final chunk'])
    ];
    
    foreach ($chunks as $chunk) {
        echo $chunk . "\n"; // Add newline for JSON lines format
        flush();
        usleep(100000); // 100ms delay between chunks
    }
    
    exit;
} elseif ($curPageName == 'error') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
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
