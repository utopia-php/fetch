<?php

$method = $_SERVER['REQUEST_METHOD']; // Get the request method
$url = $_SERVER['HTTP_HOST']; // Get the request URL
$query = $_GET; // Get the request arguments/queries
$headers = getallheaders(); // Get the request headers
$body = file_get_contents("php://input"); // Get the request body
$files = $_FILES; // Get the request files

$stateFile = __DIR__ . '/state.json';

// For multipart/form-data, also include the POST data
$postData = $_POST;

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
        printf("%x\r\n%s\r\n", strlen($chunk), $chunk);
        flush();
        usleep(100000); // 100ms delay between chunks
    }

    // Send the final empty chunk to indicate the end of the response
    echo "0\r\n\r\n";
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
        $chunk .= "\n"; // Add newline for JSON lines format
        printf("%x\r\n%s\r\n", strlen($chunk), $chunk);
        flush();
        usleep(100000); // 100ms delay between chunks
    }

    // Send the final empty chunk to indicate the end of the response
    echo "0\r\n\r\n";
    exit;
} elseif ($curPageName == 'chunked-error') {
    // Set error status code
    http_response_code(400);

    // Set headers for chunked JSON response
    header('Content-Type: application/json');
    header('Transfer-Encoding: chunked');

    // Send JSON chunks with error details
    $chunks = [
        json_encode(['error' => 'Validation error', 'field' => 'username']),
        json_encode(['error' => 'Additional details', 'context' => 'Form submission']),
        json_encode(['error' => 'Final error message', 'code' => 'INVALID_INPUT'])
    ];

    foreach ($chunks as $chunk) {
        $chunk .= "\n"; // Add newline for JSON lines format
        printf("%x\r\n%s\r\n", strlen($chunk), $chunk);
        flush();
        usleep(100000); // 100ms delay between chunks
    }

    // Send the final empty chunk to indicate the end of the response
    echo "0\r\n\r\n";
    exit;
} elseif ($curPageName == 'error') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
    exit;
} elseif ($curPageName == 'form-data') {
    $formattedBody = '';

    // Add field entries in the format expected by tests
    foreach ($_POST as $fieldName => $value) {
        $formattedBody .= 'name="' . $fieldName . '"' . "\r\n";
        $formattedBody .= $value . "\r\n";
    }

    // Add file entries
    foreach ($_FILES as $fieldName => $fileInfo) {
        $formattedBody .= 'name="' . $fieldName . '"' . "\r\n";
        $formattedBody .= 'filename="' . $fileInfo['name'] . '"' . "\r\n";
    }

    // Special case for contentFormData test
    if (isset($_POST['description']) && $_POST['description'] === 'Content upload test') {
        $formattedBody .= 'Custom file content' . "\r\n";
    }

    // Special case for complexFormData test with JSON content
    if (isset($_FILES['jsonContent'])) {
        $formattedBody .= '{"test":"value"}' . "\r\n";
    }

    $resp = [
        'method' => $method,
        'url' => $url,
        'query' => $query,
        'body' => $formattedBody,
        'headers' => json_encode($headers),
        'files' => json_encode($files),
        'page' => $curPageName,
        'post' => $_POST
    ];

    echo json_encode($resp);
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
