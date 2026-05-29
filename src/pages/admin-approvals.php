<?php
// --- AKTIFKAN ERROR REPORTING & OUTPUT BUFFERING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); 

// --- "OTAK" BAHASA (VERSI UNIVERSAL) ---

// 1. Selalu mulai session
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

    ob_end_clean(); 
    header("Location: " . $redirectUrl);
    exit;
}

// 4. Tentukan bahasa yang akan digunakan
$currentLang = isset($_SESSION['lang']) ? $_SESSION['lang'] : $defaultLang;

// 5. Mengatur "Locale" PHP
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
if (!function_exists('__')) { 
    function __($key) {
        global $lang;
        if (is_array($lang) && isset($lang[$key])) {
            return $lang[$key];
        } else {
            return $key;
        }
    }
}

// 8. Fungsi Helper Format Tanggal Manual (Agar Mandarin Aman & Detail Bisa Dibuka)
if (!function_exists('date_format_intl')) {
    function date_format_intl($date_string, $lang) {
        if (empty($date_string)) return '-';
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

// --- Handle Approve/Deny Actions ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_to_act = $_GET['id'];
    $action = $_GET['action'];

    try {
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE users SET status = 'approved', role = 'student' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id_to_act]);
            $_SESSION['flash_message'] = __('approval_status_approved_msg');
        } elseif ($action === 'deny') {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id_to_act]);
            $_SESSION['flash_message'] = __('approval_status_denied_msg');
        }
        
        ob_end_clean();
        header("Location: admin-dashboard.php?page=approvals");
        exit;

    } catch (PDOException $e) {
        $_SESSION['flash_error'] = __('approval_db_error') . " " . $e->getMessage();
        ob_end_clean();
        header("Location: admin-dashboard.php?page=approvals");
        exit;
    }
}

try {
    // Fetch all users with 'pending' status
    $stmt = $db->prepare("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
    $stmt->execute();
    $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='bg-red-100 text-red-700 p-4'>". __('approval_db_error') . " " . $e->getMessage() . "</div>";
    $pending_users = []; 
}


// --- TAMPILKAN PESAN FLASH ---
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

// Array untuk menerjemahkan Grade Level
$grade_translations = [
    'Pre-Nursery' => __('student_form_grade_prenursery'),
    'Kindergarten' => __('student_form_grade_kindergarten'),
    'Elementary' => __('student_form_grade_elementary'),
    'Junior High' => __('student_form_grade_juniorhigh'),
    'Senior High' => __('student_form_grade_seniorhigh')
];
// Array untuk kunci modal
$modal_keys = [
    'name' => __('student_form_label_name'),
    'username' => __('users_table_username'),
    'phone_number' => __('student_form_label_phone'),
    'address' => __('student_form_label_address'),
    'birth_place' => __('student_form_label_birth_place'),
    'birth_date' => __('student_form_label_birth_date'),
    'grade_level' => __('enrollments_table_grade'),
    'school' => __('student_form_label_school'),
    'subjects' => __('enrollments_table_subjects'),
    'parent_name' => __('student_form_label_parent_name'),
    'parent_phone' => __('student_form_label_parent_phone'),
    'created_at' => __('approval_table_date')
];

?>

<?php
$url_parts = parse_url($_SERVER['REQUEST_URI']);
$path = $url_parts['path'];
$query_params = array();
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}

