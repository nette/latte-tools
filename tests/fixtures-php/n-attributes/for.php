<?php

// Simple for with single HTML element
for ($i = 0; $i < 10; $i++) {
    echo "<li>Item {$i}</li>";
}

// For with multiple conditions
for ($i = 0, $j = 0; $i < 10 && $j < 5; $i++, $j++) {
    echo "<span>{$i}-{$j}</span>";
}

// Nested for (outer should not be converted, inner should)
for ($i = 0; $i < 3; $i++) {
    for ($j = 0; $j < 3; $j++) {
        echo "<td>{$i},{$j}</td>";
    }
}
