<?php
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Tetap menggunakan fungsi translate agar bahasa berfungsi
$navItems = [
    'dashboard' => ['icon' => 'layout-dashboard', 'label' => __('sidebar_label_dashboard')],
    'schedule'  => ['icon' => 'calendar-days',    'label' => __('sidebar_label_schedule')],
    'exams'     => ['icon' => 'file-text',        'label' => __('sidebar_label_upcoming_exams')],
    'profile'   => ['icon' => 'user-circle',      'label' => __('sidebar_label_profile')],
];
?>

<div class="fixed bottom-0 left-0 right-0 bg-white shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-50 border-t border-gray-200">
    <div class="grid grid-cols-4 h-20"> 
        <?php foreach ($navItems as $pageId => $item): ?>
            <a href="teacher-dashboard.php?page=<?php echo $pageId; ?>"
               class="flex flex-col items-center justify-center w-full h-full transition-colors duration-200 p-1
                      <?php echo $currentPage === $pageId ? 'text-orange-600' : 'text-gray-500 hover:text-orange-600'; ?>">
                
                <i data-lucide="<?php echo $item['icon']; ?>" class="w-7 h-7 mb-1"></i>
                
                <span class="text-xs font-medium text-center leading-tight px-1 w-full h-9 flex items-center justify-center overflow-hidden">
                    <?php echo $item['label']; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>