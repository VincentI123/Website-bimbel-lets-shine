<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Mulai menangkap output
// --- AKHIR BAGIAN BARU ---


// --- "OTAK" BAHASA (VERSI UNIVERSAL REQUEST_URI + MANUAL QUERY + OB CLEAN) ---

// 1. Selalu mulai session di paling atas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan bahasa default
$defaultLang = 'id';

// 3. Cek jika pengguna MENGKLIK tombol ganti bahasa
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en', 'cn'])) {

    $_SESSION['lang'] = $_GET['lang'];

    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];

    $query_params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }

    unset($query_params['lang']);

    // Bangun ulang URL
    $queryStringParts = array();
    foreach ($query_params as $key => $value) {
        $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
    }
    $queryString = implode('&', $queryStringParts);

    $redirectUrl = $path . ($queryString ? '?' . $queryString : '');

    // Hentikan buffer dan lakukan redirect
    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    die('Redirecting...');
}

// 4. Tentukan bahasa yang akan digunakan
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;


// 5. Mengatur "Locale" PHP untuk Tanggal
if ($currentLang == 'id') {
    setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian_Indonesia.1252', 'Indonesian');
} elseif ($currentLang == 'cn') {
    setlocale(LC_TIME, 'zh_CN.UTF-8', 'zh_CN', 'Chinese_China.936', 'Chinese');
} else {
    setlocale(LC_TIME, 'en_US.UTF-8', 'en_US', 'English_United States.1252', 'English');
}

// 6. Muat file "kamus"
$langFile = __DIR__ . '/lang/' . $currentLang . '.php'; 
if (file_exists($langFile) && is_readable($langFile)) {
    $lang = include $langFile;
} else {
    $defaultFile = __DIR__ . '/lang/' . $defaultLang . '.php';
    $lang = (file_exists($defaultFile) && is_readable($defaultFile)) ? include $defaultFile : array();
}

