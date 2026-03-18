<?php

// Extensive tests for n:attr combined with n:if on same element
// These test various combinations of conditional attributes and control structures

// Test 1: Single conditional attr with n:if
if ($show) {
    ?><input value="<?php if (isset($val)) echo $val; ?>" /><?php
}

// Test 2: Multiple conditional attrs with n:if
if ($visible) {
    ?><input value="<?php if (isset($name)) echo $name; ?>" data-id="<?php if (isset($id)) echo $id; ?>" /><?php
}

// Test 3: n:if with data-* attributes
if ($enabled) {
    ?><div data-user="<?php if (isset($user)) echo $user; ?>" data-role="<?php if (isset($role)) echo $role; ?>"><?php
}

// Test 4: n:if with ARIA attributes
if ($accessible) {
    ?><button aria-label="<?php if (isset($label)) echo $label; ?>" aria-describedby="<?php if (isset($desc)) echo $desc; ?>"><?php
}

// Test 5: n:if with mixed quote styles
if ($mixed) {
    ?><input type='text' value="<?php if (isset($val)) echo $val; ?>" /><?php
}

// Test 6: n:if with method call in conditional attr
if ($showObj) {
    ?><input value="<?php if (isset($obj)) echo $obj->getValue(); ?>" /><?php
}

// Test 7: n:if with array access in conditional attr
if ($showArray) {
    ?><input value="<?php if (isset($arr['key'])) echo $arr['key']; ?>" /><?php
}

// Test 8: n:if with property chain in conditional attr
if ($showChain) {
    ?><input value="<?php if (isset($user->profile)) echo $user->profile->name; ?>" /><?php
}

// Test 9: n:if with !empty() condition
if ($showEmpty) {
    ?><input value="<?php if (!empty($name)) echo $name; ?>" /><?php
}

// Test 10: n:if with checked attribute (boolean)
if ($showChecked) {
    ?><input type="checkbox" checked="<?php if (isset($isChecked)) echo $isChecked; ?>" /><?php
}

// Test 11: n:if with style attribute
if ($showStyled) {
    ?><div style="<?php if (isset($style)) echo $style; ?>"><?php
}

// Test 12: n:if with class attribute
if ($showClass) {
    ?><span class="<?php if (isset($className)) echo $className; ?>"><?php
}

// Test 13: n:if with src attribute (images)
if ($showImage) {
    ?><img src="<?php if (isset($src)) echo $src; ?>" /><?php
}

// Test 14: n:if with href attribute (links)
if ($showLink) {
    ?><a href="<?php if (isset($url)) echo $url; ?>"><?php
}

// Test 15: n:if with multiple attrs including standard ones
if ($showMultiple) {
    ?><input type="text" name="field" value="<?php if (isset($val)) echo $val; ?>" placeholder="<?php if (isset($ph)) echo $ph; ?>" /><?php
}

// Test 16: n:if with self-closing tag and conditional attr
if ($showBr) {
    ?><br data-info="<?php if (isset($info)) echo $info; ?>" /><?php
}

// Test 17: n:if with void element and conditional attr
if ($showMeta) {
    ?><meta content="<?php if (isset($content)) echo $content; ?>" /><?php
}

// Test 18: n:if with title attribute
if ($showTitle) {
    ?><abbr title="<?php if (isset($title)) echo $title; ?>"><?php
}

// Test 19: n:if with alt attribute (images)
if ($showAlt) {
    ?><img src="pic.jpg" alt="<?php if (isset($alt)) echo $alt; ?>" /><?php
}

// Test 20: Complex scenario - n:if with n:foreach sibling (nested)
if ($showComplex) {
    foreach ($items as $item) {
        ?><input value="<?php if (isset($item->val)) echo $item->val; ?>" /><?php
    }
}

// Test 21: n:if with disabled attribute
if ($showDisabled) {
    ?><button disabled="<?php if (isset($isDisabled)) echo $isDisabled; ?>"><?php
}

// Test 22: n:if with readonly attribute
if ($showReadonly) {
    ?><input readonly="<?php if (isset($isReadonly)) echo $isReadonly; ?>" /><?php
}

// Test 23: n:if with selected attribute (dropdowns)
if ($showSelected) {
    ?><option selected="<?php if (isset($isSelected)) echo $isSelected; ?>"><?php
}

// Test 24: n:if with colspan attribute (tables)
if ($showColspan) {
    ?><td colspan="<?php if (isset($span)) echo $span; ?>"><?php
}

// Test 25: n:if with rowspan attribute (tables)
if ($showRowspan) {
    ?><td rowspan="<?php if (isset($span)) echo $span; ?>"><?php
}

// Test 26: n:if with target attribute (links)
if ($showTarget) {
    ?><a target="<?php if (isset($target)) echo $target; ?>"><?php
}

// Test 27: n:if with rel attribute (links)
if ($showRel) {
    ?><a rel="<?php if (isset($rel)) echo $rel; ?>"><?php
}

// Test 28: n:if with type attribute (inputs)
if ($showType) {
    ?><input type="<?php if (isset($type)) echo $type; ?>" /><?php
}

// Test 29: n:if with name attribute
if ($showName) {
    ?><input name="<?php if (isset($name)) echo $name; ?>" /><?php
}

// Test 30: n:if with id attribute
if ($showId) {
    ?><div id="<?php if (isset($id)) echo $id; ?>"><?php
}

// Test 31: n:if with maxlength attribute
if ($showMaxlength) {
    ?><input maxlength="<?php if (isset($max)) echo $max; ?>" /><?php
}

// Test 32: n:if with size attribute
if ($showSize) {
    ?><input size="<?php if (isset($size)) echo $size; ?>" /><?php
}

// Test 33: n:if with width attribute
if ($showWidth) {
    ?><img width="<?php if (isset($width)) echo $width; ?>" /><?php
}

// Test 34: n:if with height attribute
if ($showHeight) {
    ?><img height="<?php if (isset($height)) echo $height; ?>" /><?php
}

// Test 35: n:if with pattern attribute (validation)
if ($showPattern) {
    ?><input pattern="<?php if (isset($pattern)) echo $pattern; ?>" /><?php
}

// Test 36: n:if with min/max attributes
if ($showRange) {
    ?><input min="<?php if (isset($min)) echo $min; ?>" max="<?php if (isset($max)) echo $max; ?>" /><?php
}

// Test 37: n:if with step attribute
if ($showStep) {
    ?><input step="<?php if (isset($step)) echo $step; ?>" /><?php
}

// Test 38: n:if with placeholder and value attrs
if ($showBoth) {
    ?><input placeholder="<?php if (isset($ph)) echo $ph; ?>" value="<?php if (isset($val)) echo $val; ?>" /><?php
}

// Test 39: n:if with formaction attribute (buttons)
if ($showFormaction) {
    ?><button formaction="<?php if (isset($action)) echo $action; ?>"><?php
}

// Test 40: n:if with formmethod attribute
if ($showFormmethod) {
    ?><button formmethod="<?php if (isset($method)) echo $method; ?>"><?php
}
