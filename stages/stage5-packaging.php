<?php if (!empty($item['packaging_lots'])): ?>
<div class="stage-container">
    <div class="stage-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="stage-icon">📦</div>
            <span>Stage 5: Packaging</span>
        </div>
        <div class="no-print">
            <span style="font-size: 0.875rem;">Lots: <?= count($item['packaging_lots']) ?></span>
        </div>
    </div>
    <div class="stage-content">
        <?php foreach ($item['packaging_lots'] as $lotIdx => $lot): ?>
            <div class="process-box packaging-lot">
                <h4>
                    <i class="fas fa-pallet"></i> Packaging Lot #<?= $lotIdx + 1 ?>
                    <?php if (($lot['dispatch_status'] ?? '') === 'Shipped'): ?>
                        <span class="status-badge status-shipped">Shipped</span>
                    <?php endif; ?>
                </h4>

                <div class="process-grid">
                    <div class="process-grid-item">
                        <div class="process-grid-label">Products in Lot</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['products_in_lot']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Packaging Type</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['packaging_type']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Number of Packages</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['num_packages']) ?> pcs</div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Packaging Date</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['packaging_date']) ?></div>
                    </div>
                </div>

                <div class="process-grid">
                    <div class="process-grid-item">
                        <div class="process-grid-label">Weight per Package</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['weight_per_package']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Package Dimensions</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['dimensions_per_package']) ?></div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Net Weight</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['net_weight']) ?> kg</div>
                    </div>
                    <div class="process-grid-item">
                        <div class="process-grid-label">Gross Weight</div>
                        <div class="process-grid-value"><?= htmlspecialchars($lot['gross_weight']) ?> kg</div>
                    </div>
                </div>

                <!-- Fumigation Information -->
                <?php if (!empty($lot['fumigation_completed']) && $lot['fumigation_completed'] === 'Yes'): ?>
                    <div class="fumigation-info">
                        <h5><i class="fas fa-spray-can"></i> Fumigation Details</h5>
                        <div class="process-grid">
                            <div class="process-grid-item">
                                <div class="process-grid-label">Certificate Number</div>
                                <div class="process-grid-value"><?= htmlspecialchars($lot['fumigation_certificate_number'] ?? 'N/A') ?></div>
                            </div>
                            <div class="process-grid-item">
                                <div class="process-grid-label">Fumigation Date</div>
                                <div class="process-grid-value"><?= htmlspecialchars($lot['fumigation_date'] ?? 'N/A') ?></div>
                            </div>
                            <div class="process-grid-item">
                                <div class="process-grid-label">Agency</div>
                                <div class="process-grid-value"><?= htmlspecialchars($lot['fumigation_agency'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Packaging Photos -->
                <?php if (!empty($lot['photos'])): ?>
                    <div class="photos-section">
                        <h5><i class="fas fa-camera"></i> Packaging Photos</h5>
                        <div class="image-gallery">
                            <?php foreach ($lot['photos'] as $photoIdx => $photo): ?>
                                <div class="image-item">
                                    <div class="image-wrapper">
                                        <img src="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                            alt="Packaging Photo <?= $photoIdx + 1 ?>"
                                            onclick="openImageModal(this.src)">
                                    </div>
                                    <div class="image-overlay">
                                        <div class="image-actions">
                                            <a href="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                                download="Packaging_Lot<?= $lotIdx + 1 ?>_Photo<?= $photoIdx + 1 ?>_<?= htmlspecialchars($photo) ?>"
                                                class="btn-icon tooltip">
                                                <i class="fas fa-download"></i>
                                                <span class="tooltiptext">Download Photo</span>
                                            </a>
                                            <a href="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                                target="_blank" class="btn-icon tooltip">
                                                <i class="fas fa-external-link-alt"></i>
                                                <span class="tooltiptext">Open in New Tab</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>