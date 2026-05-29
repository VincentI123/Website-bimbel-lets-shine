<?php
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// PERBAIKAN: Menggunakan fungsi __() untuk label
$navItems = [
    'dashboard' => ['icon' => 'layout-dashboard', 'label' => __('sidebar_label_dashboard')],
    'schedule'  => ['icon' => 'calendar-days',    'label' => __('sidebar_label_schedule')],
    'exams'     => ['icon' => 'graduation-cap',   'label' => __('sidebar_label_my_exams')],
    'profile'   => ['icon' => 'user-circle',      'label' => __('sidebar_label_profile')],
];
?>
<div class="fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-sm border-t border-yellow-200 shadow-t-lg z-50">
    <div class="grid grid-cols-4">
        <?php foreach ($navItems as $pageId => $item): ?>
            <a href="student-dashboard.php?page=<?php echo $pageId; ?>" class="mobile-nav-item student <?php echo $currentPage === $pageId ? 'active' : ''; ?>">
                <i data-lucide="<?php echo $item['icon']; ?>" class="w-6 h-6 mb-1"></i>
                <span class="text-xs"><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>