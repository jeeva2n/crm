            </main>
        </div><!-- End .main-content-wrapper -->
        
        <!-- Toast Notification -->
        <div id="toast-notification" class="toast-notification" style="display: none;">
            <div class="toast-content">
                <i class="fas fa-bell"></i>
                <span class="toast-message">Operation completed successfully</span>
            </div>
        </div>

        <footer class="site-footer">
            <div class="footer-content">
                <div class="footer-left">
                    <p>&copy; <?= date('Y') ?> Alphasonix Dakstools CRM. All rights reserved.</p>
                </div>
                <div class="footer-right">
                    <div class="footer-links">
                        <a href="privacy.php">Privacy Policy</a>
                        <a href="terms.php">Terms of Service</a>
                        <a href="support.php">Support</a>
                    </div>
                </div>
            </div>
        </footer>
    </div><!-- End #app-container -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast-notification');
            if (toast) {
                // Show toast if there's a message
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('message')) {
                    const message = urlParams.get('message');
                    const type = urlParams.get('type') || 'success';
                    
                    toast.style.display = 'block';
                    toast.style.background = type === 'error' ? '#ef4444' : '#10b981';
                    toast.querySelector('.toast-message').textContent = message;
                    
                    setTimeout(() => {
                        toast.classList.add('show');
                    }, 100);
                    
                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => {
                            toast.style.display = 'none';
                        }, 300);
                    }, 3000);
                }
            }
        });
    </script>
</body>
</html>