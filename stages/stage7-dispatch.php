<?php if (!empty($item['packaging_lots'])): ?>
    <?php foreach ($item['packaging_lots'] as $lotIdx => $lot): ?>
        <?php if (($lot['dispatch_status'] ?? '') === 'Shipped'): ?>
        <div class="stage-container">
            <div class="stage-header" style="background: #10b981;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="stage-icon">🚚</div>
                    <span>Stage 7: Dispatch - Lot #<?= $lotIdx + 1 ?></span>
                </div>
                <div class="no-print">
                    <span class="status-badge status-shipped">Successfully Dispatched</span>
                </div>
            </div>
            <div class="stage-content">
                <div class="process-box dispatch-details">
                    <h4><i class="fas fa-check-circle" style="color: #10b981;"></i> Dispatch Completed</h4>
                    
                    <div class="process-grid">
                        <div class="process-grid-item">
                            <div class="process-grid-label">Dispatch Date</div>
                            <div class="process-grid-value"><?= htmlspecialchars($lot['dispatch_date']) ?></div>
                        </div>
                        <div class="process-grid-item">
                            <div class="process-grid-label">Transport Mode</div>
                            <div class="process-grid-value"><?= htmlspecialchars($lot['transport_mode']) ?></div>
                        </div>
                        <div class="process-grid-item">
                            <div class="process-grid-label">Tracking Number</div>
                            <div class="process-grid-value" style="font-weight: 600; color: var(--accent-color);">
                                <?= htmlspecialchars($lot['tracking_number']) ?>
                            </div>
                        </div>
                        <div class="process-grid-item">
                            <div class="process-grid-label">Dispatch Status</div>
                            <div class="process-grid-value">
                                <span class="status-badge status-shipped">
                                    <?= htmlspecialchars($lot['dispatch_status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($lot['dispatch_remarks'])): ?>
                        <div class="remarks-box">
                            <strong>Dispatch Remarks:</strong>
                            <p><?= nl2br(htmlspecialchars($lot['dispatch_remarks'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Client Notification -->
                    <div class="notification-info">
                        <h5><i class="fas fa-bell"></i> Client Notification</h5>
                        <div style="background: #d1fae5; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981;">
                            <p style="margin: 0; color: #065f46;">
                                <i class="fas fa-check-circle"></i> 
                                Client has been notified about the shipment with tracking details.
                            </p>
                        </div>
                    </div>

                    <!-- Delivery Timeline -->
                    <div class="timeline-section">
                        <h5><i class="fas fa-road"></i> Delivery Timeline</h5>
                        <div class="timeline">
                            <div class="timeline-item completed">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <strong>Order Created</strong>
                                    <span><?= htmlspecialchars($order['po_date']) ?></span>
                                </div>
                            </div>
                            <div class="timeline-item completed">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <strong>Production Completed</strong>
                                    <span>Before <?= htmlspecialchars($lot['packaging_date'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                            <div class="timeline-item completed">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <strong>Packaging Completed</strong>
                                    <span><?= htmlspecialchars($lot['packaging_date'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                            <div class="timeline-item completed">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <strong>Dispatched</strong>
                                    <span><?= htmlspecialchars($lot['dispatch_date']) ?></span>
                                </div>
                            </div>
                            <?php if (!empty($order['delivery_date'])): ?>
                                <div class="timeline-item <?= (strtotime($order['delivery_date']) > time()) ? 'pending' : 'completed' ?>">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <strong>Expected Delivery</strong>
                                        <span><?= htmlspecialchars($order['delivery_date']) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>