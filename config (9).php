<?php
session_start();

// Aapka Firebase URL[cite: 2]
define('FIREBASE_URL', 'https://lottery-8f331-default-rtdb.firebaseio.com');

// Data read karne ke liye (Login Check)
function firebase_read($path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FIREBASE_URL . '/' . $path . '.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Data save karne ke liye (Register)
function firebase_write($path, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FIREBASE_URL . '/' . $path . '.json');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($action === 'register') {
        $invite = $_POST['invite'] ?? '';
        $existingUser = firebase_read("users/" . $phone);
        
        if ($existingUser) {
            echo json_encode(['success' => false, 'message' => 'Bhai, ye number pehle se register hai!']);
        } else {
            $userData = ['phone' => $phone, 'password' => $password, 'invite_code' => $invite, 'balance' => 0];
            firebase_write("users/" . $phone, $userData);
            $_SESSION['phone'] = $phone;
            echo json_encode(['success' => true, 'message' => 'Account Create Ho Gaya OP!']);
        }
        exit;
    }

    if ($action === 'login') {
        $user = firebase_read("users/" . $phone);
        if ($user && isset($user['password']) && $user['password'] === $password) {
            $_SESSION['phone'] = $phone;
            echo json_encode(['success' => true, 'message' => 'Login Successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Galat Number ya Password bhai!']);
        }
        exit;
    }
}
?>