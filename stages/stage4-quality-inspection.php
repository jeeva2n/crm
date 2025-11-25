<?php if (!empty($item['inspection_data']) && is_array($item['inspection_data'])): ?>
<div class="stage-container">
    <div class="stage-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="stage-icon">🔍</div>
            <span>Stage 4: Quality Inspection</span>
        </div>
        <div class="no-print">
            <?php
            $latestInspection = end($item['inspection_data']);
            $latestStatus = $latestInspection['status'] ?? 'Pending';
            ?>
            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $latestStatus)) ?>">
                <?= htmlspecialchars($latestStatus) ?>
            </span>
        </div>
    </div>
    <div class="stage-content">
        <?php foreach (array_reverse($item['inspection_data']) as $inspectionIndex => $inspection): ?>
            <div class="process-box inspection-entry 
                <?= $inspection['status'] === 'QC Passed' ? 'inspection-passed' : 
                   ($inspection['status'] === 'Rework Required' ? 'inspection-failed' : 'inspection-pending') ?>">
                <h4>
                    <i class="fas fa-clipboard-check"></i>
                    Inspection #<?= count($item['inspection_data']) - $inspectionIndex ?>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $inspection['status'])) ?>">
                        <?= htmlspecialchars($inspection['status']) ?>
                    </span>
                </h4>
                
                <div class="process-grid">
                    <div class="process-grid-item">
                        <div class="process-grid-label">Inspection Type</div>
                        <div class="process-grid-value"><?= htmlspecialchars($inspection['type']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Technician</div>
                        <div class="process-grid-value"><?= htmlspecialchars($inspection['technician_name']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Inspection Date</div>
                        <div class="process-grid-value"><?= htmlspecialchars($inspection['inspection_date']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Inspection ID</div>
                        <div class="process-grid-value"><?= htmlspecialchars($inspection['inspection_id'] ?? 'N/A') ?></div>
                    </div>
                </div>

                <?php if (!empty($inspection['remarks'])): ?>
                    <div class="remarks-box">
                        <strong>QC Remarks:</strong>
                        <p><?= nl2br(htmlspecialchars($inspection['remarks'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($inspection['documents'])): ?>
                    <div class="documents-section">
                        <h5><i class="fas fa-file-medical-alt"></i> QC Reports & Documents</h5>
                        <ul class="document-list">
                            <?php foreach ($inspection['documents'] as $docIndex => $doc): ?>
                                <li class="document-item">
                                    <div class="document-info">
                                        <div class="document-icon" style="background: var(--success-color);">
                                            <i class="fas fa-file-medical-alt"></i>
                                        </div>
                                        <a href="uploads/inspection_reports/<?= htmlspecialchars($doc) ?>"
                                           class="document-name" target="_blank">
                                            <?= htmlspecialchars($inspection['original_filenames'][$docIndex] ?? $doc) ?>
                                        </a>
                                    </div>
                                    <div class="document-actions no-print">
                                        <a href="uploads/inspection_reports/<?= htmlspecialchars($doc) ?>"
                                           download="QC_Report_<?= $inspectionIndex + 1 ?>_<?= htmlspecialchars($doc) ?>"
                                           class="btn-icon tooltip">
                                            <i class="fas fa-download"></i>
                                            <span class="tooltiptext">Download QC Report</span>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <!-- Inspection Summary -->
        <div class="stats-box">
            <h4><i class="fas fa-chart-line"></i> Inspection Summary</h4>
            <div class="process-grid">
                <?php
                $inspectionStats = [
                    'Total' => count($item['inspection_data']),
                    'Passed' => count(array_filter($item['inspection_data'], function($i) { 
                        return $i['status'] === 'QC Passed'; 
                    })),
                    'Rework' => count(array_filter($item['inspection_data'], function($i) { 
                        return $i['status'] === 'Rework Required'; 
                    })),
                    'Minor Issues' => count(array_filter($item['inspection_data'], function($i) { 
                        return $i['status'] === 'Minor Issues'; 
                    }))
                ];
                ?>
                <?php foreach ($inspectionStats as $label => $count): ?>
                    <div class="process-grid-item">
                        <div class="process-grid-label"><?= $label ?></div>
                        <div class="process-grid-value"><?= $count ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>