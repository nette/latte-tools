<?php

// Basic isset in value attribute
?>
<input type="text" name="established" value="<?php if (isset($company)) { echo $company->established; } ?>" class="dateEU">
<?php

// !empty condition
?>
<input type="text" value="<?php if (!empty($name)) { echo $name; } ?>" />
<?php

// Multiple conditional attributes in same element
?>
<input type="text" value="<?php if (isset($user)) { echo $user->name; } ?>" data-id="<?php if (isset($user)) { echo $user->id; } ?>" class="field">
<?php

// With other non-conditional attributes
?>
<input type="text" name="field" value="<?php if (isset($val)) { echo $val; } ?>" placeholder="Enter value" class="input">
<?php

// Property access on variable
?>
<div data-info="<?php if (isset($data)) { echo $data->info; } ?>">Content</div>
<?php

// Array access
?>
<input value="<?php if (isset($arr['key'])) { echo $arr['key']; } ?>" />
<?php

// Simple variable
?>
<input value="<?php if (isset($simple)) { echo $simple; } ?>" />
