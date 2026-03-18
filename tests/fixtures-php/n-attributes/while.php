<?php

// Simple while with single HTML element (only echo)
$i = 0;
while ($i < 10) {
    echo "<li>Item {$i}</li>";
}

// While with complex condition
while ($row = $result->fetch()) {
    echo "<tr><td>Row</td></tr>";
}
