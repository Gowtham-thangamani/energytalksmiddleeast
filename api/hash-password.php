<?php
// ============================================
// Password Hash Generator - DELETE AFTER USE!
// ============================================
// Visit this URL once to generate a password hash
// Then use the hash in your setup.sql or update the users table
// DELETE THIS FILE after you're done!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        echo "<p style='font-family:monospace;background:#222;color:#0f0;padding:1rem;border-radius:8px;word-break:break-all'>Hash: <strong>" . htmlspecialchars($hash) . "</strong></p>";
        echo "<p style='color:#f7931e;margin-top:1rem'>Use this hash in your database. Run this SQL in phpMyAdmin:</p>";
        echo "<pre style='background:#222;color:#fff;padding:1rem;border-radius:8px'>UPDATE users SET password_hash = '" . htmlspecialchars($hash) . "' WHERE username = 'admin';</pre>";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Password Hash Generator</title>
<style>body{font-family:sans-serif;background:#0d1117;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}form{background:#21262d;padding:2rem;border-radius:12px;width:400px}input{width:100%;padding:0.8rem;border:1px solid #333;border-radius:6px;background:#161b22;color:#fff;font-size:1rem;margin:0.5rem 0}button{width:100%;padding:0.8rem;background:linear-gradient(135deg,#f7931e,#ff6b35);color:#0d1117;border:none;border-radius:6px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:0.5rem}h2{color:#f7931e;margin-bottom:1rem}p.warn{color:#ef4444;font-size:0.85rem;margin-top:1rem}</style>
</head>
<body>
<form method="POST">
    <h2>Generate Password Hash</h2>
    <label>Enter your admin password:</label>
    <input type="text" name="password" placeholder="Enter password..." required>
    <button type="submit">Generate Hash</button>
    <p class="warn">DELETE this file after generating your hash!</p>
</form>
</body>
</html>
