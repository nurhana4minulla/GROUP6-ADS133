<?php
declare(strict_types=1);

function page_start(string $title,string $area='public'): void {
    $body=e($area);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.e($title).' | Prime Pool Peninsula</title><link rel="icon" href="assets/brand.png"><link rel="stylesheet" href="assets/bootstrap.min.css"><link rel="stylesheet" href="assets/site.css"><script src="assets/site.js" defer></script></head><body class="'.$body.'">';
    echo '<a class="skip-link" href="#main">Skip to content</a>';
}
function page_end(): void { echo '</body></html>'; }
function notice(?string $error=null): void {
    if ($error) echo '<div class="alert alert-danger" role="alert">'.e($error).'</div>';
    if ($notice=$_SESSION['notice']??null) {
        unset($_SESSION['notice']);
        echo '<div class="alert alert-success" role="status">'.e($notice).'</div>';
    }
}
function input(string $label,string $name,mixed $value='',string $type='text',bool $required=false,int $max=160): void {
    echo '<label class="field"><span>'.e($label).'</span><input class="form-control" type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" maxlength="'.$max.'"'.($required?' required':'').'></label>';
}
function select_field(string $label,string $name,array $values,mixed $value='',bool $required=false): void {
    echo '<label class="field"><span>'.e($label).'</span><select class="form-select" name="'.e($name).'"'.($required?' required':'').'><option value="">Choose</option>';
    foreach ($values as $k=>$v) {
        if (array_is_list($values)) $k=$v;
        echo '<option value="'.e($k).'"'.((string)$value===(string)$k?' selected':'').'>'.e($v).'</option>';
    }
    echo '</select></label>';
}
function textarea(string $label,string $name,mixed $value='',int $max=3000,bool $required=false): void {
    echo '<label class="field"><span>'.e($label).'</span><textarea class="form-control" rows="3" name="'.e($name).'" maxlength="'.$max.'"'.($required?' required':'').'>'.e($value).'</textarea></label>';
}
function check_group(string $label,string $name,array $choices,array $data): void {
    echo '<fieldset class="field"><legend>'.e($label).'</legend><div class="checks">';
    $values=$data[$name]??[];
    foreach ($choices as $choice) echo '<label><input class="form-check-input" type="checkbox" name="'.e($name).'[]" value="'.e($choice).'"'.(is_array($values)&&in_array($choice,$values,true)?' checked':'').'> '.e($choice).'</label>';
    echo '</div></fieldset>';
}
function button(string $text,string $class='btn-primary'): void { echo '<button class="btn '.e($class).'" type="submit">'.e($text).'</button>'; }
function form_start(string $action,?int $id=null): void {
    $api=$action==='content'?' data-cms-api':'';
    echo '<form method="post" class="stack"'.$api.'>'.csrf_input().'<input type="hidden" name="action" value="'.e($action).'">';
    if ($id) echo '<input type="hidden" name="id" value="'.$id.'">';
}
function badge(string $status): void { echo '<span class="status status-'.e($status).'">'.e(ucfirst($status)).'</span>'; }
