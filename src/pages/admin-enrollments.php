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

// 8. Fungsi Helper Format Tanggal Manual (Agar Mandarin Aman)
if (!function_exists('date_format_intl')) {
    function date_format_intl($date_string, $lang) {
        $timestamp = strtotime($date_string);
        if ($lang == 'cn') {
            // Format Mandarin: YYYY年MM月DD日
            return date('Y', $timestamp) . '年' . date('m', $timestamp) . '月' . date('d', $timestamp) . '日';
        } elseif ($lang == 'id') {
            // Format Indonesia
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        } else {
            // Format Inggris
            return date('d F Y', $timestamp);
        }
    }
}
// --- AKHIR DARI "OTAK" BAHASA ---


// --- KODE ASLI HALAMAN ANDA DIMULAI DI SINI ---
// Dapatkan koneksi PDO
$pdo = Database::getInstance()->getConnection();

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $delete_stmt->execute([$id_to_delete]);

        $_SESSION['flash_message'] = __('enrollments_status_deleted');

        ob_end_clean(); 
        header("Location: admin-dashboard.php?page=enrollments");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error deleting record: " . $e->getMessage();
        ob_end_clean(); 
        header("Location: admin-dashboard.php?page=enrollments");
        exit;
    }
}

// --- PERUBAHAN: Logika pencarian PHP dihapus karena akan menggunakan JS ---
$search_query = ""; 

// Query SQL
// SELECT * akan otomatis mengambil kolom 'available_days' dan 'available_times' jika ada di DB
$sql = "SELECT * FROM users WHERE role = 'student' AND status = 'approved'";
$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil dan hapus pesan flash dari session
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
$flash_error = null;
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Array untuk menerjemahkan grade level
$grade_translations = [
    'Pre-Nursery' => __('student_form_grade_prenursery'),
    'Kindergarten' => __('student_form_grade_kindergarten'),
    'Elementary' => __('student_form_grade_elementary'),
    'Junior High' => __('student_form_grade_juniorhigh'),
    'Senior High' => __('student_form_grade_seniorhigh')
];

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
<?php if ($flash_message): ?>
<div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg shadow border-l-4 border-green-500 flex items-center gap-2" role="alert">
    <i data-lucide="check-circle" class="w-5 h-5"></i>
    <p><?php echo $flash_message; ?></p>
</div>
<?php endif; ?>
<?php if ($flash_error): ?>
<div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg shadow">
    <?php echo $flash_error; ?>
</div>
<?php endif; ?>


<h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo __('enrollments_page_title'); ?></h1>
<p class="text-gray-600 mb-6"><?php echo __('enrollments_page_subtitle'); ?></p>

