<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get the query string from the URL
    $query_string = $_SERVER['QUERY_STRING'];

    // Parse the query string into an associative array
    parse_str($query_string, $query_params);

    // Display the query parameters
    echo '<h2>Query Parameters:</h2>';
    echo '<ul>';
    foreach ($query_params as $key => $value) {
        echo '<li>' . htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '</li>';
    }
    echo '</ul>';
} else {
    echo 'Invalid request method.';
}
?>
