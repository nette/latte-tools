<?php

// Simple try with single HTML element
try {
    echo "<div>Success content</div>";
} catch (Exception $e) {
    echo "<div>Error occurred</div>";
}

// Try with else clause
try {
    echo "<span>Loading...</span>";
} catch (Exception $e) {
    echo "<span>Failed to load</span>";
}

// Nested try inside if (try should be converted to n:try)
if ($showContent) {
    try {
        echo "<p>Content</p>";
    } catch (Exception $e) {
        echo "<p>Error</p>";
    }
}
