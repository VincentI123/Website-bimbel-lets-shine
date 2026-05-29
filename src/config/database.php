<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'bimbel_letshine');
define('DB_USER', 'root');
define('DB_PASS', ''); // Kosongkan jika tidak ada password

// Konfigurasi Aplikasi
define('APP_NAME', 'Bimbel Let\'s Shine');
define('BASE_URL', '/'); // Sesuaikan jika proyek ada di subfolder

// Pengaturan Sesi
ini_set('session.cookie_lifetime', 86400); // 24 jam

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die("Koneksi database gagal. Silakan cek file `src/config/database.php` dan pastikan database sudah diimpor.");
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

