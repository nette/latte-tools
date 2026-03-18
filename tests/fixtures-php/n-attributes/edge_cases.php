<?php

// Edge case 1: Multiple n:attr on same element (top-level)
?><input type="text" value="<?php if (isset($user)) { echo $user->name; } ?>" data-id="<?php if (isset($user)) { echo $user->id; } ?>" class="field"><?php

// Edge case 2: Three conditional attributes
?><input value="<?php if (isset($a)) echo $a; ?>" data-x="<?php if (isset($b)) echo $b; ?>" data-y="<?php if (isset($c)) echo $c; ?>" /><?php

// Edge case 3: n:attr inside if block
if ($show) {
    ?><input value="<?php if (isset($val)) echo $val; ?>" /><?php
}

// Edge case 4: n:attr inside foreach
foreach ($items as $item) {
    ?><input value="<?php if (isset($item)) echo $item; ?>" /><?php
}

// Edge case 5: n:attr inside for loop
for ($i = 0; $i < 10; $i++) {
    ?><input value="<?php if (isset($arr[$i])) echo $arr[$i]; ?>" /><?php
}

// Edge case 6: n:attr inside while
while ($cond) {
    ?><input value="<?php if (isset($val)) echo $val; ?>" /><?php
}

// Edge case 7: Conditional attr followed by static attr
?><input value="<?php if (isset($val)) echo $val; ?>" name="static_name" class="input" /><?php

// Edge case 8: Empty condition (!empty vs isset)
?><input value="<?php if (!empty($name)) echo $name; ?>" /><?php

// Edge case 9: Property access in conditional attr
?><input value="<?php if (isset($user->profile)) echo $user->profile->name; ?>" /><?php

// Edge case 10: Array access in conditional attr  
?><input value="<?php if (isset($data['key'])) echo $data['key']; ?>" /><?php

// Edge case 11: Mixed quotes - single quotes
?><input type='text' value='<?php if (isset($val)) echo $val; ?>' /><?php

// Edge case 12: n:if wrapping element with conditional attr
if ($visible) {
    ?><input value="<?php if (isset($data)) echo $data; ?>" /><?php
}

// Edge case 13: n:foreach wrapping element with conditional attr
foreach ($list as $entry) {
    ?><input value="<?php if (isset($entry->value)) echo $entry->value; ?>" /><?php
}

// Edge case 14: Deeply nested - if inside foreach with conditional attr
foreach ($users as $user) {
    if ($user->active) {
        ?><input value="<?php if (isset($user->name)) echo $user->name; ?>" /><?php
    }
}

// Edge case 15: Multiple conditional attrs inside nested structure
if ($showForm) {
    ?><input value="<?php if (isset($name)) echo $name; ?>" data-id="<?php if (isset($id)) echo $id; ?>" /><?php
}

// Edge case 16: Conditional attr with method call
?><input value="<?php if (isset($obj)) echo $obj->getValue(); ?>" /><?php

// Edge case 17: Conditional attr in elseif block
if ($type === 'a') {
    ?><input value="<?php if (isset($a)) echo $a; ?>" /><?php
} elseif ($type === 'b') {
    ?><input value="<?php if (isset($b)) echo $b; ?>" /><?php
}

// Edge case 18: Self-closing tag with conditional attr
?><br value="<?php if (isset($val)) echo $val; ?>" /><?php

// Edge case 19: Void element with conditional attr
?><img src="test.jpg" alt="<?php if (isset($alt)) echo $alt; ?>" /><?php

// Edge case 20: Nested control structures with multiple attrs
foreach ($groups as $group) {
    if ($group->visible) {
        foreach ($group->items as $item) {
            ?><input value="<?php if (isset($item->val)) echo $item->val; ?>" data-group="<?php if (isset($group->id)) echo $group->id; ?>" /><?php
        }
    }
}
