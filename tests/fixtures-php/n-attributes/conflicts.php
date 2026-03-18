<?php

// Test: n:attr should not duplicate if element already has n:attr (manual)
// Note: This tests that the converter doesn't create duplicate n:attr

// Scenario 1: Element with existing n:if and conditional attr
if ($visible) {
    ?><input value="<?php if (isset($val)) echo $val; ?>" /><?php
}

// Scenario 2: Element with existing n:foreach and conditional attr
foreach ($items as $item) {
    ?><input value="<?php if (isset($item->name)) echo $item->name; ?>" /><?php
}

// Scenario 3: Multiple conditional attrs with existing n: structure
foreach ($users as $user) {
    if ($user->active) {
        ?><input value="<?php if (isset($user->data)) echo $user->data; ?>" data-id="<?php if (isset($user->id)) echo $user->id; ?>" /><?php
    }
}

// Scenario 4: Conditional attr on element with multiple existing n: attrs
// This shouldn't happen in practice, but test that we handle it gracefully

// Scenario 5: Empty value handling
?><input value="<?php if (isset($emptyVal)) echo $emptyVal; ?>" /><?php

// Scenario 6: Null value handling
?><input value="<?php if (isset($nullVal)) echo $nullVal; ?>" /><?php

// Scenario 7: Boolean value handling
?><input checked="<?php if (isset($isChecked)) echo $isChecked; ?>" /><?php

// Scenario 8: Data attributes specifically
?><div data-user-id="<?php if (isset($userId)) echo $userId; ?>" data-role="<?php if (isset($role)) echo $role; ?>"><?php
