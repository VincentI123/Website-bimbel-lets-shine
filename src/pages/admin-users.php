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

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    try {
        // Prevent deleting self
        $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        if ($id_to_delete == $current_user) {
             // Optional: Set flash message for error
        } else {
            $stmt_delete = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt_delete->execute([$id_to_delete]);
            // Optional: Set flash message
        }
    } catch (PDOException $e) {
        // Handle error
    }
    
    // Redirect bersih
    ob_end_clean();
    header("Location: admin-dashboard.php?page=users");
    exit;
}

// Fetch all users
// --- PERUBAHAN: Logika pencarian PHP dihapus karena menggunakan JS ---
$sql = "SELECT id, name, username, role FROM users WHERE status = 'approved' ORDER BY name";$stmt = $db->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Hapus agar tidak muncul terus
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

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('users_page_title'); ?></h1>
        <a href="admin-dashboard.php?page=user-form" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-lg flex items-center justify-center gap-2 transition-colors w-full sm:w-auto">
            <i data-lucide="plus" class="w-5 h-5"></i> 
            <span><?php echo __('users_add_new'); ?></span>
        </a>
    </div>

    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-lg shadow-orange-900/5">
        
        <div class="mb-4 flex flex-col md:flex-row gap-4">
            <div class="relative w-full md:w-1/3">
                <input type="text" id="userSearchInput" placeholder="<?php echo __('users_search_placeholder'); ?>" class="w-full px-4 py-2 pl-10 border rounded-lg focus:ring-orange-500 focus:border-orange-500" value="">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
            </div>
            <div class="relative w-full md:w-auto">
                <select id="roleFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-orange-500 focus:border-orange-500 bg-white">
                    <option value="all"><?php echo __('users_role_all'); ?></option>
                    
                    <option value="admin"><?php echo __('users_role_admin'); ?></option>
                    <option value="teacher"><?php echo __('users_role_teacher'); ?></option>
                    <option value="student"><?php echo __('users_role_student'); ?></option>
                </select>
            </div>
        </div>
        
        <div id="userCardContainer" class="sm:hidden space-y-4">
            <?php if (empty($users)): ?>
                <div id="noUsersCard" class="text-center text-gray-500 py-4">
                    <?php echo __('users_table_empty'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <?php
                        $display_name = htmlspecialchars(ucwords(strtolower($user['name'])));
                        
                        // --- LOGIKA CEK USER ---
                        $is_self = ($user['id'] == $_SESSION['user_id']);
                        $is_super_admin = ($user['id'] == 1);
                    ?>
                    <div class="border rounded-lg p-4 bg-gray-50 user-card" data-role="<?php echo $user['role']; ?>">
                        <div class="flex items-center gap-4 mb-3">
                            <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center font-bold text-orange-600 text-xl">
                                <?php echo htmlspecialchars(strtoupper(substr($display_name, 0, 1))); ?>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">
                                    <?php echo $display_name; ?>
                                    <?php if ($is_self): ?>
                                        <span class="text-xs text-green-600 font-bold ml-1"><?php echo __('users_label_you'); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 text-xs font-medium rounded-full 
                                <?php 
                                    switch ($user['role']) {
                                        case 'admin': echo 'bg-red-100 text-red-700'; break;
                                        case 'teacher': echo 'bg-blue-100 text-blue-700'; break;
                                        default: echo 'bg-green-100 text-green-700'; break;
                                    }
                                ?>">
                                <?php 
                                    switch ($user['role']) {
                                        case 'admin': echo __('users_role_admin'); break;
                                        case 'teacher': echo __('users_role_teacher'); break;
                                        default: echo __('users_role_student'); break;
                                    }
                                ?>
                            </span>
                            <div class="flex gap-2">
                                <a href="admin-dashboard.php?page=user-form&id=<?php echo $user['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('users_action_edit'); ?>">
                                    <i data-lucide="edit" class="w-5 h-5"></i>
                                </a>
                                
                                <?php 
                                // --- TOMBOL HAPUS (MOBILE) HANYA MUNCUL JIKA BUKAN DIRI SENDIRI & BUKAN SUPER ADMIN ---
                                if (!$is_self && !$is_super_admin): 
                                ?>
                                    <a href="admin-dashboard.php?page=users&action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('<?php echo __('users_delete_confirm'); ?>');" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('users_action_delete'); ?>">
                                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div id="noUsersCardSearch" class="text-center text-gray-500 py-4" style="display: none;">
                    <?php echo __('users_table_empty_search'); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left table-auto">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="p-4 font-semibold text-gray-600"><?php echo __('users_table_name'); ?></th>
                        <th class="p-4 font-semibold text-gray-600"><?php echo __('users_table_username'); ?></th>
                        <th class="p-4 font-semibold text-gray-600"><?php echo __('users_table_role'); ?></th>
                        <th class="p-4 font-semibold text-gray-600"><?php echo __('users_table_actions'); ?></th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php if (empty($users)): ?>
                         <tr id="noUsersRow">
                            <td colspan="4" class="p-4 text-center text-gray-500"><?php echo __('users_table_empty'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                                $display_name = htmlspecialchars(ucwords(strtolower($user['name'])));
                                
                                // --- LOGIKA CEK USER ---
                                $is_self = ($user['id'] == $_SESSION['user_id']);
                                $is_super_admin = ($user['id'] == 1);
                            ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50 user-row" data-role="<?php echo $user['role']; ?>">
                                <td class="p-4"> 
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center font-bold text-orange-600">
                                            <?php echo htmlspecialchars(strtoupper(substr($display_name, 0, 1))); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo $display_name; ?></p>
                                            <?php if ($is_self): ?>
                                                <span class="text-xs text-green-600 font-bold"><?php echo __('users_label_you'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-gray-600"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="p-4 text-gray-600">
                                    <span class="px-3 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                            switch ($user['role']) {
                                                case 'admin': echo 'bg-red-100 text-red-700'; break;
                                                case 'teacher': echo 'bg-blue-100 text-blue-700'; break;
                                                default: echo 'bg-green-100 text-green-700'; break;
                                            }
                                        ?>">
                                        <?php 
                                            switch ($user['role']) {
                                                case 'admin': echo __('users_role_admin'); break;
                                                case 'teacher': echo __('users_role_teacher'); break;
                                                default: echo __('users_role_student'); break;
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <a href="admin-dashboard.php?page=user-form&id=<?php echo $user['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-100 rounded-full" title="<?php echo __('users_action_edit'); ?>">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </a>
                                        
                                        <?php 
                                        // --- TOMBOL HAPUS (DESKTOP) HANYA MUNCUL JIKA BUKAN DIRI SENDIRI & BUKAN SUPER ADMIN ---
                                        if (!$is_self && !$is_super_admin): 
                                        ?>
                                            <a href="admin-dashboard.php?page=users&action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('<?php echo __('users_delete_confirm'); ?>');" class="p-2 text-red-600 hover:bg-red-100 rounded-full" title="<?php echo __('users_action_delete'); ?>">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="noUsersRowSearch" style="display: none;">
                            <td colspan="4" class="p-4 text-center text-gray-500"><?php echo __('users_table_empty_search'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('roleFilter'); 
    
    // Target untuk Desktop
    const tableBody = document.getElementById('userTableBody');
    const tableRows = tableBody ? tableBody.getElementsByClassName('user-row') : [];
    const noTableRow = document.getElementById('noUsersRow');
    const noTableRowSearch = document.getElementById('noUsersRowSearch');
    
    // Target untuk Mobile
    const cardContainer = document.getElementById('userCardContainer');
    const cards = cardContainer ? cardContainer.getElementsByClassName('user-card') : [];
    const noCard = document.getElementById('noUsersCard');
    const noCardSearch = document.getElementById('noUsersCardSearch');

    // [BARU] Fungsi filter gabungan
    function filterUsers() {
        const filterText = searchInput.value.toLowerCase().trim();
        const filterRole = roleFilter.value; // "all", "admin", "teacher", "student"
        
        let visibleCountTable = 0;
        let visibleCountCard = 0;

        // Filter Tabel Desktop
        if (tableRows.length > 0) {
            for (let row of tableRows) {
                const rowText = row.textContent.toLowerCase();
                const rowRole = row.getAttribute('data-role');
                
                const textMatch = rowText.includes(filterText);
                const roleMatch = (filterRole === 'all' || rowRole === filterRole);

                if (textMatch && roleMatch) {
                    row.style.display = '';
                    visibleCountTable++;
                } else {
                    row.style.display = 'none';
                }
            }
        }
        
        if (noTableRowSearch) {
            noTableRowSearch.style.display = (visibleCountTable === 0 && noTableRow == null) ? '' : 'none';
        }

        // Filter Kartu Mobile
        if (cards.length > 0) {
            for (let card of cards) {
                const cardText = card.textContent.toLowerCase();
                const cardRole = card.getAttribute('data-role');
                
                const textMatch = cardText.includes(filterText);
                const roleMatch = (filterRole === 'all' || cardRole === filterRole);

                if (textMatch && roleMatch) {
                    card.style.display = '';
                    visibleCountCard++;
                } else {
                    card.style.display = 'none';
                }
            }
        }
        
        if (noCardSearch) {
            noCardSearch.style.display = (visibleCountCard === 0 && noCard == null) ? '' : 'none';
        }
    }

    // [BARU] Terapkan fungsi filter ke kedua elemen
    if (searchInput) {
        searchInput.addEventListener('keyup', filterUsers);
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', filterUsers);
    }
});
</script>

<?php
// --- DIUBAH: Akhiri dan kirim output buffer ---
ob_end_flush(); 
?>