<?php
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
// Pastikan label menggunakan fungsi __()
$navItems = [
    'dashboard' => ['icon' => 'layout-dashboard', 'label' => __('sidebar_label_dashboard')],
    'schedule'  => ['icon' => 'calendar-days',    'label' => __('sidebar_label_schedule')],
    'exams'     => ['icon' => 'graduation-cap',   'label' => __('sidebar_label_my_exams')],
    'profile'   => ['icon' => 'user-circle',      'label' => __('sidebar_label_profile')],
];
?>
<aside class="h-full bg-white flex flex-col shadow-lg shadow-yellow-900/5">
    <div class="p-4 border-b border-gray-100">
        <a href="student-dashboard.php" class="flex items-center gap-3">
            <img src="assets/img/logo.png" alt="Logo" class="h-10 w-10">
            <div>
                <h1 class="text-sm font-bold text-gray-800">Bimbel Let's Shine</h1>
                <p class="text-xs text-orange-500 font-semibold">⭐ Shine Like Star</p>
            </div>
        </a>
        <div class="mt-4 p-3 bg-yellow-50 rounded-xl">
            <p class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($user['name']); ?></p>
            <p class="text-xs text-yellow-600"><?php echo __('profile_role_student'); ?></p>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-2">
        <?php foreach ($navItems as $pageId => $item): ?>
            <a href="student-dashboard.php?page=<?php echo $pageId; ?>"
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
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="p-4 border-t border-gray-100">
        <a href="logout.php" class="flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-red-100 hover:text-red-600 transition-all duration-300">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span><?php echo __('sidebar_label_sign_out'); ?></span>
        </a>
    </div>
</aside>