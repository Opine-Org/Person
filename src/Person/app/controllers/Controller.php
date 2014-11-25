<?php
namespace Opine\Person;

class Controller {
    public function logout () {
        setcookie('api_token', '', time()-3600);
        if (isset($_COOKIE['api_key'])) {
            unset($_COOKIE['api_key']);
        }
        $redirect = '/';
        if (isset($_GET['redirect'])) {
            $redirect = $_GET['redirect'];
        }
        header('Location: ' . $redirect);
    }
}