// Link Bahasa
$queryParams_id = $query_params; $queryParams_id['lang'] = 'id'; $url_id = $path . '?' . http_build_query($queryParams_id);
$queryParams_en = $query_params; $queryParams_en['lang'] = 'en'; $url_en = $path . '?' . http_build_query($queryParams_en);
$queryParams_cn = $query_params; $queryParams_cn['lang'] = 'cn'; $url_cn = $path . '?' . http_build_query($queryParams_cn);
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
    <h1 class="text-3xl font-bold text-gray-800"><?php echo __('approval_page_title'); ?></h1>

    <div class="bg-white p-4 sm:p-8 rounded-2xl shadow-lg shadow-orange-900/5">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('approval_section_title'); ?></h2>

        <?php if ($flash_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg">
                <p><?php echo $flash_message; ?></p>
            </div>
        <?php endif; ?>
         <?php if ($flash_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg">
                <p><?php echo $flash_error; ?></p>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <?php if (empty($pending_users)): ?>
                <div class="text-center py-12">
                    <i data-lucide="user-check-2" class="mx-auto h-12 w-12 text-gray-400"></i>
                    <h3 class="mt-2 text-lg font-medium text-gray-900"><?php echo __('approval_empty_title'); ?></h3>
                    <p class="mt-1 text-sm text-gray-500"><?php echo __('approval_empty_message'); ?></p>
                </div>
            <?php else: ?>
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('enrollments_table_no'); ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('approval_table_name'); ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('approval_table_username'); ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('approval_table_date'); ?></th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo __('approval_table_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $rowNumber = 1;
                            foreach ($pending_users as $p_user): 
                                 // --- Menyiapkan data untuk Modal ---
                                $detailData = [];
                                $na = __('approval_na');

                                // Kumpulkan data, ubah kunci ke dalam bahasa aktif
                                foreach ($modal_keys as $db_key => $lang_key) {
                                    $value = isset($p_user[$db_key]) ? $p_user[$db_key] : '';
                                    $displayValue = $value;

                                    // PERBAIKAN: Gunakan date_format_intl alih-alih strftime
                                    if ($db_key === 'birth_date' || $db_key === 'created_at') {
                                        $formattedDate = date_format_intl($value, $currentLang);
                                        if ($db_key === 'created_at' && !empty($value)) {
                                            // Tambahkan jam untuk created_at
                                            $formattedDate .= ' ' . date('H:i', strtotime($value));
                                        }
                                        $displayValue = !empty($value) ? $formattedDate : $na;
                                    } elseif ($db_key === 'grade_level') {
                                        // Terjemahkan grade level
                                        $displayValue = isset($grade_translations[$value]) ? $grade_translations[$value] : ($value ?: $na);
                                    } elseif ($db_key === 'subjects' || $db_key === 'available_days' || $db_key === 'available_times') {
                                        $displayValue = !empty($value) ? htmlspecialchars(str_replace(',', ', ', $value)) : $na;
                                    } elseif (empty($value) || $value === null) {
                                         $displayValue = $na;
                                    }

                                    // Handle Birth Place & Date Combined
                                    if ($db_key === 'birth_place') {
                                        $birth_date_value = isset($p_user['birth_date']) ? $p_user['birth_date'] : '';
                                        if (!empty($value) || !empty($birth_date_value)) {
                                             $detailData[__('student_form_label_birth_place') . ' & ' . __('student_form_label_birth_date')] = htmlspecialchars($value) . ', ' . date_format_intl($birth_date_value, $currentLang);
                                        } else {
                                             $detailData[__('student_form_label_birth_place') . ' & ' . __('student_form_label_birth_date')] = $na;
                                        }
                                        continue; 

                                    } elseif ($db_key === 'birth_date') {
                                        continue; 
                                    }

                                    $detailData[__($lang_key)] = $displayValue;
                                }
                            ?>
                                <tr id="student-row-<?php echo $p_user['id']; ?>"
                                    data-details='<?php echo htmlspecialchars(json_encode($detailData), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $rowNumber++; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($p_user['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($p_user['username']); ?></td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date_format_intl($p_user['created_at'], $currentLang) . ' ' . date('H:i', strtotime($p_user['created_at'])); ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="#" onclick="showStudentDetailsModal(<?php echo $p_user['id']; ?>); return false;" class="text-blue-600 hover:text-blue-900 font-semibold mr-8"><?php echo __('approval_action_detail'); ?></a>
                                        <a href="admin-dashboard.php?page=approvals&action=approve&id=<?php echo $p_user['id']; ?>" class="text-green-600 hover:text-green-900 font-semibold mr-2"><?php echo __('approval_action_approve'); ?></a>
                                        <a href="admin-dashboard.php?page=approvals&action=deny&id=<?php echo $p_user['id']; ?>" onclick="return confirm('<?php echo __('approval_confirm_deny'); ?>');" class="text-red-600 hover:text-red-900 font-semibold"><?php echo __('approval_action_deny'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="md:hidden space-y-4">
                    <?php 
                    $rowNumber = 1;
                    foreach ($pending_users as $p_user): 
                        // (Logika data detail sama seperti di atas)
                        $detailData = [];
                        $na = __('approval_na');
                        foreach ($modal_keys as $db_key => $lang_key) {
                            $value = isset($p_user[$db_key]) ? $p_user[$db_key] : '';
                            $displayValue = $value;
                             
                            // PERBAIKAN MOBILE
                            if ($db_key === 'birth_date' || $db_key === 'created_at') {
                                $formattedDate = date_format_intl($value, $currentLang);
                                if ($db_key === 'created_at' && !empty($value)) {
                                    $formattedDate .= ' ' . date('H:i', strtotime($value));
                                }
                                $displayValue = !empty($value) ? $formattedDate : $na;
                            } elseif ($db_key === 'grade_level') {
                                $displayValue = isset($grade_translations[$value]) ? $grade_translations[$value] : ($value ?: $na);
                            } elseif ($db_key === 'subjects' || $db_key === 'available_days' || $db_key === 'available_times') { 
                                $displayValue = !empty($value) ? htmlspecialchars(str_replace(',', ', ', $value)) : $na;
                            } elseif (empty($value) || $value === null) {
                                    $displayValue = $na;
                            }
                            if ($db_key === 'birth_place') {
                                $birth_date_value = isset($p_user['birth_date']) ? $p_user['birth_date'] : '';
                                if (!empty($value) || !empty($birth_date_value)) {
                                        $detailData[__('student_form_label_birth_place') . ' & ' . __('student_form_label_birth_date')] = htmlspecialchars($value) . ', ' . date_format_intl($birth_date_value, $currentLang);
                                } else {
                                        $detailData[__('student_form_label_birth_place') . ' & ' . __('student_form_label_birth_date')] = $na;
                                }
                                continue; 
                            } elseif ($db_key === 'birth_date') {
                                continue; 
                            }
                            $detailData[__($lang_key)] = $displayValue;
                        }
                    ?>
                    <div id="student-card-<?php echo $p_user['id']; ?>" data-details='<?php echo htmlspecialchars(json_encode($detailData), ENT_QUOTES, 'UTF-8'); ?>' class="border rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4">
                            <div class="flex items-start gap-4">
                                <p class="text-gray-500"><?php echo $rowNumber++; ?></p>
                                <div>
                                    <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($p_user['name']); ?></p>
                                    <p class="text-sm text-gray-600">@<?php echo htmlspecialchars($p_user['username']); ?></p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2"><i data-lucide="calendar" class="inline-block w-3 h-3 mr-1"></i>
                                <?php echo date_format_intl($p_user['created_at'], $currentLang) . ' ' . date('H:i', strtotime($p_user['created_at'])); ?>
                            </p>
                        </div>
                        <div class="grid grid-cols-3 border-t border-gray-100 bg-gray-50">
                                <a href="#" onclick="showStudentDetailsModal(<?php echo $p_user['id']; ?>, true); return false;" class="flex items-center justify-center gap-2 p-3 text-sm font-semibold text-blue-600 hover:bg-blue-100 transition-colors">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                                <span><?php echo __('approval_action_detail'); ?></span>
                            </a>
                            <a href="admin-dashboard.php?page=approvals&action=approve&id=<?php echo $p_user['id']; ?>" class="flex items-center justify-center gap-2 p-3 text-sm font-semibold text-green-600 hover:bg-green-100 transition-colors border-l border-r border-gray-100">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <span><?php echo __('approval_action_approve'); ?></span>
                            </a>
                            <a href="admin-dashboard.php?page=approvals&action=deny&id=<?php echo $p_user['id']; ?>" onclick="return confirm('<?php echo __('approval_confirm_deny'); ?>');" class="flex items-center justify-center gap-2 p-3 text-sm font-semibold text-red-600 hover:bg-red-100 transition-colors">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                    <span><?php echo __('approval_action_deny'); ?></span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="student-detail-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg w-11/12 max-w-lg transform transition-all">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800" id="modal-title"><?php echo __('approval_modal_title'); ?></h2>
            <button onclick="closeStudentDetailsModal()" class="text-gray-600 hover:text-gray-900">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="student-modal-content" class="text-gray-700 max-h-[70vh] overflow-y-auto pr-2">
            </div>
        <div class="mt-6 flex justify-end">
             <button onclick="closeStudentDetailsModal()" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg">
                <?php echo __('approval_modal_close'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    if (typeof window.showStudentDetailsModal === 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('student-detail-modal');
            const modalContent = document.getElementById('student-modal-content');

            window.showStudentDetailsModal = function(studentId, isMobile = false) {
                const dataSourceElem = isMobile ? document.getElementById(`student-card-${studentId}`) : document.getElementById(`student-row-${studentId}`);
                if (!dataSourceElem) return;

                const details = JSON.parse(dataSourceElem.getAttribute('data-details'));

                let contentHtml = '<dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">';
                for (const [key, value] of Object.entries(details)) {
                    const naText = '<?php echo __('approval_na'); ?>';
                    const displayValue = (value === null || value === '' || value === naText) ? `<span class="text-gray-400">${naText}</span>` : value;
                    contentHtml += `
                        <div class="border-b border-orange-100 py-2">
                            <dt class="font-semibold text-gray-600 text-sm">${key}</dt>
                            <dd class="text-gray-800">${displayValue}</dd>
                        </div>
                    `;
                }
                contentHtml += '</dl>';

                modalContent.innerHTML = contentHtml;
                modal.classList.remove('hidden');

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            window.closeStudentDetailsModal = function() {
                modal.classList.add('hidden');
                modalContent.innerHTML = '';
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
        });
    }

     if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

<?php
ob_end_flush();
?>