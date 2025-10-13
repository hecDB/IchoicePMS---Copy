<?php
// Simple test to check PHP logging
error_log("=== PHP LOGGING TEST ===");
error_log("Current timestamp: " . date('Y-m-d H:i:s'));
error_log("PHP version: " . phpversion());
error_log("Error log setting: " . ini_get('error_log'));
error_log("Log errors setting: " . ini_get('log_errors'));

echo "<h1>PHP Logging Test</h1>";
echo "<p>PHP Logging test completed. Check the error logs to see if messages appeared.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test if we can write to a custom log file
$logFile = __DIR__ . '/test.log';
if (error_log("Test message to custom file at " . date('Y-m-d H:i:s') . "\n", 3, $logFile)) {
    echo "<p style='color: green;'>✅ Custom log file write: SUCCESS</p>";
    if (file_exists($logFile)) {
        echo "<p>Custom log content:</p>";
        echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ Custom log file write: FAILED</p>";
}

// Test form submission detection
if ($_POST) {
    error_log("=== FORM POST DETECTED ===");
    error_log("POST data: " . print_r($_POST, true));
    echo "<h2>Form Submitted!</h2>";
    echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
} else {
    echo "<h2>Test Form</h2>";
    echo '<form method="POST">';
    echo '<input type="text" name="test_field" placeholder="Enter something" required>';
    echo '<button type="submit">Test Submit</button>';
    echo '</form>';
}

error_log("=== PHP LOGGING TEST COMPLETE ===");
?>