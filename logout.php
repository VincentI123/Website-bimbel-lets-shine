<?php
require_once __DIR__ . '/src/includes/auth.php';

// 1. Mulai session untuk membaca data terakhir
initAuth();

// 2. Simpan bahasa terakhir sebelum session dihancurkan
$last_language = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'id';

// 3. Hapus session (Logout)
logoutUser();

// 4. Redirect ke login DENGAN membawa parameter bahasa
header("Location: login.php?logout=1&lang=" . $last_language);
exit();
?>