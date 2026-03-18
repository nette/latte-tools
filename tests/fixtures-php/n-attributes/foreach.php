<?php

// Simple foreach with single HTML element
foreach ($items as $item) {
    echo "<li>{$item->name}</li>";
}

// Foreach with key
foreach ($users as $id => $user) {
    echo "<div class='user' data-id='{$id}'>{$user->name}</div>";
}

// Foreach with reference
foreach ($items as &$item) {
    echo "<span>{$item}</span>";
}

// Nested foreach (outer should not be converted, inner should)
foreach ($categories as $category) {
    foreach ($category->items as $item) {
        echo "<p>{$item->name}</p>";
    }
}
