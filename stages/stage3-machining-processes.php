<?php if (!empty($item['machining_processes'])): ?>
<div class="stage-container">
    <div class="stage-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="stage-icon">⚙️</div>
            <span>Stage 3: Machining/Processing</span>
        </div>
        <div class="no-print">
            <?php
            $completed = array_filter($item['machining_processes'], function($p) {
                return ($p['status'] ?? '') === 'Completed';
            });
            ?>
            <span style="font-size: 0.875rem;">
                Progress: <?= count($completed) ?>/<?= count($item['machining_processes']) ?> Completed
            </span>
        </div>
    </div>
    <div class="stage-content">
        <?php foreach ($item['machining_processes'] as $processIdx => $process): ?>
            <div class="process-box">
                <h4>
                    <span class="process-sequence">
                        <?= htmlspecialchars($process['sequence']) ?>
                    </span>
                    <?= htmlspecialchars($process['name']) ?>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $process['status'])) ?>">
                        <?= htmlspecialchars($process['status']) ?>
                    </span>
                </h4>
                
                <div class="process-grid">
                    <div class="process-grid-item">
                        <div class="process-grid-label">Vendor/Department</div>
                        <div class="process-grid-value"><?= htmlspecialchars($process['vendor']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Start Date</div>
                        <div class="process-grid-value"><?= htmlspecialchars($process['start_date']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Expected Completion</div>
                        <div class="process-grid-value"><?= htmlspecialchars($process['expected_completion']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Actual Completion</div>
                        <div class="process-grid-value">
                            <?= htmlspecialchars($process['actual_completion'] ?: 'Pending') ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($process['remarks'])): ?>
                    <div class="remarks-box">
                        <strong>Process Remarks:</strong>
                        <p><?= nl2br(htmlspecialchars($process['remarks'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($process['documents'])): ?>
                    <div class="documents-section">
                        <h5><i class="fas fa-paperclip"></i> Process Documents</h5>
                        <ul class="document-list">
                            <?php foreach ($process['documents'] as $docIndex => $doc): ?>
                                <li class="document-item">
                                    <div class="document-info">
                                        <div class="document-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <a href="uploads/machining_docs/<?= htmlspecialchars($doc) ?>"
                                           class="document-name" target="_blank">
                                            <?= htmlspecialchars($process['original_filenames'][$docIndex] ?? $doc) ?>
                                        </a>
                                    </div>
                                    <div class="document-actions no-print">
                                        <a href="uploads/machining_docs/<?= htmlspecialchars($doc) ?>"
                                           download="Process_<?= $processIdx + 1 ?>_<?= htmlspecialchars($doc) ?>"
                                           class="btn-icon tooltip">
                                            <i class="fas fa-download"></i>
                                            <span class="tooltiptext">Download Document</span>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <!-- Process Statistics -->
        <div class="stats-box">
            <h4><i class="fas fa-chart-pie"></i> Process Statistics</h4>
            <div class="process-grid">
                <?php
                $statusCounts = array_count_values(array_column($item['machining_processes'], 'status'));
                ?>
                <div class="process-grid-item">
                    <div class="process-grid-label">Completed</div>
                    <div class="process-grid-value"><?= $statusCounts['Completed'] ?? 0 ?></div>
                </div>
                <div class="process-grid-item">
                    <div class="process-grid-label">In Progress</div>
                    <div class="process-grid-value"><?= $statusCounts['In Progress'] ?? 0 ?></div>
                </div>
                <div class="process-grid-item">
                    <div class="process-grid-label">Not Started</div>
                    <div class="process-grid-value"><?= $statusCounts['Not Started'] ?? 0 ?></div>
                </div>
                <div class="process-grid-item">
                    <div class="process-grid-label">On Hold</div>
                    <div class="process-grid-value"><?= $statusCounts['On Hold'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>