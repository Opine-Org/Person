<?php
return function ($arguments, $options) {
    if (!isset($options['email'])) {
        $options['email'] = 'test@email.com';
    }
    if (!isset($options['s'])) {
        $options['s'] = 80;
    }
    if (!isset($options['d'])) {
        $options['d'] = 'mm';
    }
    if (!isset($options['r'])) {
        $options['r'] = 'mm';
    }
    return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($options['email']))) . '?s=' . $options['s'] . '&d=' . $options['d'] . '&r=' . $options['r'];
};