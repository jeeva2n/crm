<?php if (!empty($item['packaging_lots'])): ?>
    <?php foreach ($item['packaging_lots'] as $lotIdx => $lot): ?>
        <?php if (!empty($lot['shipping_documents'])): ?>
            <div class="stage-container">
                <div class="stage-header" style="background: #f59e0b;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="stage-icon">📋</div>
                        <span>Stage 6: Shipping Documentation - Lot #<?= $lotIdx + 1 ?></span>
                    </div>
                    <div class="no-print">
                        <span style="font-size: 0.875rem;">
                            Documents: <?= count($lot['shipping_documents']) ?>
                        </span>
                    </div>
                </div>
                <div class="stage-content">
                    <div class="process-box">
                        <h4><i class="fas fa-shipping-fast"></i> Shipping Documents</h4>
                        <p style="color: var(--gray-600); margin-bottom: 20px;">
                            All required shipping documents for logistics and customs clearance
                        </p>

                        <ul class="document-list">
                            <?php foreach ($lot['shipping_documents'] as $docIdx => $doc): ?>
                                <li class="document-item">
                                    <div class="document-info">
                                        <div class="document-icon" style="background: #647effff;">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div>
                                            <a href="uploads/shipping_docs/<?= htmlspecialchars($doc) ?>" class="document-name"
                                                target="_blank">
                                                <?= htmlspecialchars($lot['shipping_original_filenames'][$docIdx] ?? $doc) ?>
                                            </a>
                                            <div style="font-size: 0.8rem; color: var(--gray-500); margin-top: 2px;">
                                                Uploaded for Lot #<?= $lotIdx + 1 ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="document-actions no-print">
                                        <a href="uploads/shipping_docs/<?= htmlspecialchars($doc) ?>"
                                            download="Shipping_Lot<?= $lotIdx + 1 ?>_Doc<?= $docIdx + 1 ?>_<?= htmlspecialchars($doc) ?>"
                                            class="btn-icon tooltip">
                                            <i class="fas fa-download"></i>
                                            <span class="tooltiptext">Download Shipping Document</span>
                                        </a>
                                        <a href="uploads/shipping_docs/<?= htmlspecialchars($doc) ?>" target="_blank"
                                            class="btn-icon tooltip">
                                            <i class="fas fa-external-link-alt"></i>
                                            <span class="tooltiptext">Open in New Tab</span>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Document Types Summary -->
                        <div class="documents-summary">
                            <h5><i class="fas fa-tasks"></i> Document Types</h5>
                            <div class="process-grid">
                                <?php
                                $docTypes = [
                                    'Commercial Invoice' => 0,
                                    'Packing List' => 0,
                                    'Bill of Lading' => 0,
                                    'Certificate of Origin' => 0,
                                    'Other Documents' => 0
                                ];

                                foreach ($lot['shipping_original_filenames'] ?? [] as $filename) {
                                    $lowerName = strtolower($filename);
                                    if (strpos($lowerName, 'invoice') !== false)
                                        $docTypes['Commercial Invoice']++;
                                    elseif (strpos($lowerName, 'packing') !== false)
                                        $docTypes['Packing List']++;
                                    elseif (strpos($lowerName, 'lading') !== false || strpos($lowerName, 'bl') !== false)
                                        $docTypes['Bill of Lading']++;
                                    elseif (strpos($lowerName, 'certificate') !== false || strpos($lowerName, 'origin') !== false)
                                        $docTypes['Certificate of Origin']++;
                                    else
                                        $docTypes['Other Documents']++;
                                }
                                ?>
                                <?php foreach ($docTypes as $type => $count): ?>
                                    <?php if ($count > 0): ?>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label"><?= $type ?></div>
                                            <div class="process-grid-value"><?= $count ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>