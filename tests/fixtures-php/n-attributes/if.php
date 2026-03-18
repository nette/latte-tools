<?php

// Simple if with single HTML element
if ($user) {
    echo "<div class='user'>{$user->name}</div>";
}

// If with else
if ($error) {
    echo "<div class='error'>{$error}</div>";
} else {
    echo "<div class='success'>Success!</div>";
}

// If with elseif
if ($status === 'active') {
    echo "<span class='status-active'>Active</span>";
} elseif ($status === 'pending') {
    echo "<span class='status-pending'>Pending</span>";
} else {
    echo "<span class='status-inactive'>Inactive</span>";
}

// If with complex condition
if ($user && $user->isAdmin() && $config['feature_enabled']) {
    echo "<button>Admin Action</button>";
}

// Nested if (outer should not be converted, inner should)
if ($showContent) {
    if ($item) {
        echo "<p>{$item->name}</p>";
    }
}