<div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg shadow-orange-900/5">
    <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
        <div class="relative w-full md:w-auto">
            <input type="text" id="studentSearchInput" placeholder="<?php echo __('enrollments_search_placeholder'); ?>" class="w-full px-4 py-2 pl-10 border rounded-lg focus:ring-orange-500 focus:border-orange-500" value="">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
        </div>
        <a href="admin-dashboard.php?page=student-form" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center gap-2 w-full md:w-auto justify-center">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
             <span class="truncate"><?php echo __('enrollments_add_new'); ?></span>
        </a>
    </div>
    <div class="overflow-x-auto hidden md:block">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_no'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_name'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_grade'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_subjects'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_days'); ?></th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_actions'); ?></th>
                </tr>
            </thead>
            <tbody id="studentTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($students)): ?>
                    <tr id="noStudentsRow">
                         <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            <?php echo __('enrollments_table_empty'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $index => $student):
                        // --- LOGIKA DATA (SAMA DENGAN SEBELUMNYA) ---
                        $days_display = __('enrollments_modal_na');
                        if (!empty($student['available_days'])) {
                            $days_array = explode(',', $student['available_days']);
                            $translated_days = [];
                            foreach ($days_array as $day) { $translated_days[] = __(trim($day)); }
                            $days_display = implode(', ', $translated_days);
                        }

                        $grade_key = isset($student["grade_level"]) ? $student["grade_level"] : null;
                        $grade_display = (!empty($grade_key) && isset($grade_translations[$grade_key])) ? $grade_translations[$grade_key] : ($grade_key ?: __('enrollments_modal_na'));
                        
                        $detailData = [
                            __('enrollments_table_name') => $student["name"],
                            __('users_table_username') => $student["username"], 
                            __('student_form_label_phone') => !empty($student["phone_number"]) ? $student["phone_number"] : __('enrollments_modal_na'),
                            __('student_form_label_address') => !empty($student["address"]) ? $student["address"] : __('enrollments_modal_na'),
                            __('student_form_label_birth_place') . ' & ' . __('student_form_label_birth_date') => (!empty($student['birth_place']) && !empty($student['birth_date'])) ? htmlspecialchars($student['birth_place']) . ', ' . date_format_intl($student['birth_date'], $currentLang) : __('enrollments_modal_na'),
                            __('enrollments_table_grade') => $grade_display,
                            __('student_form_label_school') => !empty($student["school"]) ? htmlspecialchars($student["school"]) : __('enrollments_modal_na'),
                            __('enrollments_table_subjects') => !empty($student["subjects"]) ? htmlspecialchars(str_replace(',', ', ', $student["subjects"])) : __('enrollments_modal_na'),
                            __('student_form_label_days') => htmlspecialchars($days_display),
                            __('student_form_label_times') => !empty($student["available_times"]) ? htmlspecialchars(str_replace(',', ', ', $student["available_times"])) : __('enrollments_modal_na'),
                            __('student_form_label_parent_name') => !empty($student["parent_name"]) ? htmlspecialchars($student["parent_name"]) : __('enrollments_modal_na'),
                            __('student_form_label_parent_phone') => !empty($student["parent_phone"]) ? htmlspecialchars($student["parent_phone"]) : __('enrollments_modal_na'),
                            __('approval_table_date') => !empty($student['created_at']) ? date_format_intl($student['created_at'], $currentLang) . ' ' . date('H:i', strtotime($student['created_at'])) : __('enrollments_modal_na') 
                        ];
                    ?>
                        <tr id="student-row-<?php echo $student['id']; ?>" class="student-row"
                            data-details='<?php echo htmlspecialchars(json_encode($detailData), ENT_QUOTES, 'UTF-8'); ?>'>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $index + 1; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $grade_display; ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-normal break-words max-w-xs">
                                <?php echo !empty($student['subjects']) ? htmlspecialchars(str_replace(',', ', ', $student['subjects'])) : __('enrollments_modal_na'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-normal break-words max-w-xs">
                                <?php echo htmlspecialchars($days_display); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                                <div class="flex gap-2 justify-end">
                                     <button onclick="showStudentDetailsModal(<?php echo $student['id']; ?>)" class="p-2 text-green-600 hover:bg-green-100 rounded-full" title="<?php echo __('enrollments_action_view'); ?>">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                    <a href="admin-dashboard.php?page=student-form&edit=<?php echo $student['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('enrollments_action_edit'); ?>">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>
                                     <a href="admin-dashboard.php?page=enrollments&action=delete&id=<?php echo $student['id']; ?>" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('enrollments_action_delete'); ?>" onclick="return confirm('<?php echo __('enrollments_delete_confirm'); ?>')">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="studentCardContainer" class="grid grid-cols-1 gap-4 md:hidden">
        <?php if (empty($students)): ?>
            <div id="noStudentsCard" class="text-center text-gray-500 py-4">
                <?php echo __('enrollments_table_empty'); ?>
            </div>
        <?php else: ?>
            <?php foreach ($students as $index => $student):
                // --- COPY LOGIKA DATA AGAR SAMA DENGAN TABEL ---
                $days_display = __('enrollments_modal_na');
                if (!empty($student['available_days'])) {
                    $days_array = explode(',', $student['available_days']);
                    $translated_days = [];
                    foreach ($days_array as $day) { $translated_days[] = __(trim($day)); }
                    $days_display = implode(', ', $translated_days);
                }
                $grade_key = isset($student["grade_level"]) ? $student["grade_level"] : null;
                $grade_display = (!empty($grade_key) && isset($grade_translations[$grade_key])) ? $grade_translations[$grade_key] : ($grade_key ?: __('enrollments_modal_na'));
                
                // Copy Detail Data Array
                $detailData = [
                    __('enrollments_table_name') => $student["name"],
                    __('users_table_username') => $student["username"], 
                    __('student_form_label_phone') => !empty($student["phone_number"]) ? $student["phone_number"] : __('enrollments_modal_na'),
                    __('student_form_label_address') => !empty($student["address"]) ? $student["address"] : __('enrollments_modal_na'),
                    __('student_form_label_birth_place') . ' & ' . __('student_form_label_birth_date') => (!empty($student['birth_place']) && !empty($student['birth_date'])) ? htmlspecialchars($student['birth_place']) . ', ' . date_format_intl($student['birth_date'], $currentLang) : __('enrollments_modal_na'),
                    __('enrollments_table_grade') => $grade_display,
                    __('student_form_label_school') => !empty($student["school"]) ? htmlspecialchars($student["school"]) : __('enrollments_modal_na'),
                    __('enrollments_table_subjects') => !empty($student["subjects"]) ? htmlspecialchars(str_replace(',', ', ', $student["subjects"])) : __('enrollments_modal_na'),
                    __('student_form_label_days') => htmlspecialchars($days_display),
                    __('student_form_label_times') => !empty($student["available_times"]) ? htmlspecialchars(str_replace(',', ', ', $student["available_times"])) : __('enrollments_modal_na'),
                    __('student_form_label_parent_name') => !empty($student["parent_name"]) ? htmlspecialchars($student["parent_name"]) : __('enrollments_modal_na'),
                    __('student_form_label_parent_phone') => !empty($student["parent_phone"]) ? htmlspecialchars($student["parent_phone"]) : __('enrollments_modal_na'),
                    __('approval_table_date') => !empty($student['created_at']) ? date_format_intl($student['created_at'], $currentLang) . ' ' . date('H:i', strtotime($student['created_at'])) : __('enrollments_modal_na') 
                ];
            ?>
            <div id="student-card-<?php echo $student['id']; ?>" 
                 data-details='<?php echo htmlspecialchars(json_encode($detailData), ENT_QUOTES, 'UTF-8'); ?>' 
                 class="student-card bg-white p-4 rounded-lg shadow ring-1 ring-gray-200/50">
                
                <div class="flex justify-between items-start">
                    <div class="flex-grow">
                        <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($student['name']); ?></p>
                        
                        <span class="inline-block bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-0.5 rounded mt-1 mb-2">
                            <?php echo $grade_display; ?>
                        </span>

                        <p class="text-sm text-gray-500 mt-1"><?php echo __('enrollments_table_subjects'); ?></p>
                        <p class="text-sm text-gray-700 break-words font-medium">
                            <?php echo !empty($student['subjects']) ? htmlspecialchars(str_replace(',', ', ', $student['subjects'])) : __('enrollments_modal_na'); ?>
                        </p>
                        
                        <p class="text-sm text-gray-500 mt-2"><?php echo __('student_form_label_days'); ?></p>
                        <p class="text-sm text-gray-700 break-words">
                           <?php echo htmlspecialchars($days_display); ?>
                        </p>
                    </div>
                    <div class="text-gray-500 text-sm font-mono ml-2">#<?php echo $index + 1; ?></div>
                </div>
                
                <div class="border-t my-4"></div>
                
                <div class="flex justify-end gap-2">
                     <button onclick="showStudentDetailsModal(<?php echo $student['id']; ?>)" class="flex-1 sm:flex-none justify-center p-2 text-green-600 bg-green-50 hover:bg-green-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('enrollments_action_view'); ?>">
                        <i data-lucide="eye" class="w-4 h-4"></i> <span><?php echo __('enrollments_action_view'); ?></span>
                    </button>
                    <a href="admin-dashboard.php?page=student-form&edit=<?php echo $student['id']; ?>" class="flex-1 sm:flex-none justify-center p-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('enrollments_action_edit'); ?>">
                        <i data-lucide="edit" class="w-4 h-4"></i> <span><?php echo __('enrollments_action_edit'); ?></span>
                    </a>
                    <a href="admin-dashboard.php?page=enrollments&action=delete&id=<?php echo $student['id']; ?>" class="flex-1 sm:flex-none justify-center p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('enrollments_action_delete'); ?>" onclick="return confirm('<?php echo __('enrollments_delete_confirm'); ?>')">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> <span><?php echo __('enrollments_action_delete'); ?></span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="student-detail-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg w-full max-w-lg transform transition-all">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800" id="modal-title"><?php echo __('enrollments_modal_title'); ?></h2>
            <button onclick="closeStudentDetailsModal()" class="text-gray-600 hover:text-gray-900">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="student-modal-content" class="text-gray-700 max-h-[70vh] overflow-y-auto pr-2">
            </div>
        <div class="mt-6 flex justify-end">
             <button onclick="closeStudentDetailsModal()" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded">
                <?php echo __('enrollments_modal_close'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    // Pastikan script ini tidak mendefinisikan ulang fungsi jika sudah ada
    if (typeof window.showStudentDetailsModal === 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('student-detail-modal');
            const modalContent = document.getElementById('student-modal-content');

            // Fungsi Tampilkan Modal (Bisa dipanggil dari Tabel atau Kartu)
            window.showStudentDetailsModal = function(studentId) {
                // Coba cari di baris tabel dulu, kalau tidak ada cari di kartu
                const row = document.getElementById(`student-row-${studentId}`) || document.getElementById(`student-card-${studentId}`);
                
                if (!row) {
                    console.error("Data container for student not found!");
                    return;
                }

                const details = JSON.parse(row.getAttribute('data-details'));

                let contentHtml = '<dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">';
                for (const [key, value] of Object.entries(details)) {
                    const displayValue = (value === null || value === '' || value === '<?php echo __('enrollments_modal_na'); ?>') ? `<span class="text-gray-400"><?php echo __('enrollments_modal_na'); ?></span>` : value;
                    contentHtml += `
                        <div class="border-b border-orange-100 py-2">
                            <dt class="font-semibold text-gray-600 text-sm">${key}</dt>
                            <dd class="text-gray-800 mt-1">${displayValue}</dd>
                        </div>
                    `;
                }
                contentHtml += '</dl>';

                modalContent.innerHTML = contentHtml;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Disable scroll

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            window.closeStudentDetailsModal = function() {
                modal.classList.add('hidden');
                modalContent.innerHTML = '';
                document.body.style.overflow = ''; // Enable scroll
            }

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeStudentDetailsModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeStudentDetailsModal();
                }
            });

            // --- LOGIKA PENCARIAN LIVE (TABEL & KARTU) ---
            const searchInput = document.getElementById('studentSearchInput');
            
            // Desktop Elements
            const tableBody = document.getElementById('studentTableBody');
            const tableRows = tableBody ? tableBody.getElementsByClassName('student-row') : [];
            const noTableRow = document.getElementById('noStudentsRow');
            
            // Mobile Elements
            const cardContainer = document.getElementById('studentCardContainer');
            const cards = cardContainer ? cardContainer.getElementsByClassName('student-card') : [];
            const noCard = document.getElementById('noStudentsCard');

            if(searchInput) {
                searchInput.addEventListener('keyup', () => {
                    const filter = searchInput.value.toLowerCase().trim();
                    let visibleCount = 0;

                    // 1. Filter Tabel Desktop
                    for (let row of tableRows) {
                        const rowText = row.textContent.toLowerCase();
                        if (rowText.includes(filter)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    }
                    if (noTableRow) noTableRow.style.display = (visibleCount === 0) ? '' : 'none';

                    // 2. Filter Kartu Mobile
                    visibleCount = 0; // Reset hitungan untuk mobile
                    for (let card of cards) {
                        const cardText = card.textContent.toLowerCase();
                        if (cardText.includes(filter)) {
                            card.style.display = '';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    }
                    if (noCard) noCard.style.display = (visibleCount === 0) ? '' : 'none';
                });
            }
        });
    }
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush();
?>