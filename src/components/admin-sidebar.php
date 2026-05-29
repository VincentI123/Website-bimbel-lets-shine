<?php
// --- PENGAMAN FUNGSI TRANSLATE ---
if (!function_exists('__')) {
    function __($key) {
        global $lang;
        return isset($lang[$key]) ? $lang[$key] : $key;
    }
}

$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- DEFINISI MENU ---
$navGroups = [
    // Grup 1: Halaman Utama
    [
        'title' => '', 
        'items' => [
            'dashboard' => ['icon' => 'layout-dashboard', 'label' => __('sidebar_menu_dashboard')],
        ]
    ],
    
    // Grup 2: Manajemen Pengguna
    [
        'title' => __('sidebar_header_users'), 
        'items' => [
            'approvals' => ['icon' => 'user-check', 'label' => __('sidebar_menu_approvals')],
            'enrollments' => ['icon' => 'user-plus', 'label' => __('sidebar_menu_enrollments')],
            'teachers' => ['icon' => 'award', 'label' => __('sidebar_menu_teachers')],
            'users' => ['icon' => 'users', 'label' => __('sidebar_menu_users')],
        ]
    ],
    
    // Grup 3: Manajemen Kelas
    [
        'title' => __('sidebar_header_class'), 
        'items' => [
            'schedules' => ['icon' => 'calendar-plus', 'label' => __('sidebar_menu_schedules')],
            'exams' => ['icon' => 'file-text', 'label' => __('sidebar_menu_exams')],
        ]
    ],
    
    // Grup 4: Pengaturan Akademik
    [
        'title' => __('sidebar_header_academic'), 
        'items' => [
            'subjects' => ['icon' => 'book-text', 'label' => __('sidebar_menu_subjects')],
            'times' => ['icon' => 'clock', 'label' => __('sidebar_menu_times')],
        ]
    ],

    // Grup 5: Pengaturan Situs
    [
        'title' => __('sidebar_header_site'), 
        'items' => [
            'banners' => ['icon' => 'image', 'label' => __('sidebar_menu_banners')],
            'landing-settings' => ['icon' => 'layout-template', 'label' => __('sidebar_menu_landing')]
        ]
    ]
];
?>

<aside class="w-64 bg-white flex-col shadow-lg shadow-orange-900/5 hidden lg:flex h-full">
    <div class="p-4 border-b border-gray-100">
        <a href="admin-dashboard.php" class="flex items-center gap-3">
            <img src="assets/img/logo.png" alt="Logo" class="h-10 w-10">
            <div>
                <h1 class="text-sm font-bold text-gray-800">Bimbel Let's Shine</h1>
                <p class="text-xs text-orange-500 font-semibold">⭐ Shine Like Star</p>
            </div>
        </a>
        <div class="mt-4 p-3 bg-orange-50 rounded-xl">
            <p class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($user['name']); ?></p>
            <p class="text-xs text-orange-600"><?php echo __('menu_role_admin'); ?></p>
        </div>
    </div>

    <nav id="sidebar-nav" class="flex-1 p-4 space-y-4 overflow-y-auto">
        
        <?php foreach ($navGroups as $group): ?>
        <div class="space-y-1">
            <?php if ($group['title']): ?>
                <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider"><?php echo $group['title']; ?></h3>
            <?php endif; ?>

            <?php foreach ($group['items'] as $pageId => $item): ?>
                <a href="admin-dashboard.php?page=<?php echo $pageId; ?>" 
                   class="flex items-center gap-3 px-4 py-2 rounded-lg transition-all duration-300
                          <?php 
                            if ($currentPage === $pageId) {
                                echo 'bg-orange-500 text-white shadow-md hover:bg-orange-600';
                            } else {
                                echo 'text-gray-600 hover:bg-orange-100 hover:text-orange-600';
                            }
                          ?>">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
                    <span><?php echo $item['label']; ?></span>

                    <?php if ($pageId === 'approvals' && isset($pending_count) && $pending_count > 0): ?>
                        <span class="ml-auto flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-sm">
                                <?php echo $pending_count; ?>
                            </span>
                        </span>
                    <?php endif; ?>
                    </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

    </nav>
    
    <div class="p-4 border-t border-gray-100">
        <a href="logout.php" class="flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-red-100 hover:text-red-600 transition-all duration-300">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span><?php echo __('sidebar_menu_logout'); ?></span>
        </a>
    </div>
</aside>