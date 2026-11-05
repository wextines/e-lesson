<?php
require '../php/config.php';
require 'Login_functions.php';
header('Content-Type: application/json');

if (isLoggedIn()) {
    $user = getCurrentUser();
    echo json_encode([
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'phone'      => $user['phone'],
        'email'      => $user['email'],
        'points'     => $user['points']
    ]);
} else {
    echo json_encode(['error' => 'Avtorizatsiya talab qilinadi!']);
}
