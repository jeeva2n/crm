<?php
/**
 * Item Pipeline Kanban View - Main Wrapper
 * This file creates the Kanban board and includes the individual stage files.
 */
?>

<div class="kanban-pipeline-container">
    <div class="kanban-pipeline">

        <!-- Stage 1, 2, 3... Columns -->
        <?php
        $stages = [
            ['title' => 'Order Details', 'icon' => '📝', 'file' => 'stage1-order-details.php', 'check' => true],
            ['title' => 'Raw Materials', 'icon' => '🔧', 'file' => 'stage2-raw-materials.php', 'check' => !empty($item['raw_materials'])],
            ['title' => 'Machining', 'icon' => '⚙️', 'file' => 'stage3-machining-processes.php', 'check' => !empty($item['machining_processes'])],
            ['title' => 'Inspection', 'icon' => '🔍', 'file' => 'stage4-quality-inspection.php', 'check' => !empty($item['inspection_data'])],
            ['title' => 'Packaging', 'icon' => '📦', 'file' => 'stage5-packaging.php', 'check' => !empty($item['packaging_lots'])],
            ['title' => 'Shipping Docs', 'icon' => '📋', 'file' => 'stage6-shipping-docs.php', 'check' => !empty($item['packaging_lots']) && count(array_filter($item['packaging_lots'], fn($lot) => !empty($lot['shipping_documents']))) > 0],
            ['title' => 'Dispatch', 'icon' => '🚚', 'file' => 'stage7-dispatch.php', 'check' => !empty($item['packaging_lots']) && count(array_filter($item['packaging_lots'], fn($lot) => ($lot['dispatch_status'] ?? '') === 'Shipped')) > 0],
        ];
        
        foreach ($stages as $index => $stage):
        ?>
        <div class="kanban-stage-column">
            <div class="kanban-stage-header">
                <div class="stage-icon"><?= $stage['icon'] ?></div>
                <span>Stage <?= $index + 1 ?>: <?= $stage['title'] ?></span>
            </div>
            <div class="kanban-stage-content">
                <?php if ($stage['check']): ?>
                    <?php include __DIR__ . '/stages/' . $stage['file']; ?>
                <?php else: ?>
                    <div class="empty-stage-placeholder">
                        <i class="fas fa-hourglass-start"></i>
                        <p>Stage not started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
