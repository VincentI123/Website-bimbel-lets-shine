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
$db = Database::getInstance()->getConnection();

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    try {
        $delete_stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        $delete_stmt->execute([$id_to_delete]);
        $_SESSION['flash_message'] = __('teachers_status_deleted');
        
        ob_end_clean(); 
        header("Location: admin-dashboard.php?page=teachers");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error deleting record: " . $e->getMessage();
        ob_end_clean(); 
        header("Location: admin-dashboard.php?page=teachers");
        exit;
    }
}

// --- PERUBAHAN: Logika pencarian PHP dihapus karena menggunakan JS ---
$search_query = "";

// Query SQL
$sql = "SELECT * FROM users WHERE role = 'teacher' ORDER BY name ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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


<h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo __('teachers_page_title'); ?></h1>
<p class="text-gray-600 mb-6"><?php echo __('teachers_page_subtitle'); ?></p>

<div class="bg-white p-4 md:p-8 rounded-2xl shadow-lg shadow-orange-900/5">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <div class="relative w-full md:w-auto">
            <input type="text" id="teacherSearchInput" placeholder="<?php echo __('teachers_search_placeholder'); ?>" class="px-4 py-2 pl-10 border rounded-lg w-full focus:ring-orange-500 focus:border-orange-500" value="">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
        </div>
        <a href="admin-dashboard.php?page=teacher-form" class="w-full md:w-auto text-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center gap-2">
            <i data-lucide="plus-circle" class="w-5 h-5"></i>
            <span><?php echo __('teachers_add_new'); ?></span>
        </a>
    </div>

    <div class="overflow-x-auto hidden md:block">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('teachers_table_no'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('teachers_table_name'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('teachers_table_subjects'); ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('teachers_table_days'); ?></th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('teachers_table_actions'); ?></th>
                </tr>
            </thead>
            <tbody id="teacherTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($teachers)): ?>
                    <tr id="noTeachersRow">
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            <?php echo __('teachers_table_empty'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $index => $teacher):
                        // --- 1. LOGIKA TRANSLATE HARI & STATUS (BARU) ---
                        $days_display_str = __('teachers_modal_na'); // Default "-"
                        $days_display_html = __('teachers_modal_na'); // Untuk tampilan tabel

                        if ($teacher['employment_status'] === 'full-time') {
                            // Jika Full-time
                            $days_display_str = __('teacher_form_status_full_time');
                            $days_display_html = '<span class="font-medium text-green-600">' . __('teacher_form_status_full_time') . '</span>';
                        } elseif (!empty($teacher['available_days'])) {
                            // Jika Part-time & ada hari
                            $days_arr = explode(',', $teacher['available_days']);
                            $days_trans = [];
                            foreach ($days_arr as $d) {
                                $days_trans[] = __(trim($d)); // Translate (Monday -> Senin)
                            }
                            $days_display_str = implode(', ', $days_trans);
                            $days_display_html = htmlspecialchars($days_display_str);
                        }

                        // --- 2. MENYIAPKAN DATA MODAL ---
                        $teacher_name_display = ucwords(strtolower(htmlspecialchars($teacher['name'])));
                        
                        $detailData = [
                            __('teachers_table_name') => $teacher_name_display,
                            __('users_table_username') => $teacher["username"],
                            __('teacher_form_label_phone') => !empty($teacher["phone_number"]) ? $teacher["phone_number"] : __('teachers_modal_na'),
                            __('teacher_form_label_address') => !empty($teacher["address"]) ? $teacher["address"] : __('teachers_modal_na'),
                            
                            // Fix Tanggal Lahir (Pakai fungsi manual date_format_intl agar Mandarin aman)
                            __('teacher_form_label_birth_place') . ' & ' . __('teacher_form_label_birth_date') => (!empty($teacher['birth_place']) && !empty($teacher['birth_date'])) ? ucwords(strtolower(htmlspecialchars($teacher['birth_place']))) . ', ' . date_format_intl($teacher['birth_date'], $currentLang) : __('teachers_modal_na'),
                            
                            __('teachers_table_subjects') => !empty($teacher["subjects"]) ? htmlspecialchars(str_replace(',', ', ', $teacher["subjects"])) : __('teachers_modal_na'),
                            __('teacher_form_label_employment_status') => ($teacher["employment_status"] === 'full-time') ? __('teacher_form_status_full_time') : __('teacher_form_status_part_time'),
                            
                            // PERBAIKAN: Gunakan variabel hari yang sudah ditranslate
                            __('teacher_form_label_days') => $days_display_str,
                            
                            __('teacher_form_label_times') => !empty($teacher["available_times"]) ? htmlspecialchars(str_replace(',', ', ', $teacher["available_times"])) : __('teachers_modal_na'), 
                            
                            // Fix Tanggal Buat
                            __('approval_table_date') => !empty($teacher['created_at']) ? date_format_intl($teacher['created_at'], $currentLang) . ' ' . date('H:i', strtotime($teacher['created_at'])) : __('teachers_modal_na')
                        ];
                    ?>
                        <tr id="teacher-row-<?php echo $teacher['id']; ?>" class="teacher-row"
                            data-details='<?php echo htmlspecialchars(json_encode($detailData), ENT_QUOTES, 'UTF-8'); ?>'>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $index + 1; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo $teacher_name_display; ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-normal break-words max-w-xs">
                                <?php 
                                echo !empty($teacher['subjects']) ? htmlspecialchars(str_replace(',', ', ', $teacher['subjects'])) : __('teachers_modal_na'); 
                                ?>
                            </td>
                            
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-normal break-words max-w-xs">
                                <?php echo $days_display_html; ?>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                                <div class="flex gap-2 justify-end">
                                     <button onclick="showDetailsModal(<?php echo $teacher['id']; ?>)" class="p-2 text-green-600 hover:bg-green-100 rounded-full" title="<?php echo __('teachers_action_view'); ?>">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                    <a href="admin-dashboard.php?page=teacher-form&edit=<?php echo $teacher['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('teachers_action_edit'); ?>">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>
                                    <a href="admin-dashboard.php?page=teachers&action=delete&id=<?php echo $teacher['id']; ?>" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('teachers_action_delete'); ?>" onclick="return confirm('<?php echo __('teachers_delete_confirm'); ?>')">
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

    <div id="teacherCardContainer" class="grid grid-cols-1 gap-4 md:hidden">
        <?php if (empty($teachers)): ?>
            <div id="noTeachersCard" class="text-center text-gray-500 py-4">
                <?php echo __('teachers_table_empty'); ?>
            </div>
        <?php else: ?>
            <?php foreach ($teachers as $index => $teacher): 
                $teacher_name_display = ucwords(strtolower(htmlspecialchars($teacher['name'])));
                $detailData = [
                    __('teachers_table_name') => $teacher_name_display,
                            __('users_table_username') => $teacher["username"],
                            __('teacher_form_label_phone') => !empty($teacher["phone_number"]) ? $teacher["phone_number"] : __('teachers_modal_na'),
                            __('teacher_form_label_address') => !empty($teacher["address"]) ? $teacher["address"] : __('teachers_modal_na'),
                            
                            // Fix Tanggal Lahir (Pakai fungsi manual date_format_intl agar Mandarin aman)
                            __('teacher_form_label_birth_place') . ' & ' . __('teacher_form_label_birth_date') => (!empty($teacher['birth_place']) && !empty($teacher['birth_date'])) ? ucwords(strtolower(htmlspecialchars($teacher['birth_place']))) . ', ' . date_format_intl($teacher['birth_date'], $currentLang) : __('teachers_modal_na'),
                            
                            __('teachers_table_subjects') => !empty($teacher["subjects"]) ? htmlspecialchars(str_replace(',', ', ', $teacher["subjects"])) : __('teachers_modal_na'),
                            __('teacher_form_label_employment_status') => ($teacher["employment_status"] === 'full-time') ? __('teacher_form_status_full_time') : __('teacher_form_status_part_time'),
                            
                            // PERBAIKAN: Gunakan variabel hari yang sudah ditranslate
                            __('teacher_form_label_days') => $days_display_str,
                            
                            __('teacher_form_label_times') => !empty($teacher["available_times"]) ? htmlspecialchars(str_replace(',', ', ', $teacher["available_times"])) : __('teachers_modal_na'), 
                            
                            // Fix Tanggal Buat
                            __('approval_table_date') => !empty($teacher['created_at']) ? date_format_intl($teacher['created_at'], $currentLang) . ' ' . date('H:i', strtotime($teacher['created_at'])) : __('teachers_modal_na')
                        ];
                
            ?>
            <div id="teacher-card-<?php echo $teacher['id']; ?>" data-details='<?php echo htmlspecialchars(json_encode($detailData), ENT_QUOTES, 'UTF-8'); ?>' class="teacher-card bg-white p-4 rounded-lg shadow ring-1 ring-gray-200/50">
                <div class="flex justify-between items-start">
                    <div class="flex-grow">
                        <p class="text-lg font-bold text-gray-800"><?php echo $teacher_name_display; ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo __('teachers_table_subjects'); ?></p>
                        <p class="text-sm text-gray-700 break-words">
                            <?php 
                            echo !empty($teacher['subjects']) ? htmlspecialchars(str_replace(',', ', ', $teacher['subjects'])) : __('teachers_modal_na'); 
                            ?>
                        </p>
                        <p class="text-sm text-gray-500 mt-2"><?php echo __('teacher_form_label_days'); ?></p>
                        <p class="text-sm text-gray-700 break-words">
                           <?php 
                                if ($teacher['employment_status'] === 'full-time') {
                                    echo '<span class="font-medium">' . __('teacher_form_status_full_time') . '</span>';
                                } else {
                                    echo !empty($teacher['available_days']) ? htmlspecialchars(str_replace(',', ', ', $teacher['available_days'])) : __('teachers_modal_na');
                                }
                            ?>
                        </p>
                    </div>
                    <div class="text-gray-500 text-sm font-mono ml-2">#<?php echo $index + 1; ?></div>
                </div>
                <div class="border-t my-4"></div>
                <div class="flex justify-end gap-2">
                     <button onclick="showDetailsModal(<?php echo $teacher['id']; ?>)" class="flex-1 sm:flex-none justify-center p-2 text-green-600 bg-green-50 hover:bg-green-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('teachers_action_view'); ?>">
                        <i data-lucide="eye" class="w-4 h-4"></i> <span><?php echo __('teachers_action_view'); ?></span>
                    </button>
                    <a href="admin-dashboard.php?page=teacher-form&edit=<?php echo $teacher['id']; ?>" class="flex-1 sm:flex-none justify-center p-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('teachers_action_edit'); ?>">
                        <i data-lucide="edit" class="w-4 h-4"></i> <span><?php echo __('teachers_action_edit'); ?></span>
                    </a>
                    <a href="admin-dashboard.php?page=teachers&action=delete&id=<?php echo $teacher['id']; ?>" class="flex-1 sm:flex-none justify-center p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg inline-flex items-center gap-2 text-sm" title="<?php echo __('teachers_action_delete'); ?>" onclick="return confirm('<?php echo __('teachers_delete_confirm'); ?>')">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> <span><?php echo __('teachers_action_delete'); ?></span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="detail-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg w-full max-w-lg transform transition-all m-4">
        <div class="flex justify-between items-center pb-3 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800" id="modal-title"><?php echo __('teachers_modal_title'); ?></h2>
            <button onclick="closeDetailsModal()" class="text-gray-500 hover:text-gray-800 p-1 rounded-full hover:bg-gray-100">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="modal-content" class="text-gray-700 py-4 max-h-[60vh] overflow-y-auto">
            </div>
        <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end">
             <button onclick="closeDetailsModal()" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg">
                <?php echo __('teachers_modal_close'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    if (typeof window.showDetailsModal === 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('detail-modal');
            const modalContent = document.getElementById('modal-content');

            window.showDetailsModal = function(teacherId) {
                const row = document.getElementById(`teacher-row-${teacherId}`) || document.getElementById(`teacher-card-${teacherId}`);
                if (!row) {
                    console.error("Data container for teacher not found!");
                    return;
                }

                const details = JSON.parse(row.getAttribute('data-details'));

                let contentHtml = '<dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">';
                for (const [key, value] of Object.entries(details)) {
                    const displayValue = (value === null || value === '' || value === '<?php echo __('teachers_modal_na'); ?>') ? `<span class="text-gray-400"><?php echo __('teachers_modal_na'); ?></span>` : value;
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
                document.body.style.overflow = 'hidden'; 

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            window.closeDetailsModal = function() {
                modal.classList.add('hidden');
                modalContent.innerHTML = '';
                document.body.style.overflow = ''; 
            }

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeDetailsModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeDetailsModal();
                }
            });
            
            const searchInput = document.getElementById('teacherSearchInput');
            const tableBody = document.getElementById('teacherTableBody');
            const tableRows = tableBody ? tableBody.getElementsByClassName('teacher-row') : [];
            const noTableRow = document.getElementById('noTeachersRow');
            
            const cardContainer = document.getElementById('teacherCardContainer');
            const cards = cardContainer ? cardContainer.getElementsByClassName('teacher-card') : [];
            const noCard = document.getElementById('noTeachersCard');

            if(searchInput) {
                searchInput.addEventListener('keyup', () => {
                    const filter = searchInput.value.toLowerCase().trim();
                    let visibleCount = 0;

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

                    visibleCount = 0; 
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