// 7. Fungsi helper untuk terjemahan
if (!function_exists('__')) { // <--- TAMBAHKAN BARIS INI
    function __($key) {
        global $lang;
        if (is_array($lang) && isset($lang[$key])) {
            return $lang[$key];
        } else {
            return $key;
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
$db = Database::getInstance()->getConnection();
$slotId = isset($_GET['id']) ? $_GET['id'] : null;
$isEditing = $slotId !== null;
$slot = null;
$formError = null;

$pageTitleKey = $isEditing ? 'time_form_edit_title' : 'time_form_add_title';

if ($isEditing) {
    $stmt = $db->prepare("SELECT * FROM time_slots WHERE id = :id");
    $stmt->execute([':id' => $slotId]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ambil dan Sanitasi
    $time_label = isset($_POST['time_label']) ? trim($_POST['time_label']) : '';
    $time_value = isset($_POST['time_value']) ? trim($_POST['time_value']) : '';
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    // [BARU] Ambil Data Hari (Wajib Array)
    $specific_days_arr = isset($_POST['specific_days']) ? $_POST['specific_days'] : [];
    $specific_days_str = implode(',', $specific_days_arr);

    // 2. VALIDASI KERAS
    if (empty($time_label) || empty($time_value)) {
        $formError = __('time_error_empty'); 
    } 
    // [BARU] Validasi Wajib Pilih Hari
    // ... kode validasi kosong sebelumnya ...
    elseif (empty($specific_days_arr)) {
        $formError = __('time_error_days_required');
    }
    elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9],([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_value)) {
        $formError = __('time_error_format'); 
    } 
    else {
        // --- [PERBAIKAN BARU] Validasi Logika Waktu ---
        $times = explode(',', $time_value);
        $start_time = strtotime($times[0]); // Jam Mulai
        $end_time = strtotime($times[1]);   // Jam Selesai

        if ($start_time >= $end_time) {
            // Error jika Mulai lebih besar atau sama dengan Selesai
            $formError = sprintf(__('time_error_logic'), $times[0], $times[1]);
        } 
        else {
            // --- JIKA LOLOS VALIDASI, BARU SIMPAN KE DB ---
            try {
                if ($isEditing) {
                    // [UBAH] Update specific_days
                    $sql = "UPDATE time_slots SET time_label = :time_label, time_value = :time_value, display_order = :display_order, specific_days = :specific_days WHERE id = :id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':time_label' => $time_label,
                        ':time_value' => $time_value,
                        ':display_order' => $display_order,
                        ':specific_days' => $specific_days_str, 
                        ':id' => $slotId
                    ]);
                    $_SESSION['flash_message'] = __('time_success_update'); 
                } else {
                    // [UBAH] Insert specific_days
                    $sql = "INSERT INTO time_slots (time_label, time_value, display_order, specific_days) VALUES (:time_label, :time_value, :display_order, :specific_days)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':time_label' => $time_label,
                        ':time_value' => $time_value,
                        ':display_order' => $display_order,
                        ':specific_days' => $specific_days_str 
                    ]);
                    $_SESSION['flash_message'] = __('time_success_add'); 
                }

                ob_end_clean(); 
                header("Location: admin-dashboard.php?page=times"); 
                exit;
            } catch (PDOException $e) {
                $formError = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<?php
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}

$queryParams_id = $query_params;
$queryParams_id['lang'] = 'id';
$url_id = $path . '?' . http_build_query($queryParams_id);

$queryParams_en = $query_params;
$queryParams_en['lang'] = 'en';
$url_en = $path . '?' . http_build_query($queryParams_en);

$queryParams_cn = $query_params;
$queryParams_cn['lang'] = 'cn';
$url_cn = $path . '?' . http_build_query($queryParams_cn);
?>

<div class="flex space-x-4 mb-4 justify-end">
    <a href="<?php echo $url_id; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'id' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="Bahasa Indonesia">
        <img src="https://flagcdn.com/w40/id.png" srcset="https://flagcdn.com/w80/id.png 2x" width="20" height="15" alt="Indonesia" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">ID</span>
    </a>
    <a href="<?php echo $url_en; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'en' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="English">
        <img src="https://flagcdn.com/w40/gb.png" srcset="https://flagcdn.com/w80/gb.png 2x" width="20" height="15" alt="United Kingdom" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">EN</span>
    </a>
    <a href="<?php echo $url_cn; ?>" class="flex items-center gap-2 px-2 py-1 rounded-md transition-colors <?php echo ($currentLang == 'cn' ? 'bg-orange-50 text-orange-600 ring-1 ring-orange-200' : 'text-gray-500 hover:text-orange-500 hover:bg-orange-50'); ?>" title="中文">
        <img src="https://flagcdn.com/w40/cn.png" srcset="https://flagcdn.com/w80/cn.png 2x" width="20" height="15" alt="China" class="rounded-sm shadow-sm">
        <span class="font-semibold text-sm">CN</span>
    </a>
</div>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __($pageTitleKey); ?></h1>
        <a href="admin-dashboard.php?page=times" class="text-orange-600 hover:text-orange-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
             <?php echo __('time_form_back_link'); ?>
        </a>
    </div>

    <?php if (isset($formError)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
            <p><?php echo htmlspecialchars($formError); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-2xl shadow-lg shadow-orange-900/5">
        <form method="POST">
            <div class="space-y-6">
                <div>
                    <label for="time_label" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('time_form_label_label'); ?></label>
                    <div class="relative">
                        <input type="text" id="time_label" name="time_label" 
                               value="<?php echo htmlspecialchars(isset($slot['time_label']) ? $slot['time_label'] : ''); ?>" 
                               required 
                               class="contact-input" 
                               placeholder="<?php echo __('time_form_placeholder_label'); ?>"
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('time_error_label')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>
                </div>
                
                <div>
                    <label for="time_value" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('time_form_label_value'); ?></label>
                    <div class="relative">
                        <input type="text" id="time_value" name="time_value" 
                            value="<?php echo htmlspecialchars(isset($slot['time_value']) ? $slot['time_value'] : ''); ?>" 
                            required 
                            class="contact-input" 
                            placeholder="<?php echo __('time_form_placeholder_value'); ?>"
                            maxlength="11" 
                            oninvalid="this.setCustomValidity('<?php echo addslashes(__('time_error_value')); ?>')"
                            oninput="this.setCustomValidity('')">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format: <strong>HH:MM,HH:MM</strong> (Contoh: 09:30,11:00)</p>
                </div>
                
                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('time_form_label_order'); ?></label>
                    <div class="relative">
                        <input type="number" id="display_order" name="display_order" 
                               value="<?php echo htmlspecialchars(isset($slot['display_order']) ? $slot['display_order'] : '0'); ?>" 
                               required 
                               class="contact-input" 
                               min="0"
                               oninvalid="this.setCustomValidity('<?php echo addslashes(__('time_error_order')); ?>')"
                               oninput="this.setCustomValidity('')">
                    </div>

                    <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('time_form_label_days'); ?> <span class="text-red-500">*</span></label>
                    <p class="text-xs text-gray-500 mb-3"><?php echo __('time_form_help_days'); ?></p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php
                        // Mapping Hari untuk Checkbox
                        $days_map = [
                            'Monday' => __('Monday'), 'Tuesday' => __('Tuesday'), 'Wednesday' => __('Wednesday'),
                            'Thursday' => __('Thursday'), 'Friday' => __('Friday'), 'Saturday' => __('Saturday')
                        ];
                        
                        // Ambil data tersimpan atau dari POST (jika error)
                        $saved_days = [];
                        if (isset($_POST['specific_days'])) {
                            $saved_days = $_POST['specific_days'];
                        } elseif (isset($slot['specific_days'])) {
                            $saved_days = explode(',', $slot['specific_days']);
                        }
                        
                        foreach ($days_map as $day_en => $day_trans) {
                            $checked = in_array($day_en, $saved_days) ? 'checked' : '';
                            echo "<label class='flex items-center space-x-2 cursor-pointer bg-gray-50 p-2 rounded border border-gray-200 hover:bg-orange-50 transition-colors'>";
                            echo "<input type='checkbox' name='specific_days[]' value='$day_en' class='rounded text-orange-600 focus:ring-orange-500 h-4 w-4' $checked>";
                            echo "<span class='text-sm text-gray-700'>$day_trans</span>";
                            echo "</label>";
                        }
                        ?>
                    </div>
                </div>
                </div>
            
            <div class="mt-8 flex justify-end gap-4"></div>
                </div>
            </div>
            
            <div class="mt-8 flex justify-end gap-4">
                 <a href="admin-dashboard.php?page=times" class="px-6 py-2 rounded-lg text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"><?php echo __('user_form_button_cancel'); ?></a>
                <button type="submit" class="btn-gradient text-white font-semibold px-6 py-2 rounded-lg">
                    <?php echo $isEditing ? __('time_form_button_update') : __('time_form_button_create'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Inisialisasi Ikon
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // --- 1. VALIDASI NILAI WAKTU (SISTEM) ---
    // Hanya: Angka, Titik Dua (:), dan Koma (,)
    const timeValueInput = document.getElementById('time_value');
    if (timeValueInput) {
        timeValueInput.addEventListener('input', function(e) {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            
            // Hapus karakter selain Angka, :, dan ,
            let cleanValue = this.value.replace(/[^0-9:,]/g, '');

            if (this.value !== cleanValue) {
                this.value = cleanValue;
                this.setSelectionRange(start - 1, end - 1);
            }
        });
    }

    // --- 2. [BARU] VALIDASI LABEL WAKTU (TAMPILAN) ---
    // Hanya: Angka, Strip (-), Titik Dua (:), dan Spasi
    const timeLabelInput = document.getElementById('time_label');
    if (timeLabelInput) {
        timeLabelInput.addEventListener('input', function(e) {
            const start = this.selectionStart;
            const end = this.selectionEnd;

            // Hapus semua karakter KECUALI:
            // 0-9 (Angka)
            // -   (Strip)
            // :   (Titik Dua - untuk jam)
            // \s  (Spasi - agar bisa dipisah)
            let cleanValue = this.value.replace(/[^0-9:\-\s]/g, '');

            if (this.value !== cleanValue) {
                this.value = cleanValue;
                
                // Kembalikan posisi kursor
                // Cek agar posisi tidak error negatif
                let newPos = start - 1;
                if (newPos < 0) newPos = 0;
                
                this.setSelectionRange(newPos, newPos);
            }
        });
    }
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>