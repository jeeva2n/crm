<?php if (!empty($item['raw_materials'])): ?>
<div class="stage-container">
    <div class="stage-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="stage-icon">🔧</div>
            <span>Stage 2: Raw Materials Sourcing</span>
        </div>
        <div class="no-print">
            <span style="font-size: 0.875rem;">Materials: <?= count($item['raw_materials']) ?></span>
        </div>
    </div>
    <div class="stage-content">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Material Type</th>
                    <th>Grade</th>
                    <th>Dimensions</th>
                    <th>Vendor</th>
                    <th>Purchase Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item['raw_materials'] as $material): ?>
                    <tr>
                        <td><?= htmlspecialchars($material['type']) ?></td>
                        <td><?= htmlspecialchars($material['grade']) ?></td>
                        <td><?= htmlspecialchars($material['dimensions']) ?></td>
                        <td><?= htmlspecialchars($material['vendor']) ?></td>
                        <td><?= htmlspecialchars($material['purchase_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Material Summary -->
        <div class="process-box" style="margin-top: 20px;">
            <h4><i class="fas fa-chart-bar"></i> Material Summary</h4>
            <div class="process-grid">
                <div class="process-grid-item">
                    <div class="process-grid-label">Total Materials</div>
                    <div class="process-grid-value"><?= count($item['raw_materials']) ?></div>
                </div>
                <div class="process-grid-item">
                    <div class="process-grid-label">Unique Vendors</div>
                    <div class="process-grid-value">
                        <?= count(array_unique(array_column($item['raw_materials'], 'vendor'))) ?>
                    </div>
                </div>
                <div class="process-grid-item">
                    <div class="process-grid-label">Material Types</div>
                    <div class="process-grid-value">
                        <?= implode(', ', array_unique(array_column($item['raw_materials'], 'type'))) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>