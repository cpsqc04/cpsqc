<?php
/** Shared legal + forgot-password modals and landing scripts for portal one-pagers. */
$forgotApiEndpoint = $forgotApiEndpoint ?? 'api/forgot-password.php';
$portalHomePath = $portalHomePath ?? 'login.php';
$autoOpenLogin = !empty($autoOpenLogin);
?>
    <div class="modal-overlay" id="legalModal" role="dialog" aria-modal="true" style="z-index:2200;">
        <div class="modal-panel wide">
            <div class="modal-header">
                <h2 id="legalTitle">Policy</h2>
                <button type="button" class="modal-close" onclick="closeLegalModal()" aria-label="Close">&times;</button>
            </div>
            <div class="legal-body" id="legalBody"></div>
        </div>
    </div>

    <div id="forgotPasswordModal" class="modal-overlay" style="z-index:2100;">
        <div class="modal-panel">
            <div class="modal-header">
                <h2 id="forgotPasswordTitle">Forgot Password</h2>
                <button type="button" class="modal-close" onclick="closeForgotPasswordModal()" aria-label="Close">&times;</button>
            </div>
            <div id="forgotPasswordStep1">
                <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.25rem;">Enter your email to receive an OTP code.</p>
                <form id="forgotPasswordForm1" onsubmit="requestOTP(event)">
                    <div class="field">
                        <label for="forgotEmail">Email *</label>
                        <input id="forgotEmail" name="email" type="email" placeholder="Enter your email" required>
                    </div>
                    <div id="forgotPasswordMessage" style="display:none;" class="alert"></div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="closeForgotPasswordModal()">Cancel</button>
                        <button type="submit" class="btn">Send OTP</button>
                    </div>
                </form>
            </div>
            <div id="forgotPasswordStep2" style="display:none;">
                <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.25rem;">Enter the 6-digit OTP code sent to your email address.</p>
                <form id="forgotPasswordForm2" onsubmit="verifyOTP(event)">
                    <div class="field">
                        <label for="forgotOTP">OTP Code *</label>
                        <input id="forgotOTP" name="otp" type="text" placeholder="Enter 6-digit OTP" maxlength="6" required style="text-align:center; letter-spacing:0.4rem; font-size:1.35rem;">
                    </div>
                    <div id="forgotPasswordMessage2" style="display:none;" class="alert"></div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="backToStep1()">Back</button>
                        <button type="submit" class="btn">Verify OTP</button>
                    </div>
                </form>
            </div>
            <div id="forgotPasswordStep3" style="display:none;">
                <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.25rem;">Enter your new password.</p>
                <form id="forgotPasswordForm3" onsubmit="resetPassword(event)">
                    <div class="field">
                        <label for="newPassword">New Password *</label>
                        <input id="newPassword" name="new_password" type="password" placeholder="Enter new password" required>
                    </div>
                    <div class="field">
                        <label for="confirmPassword">Confirm Password *</label>
                        <input id="confirmPassword" name="confirm_password" type="password" placeholder="Confirm new password" required>
                    </div>
                    <div id="forgotPasswordMessage3" style="display:none;" class="alert"></div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="backToStep2()">Back</button>
                        <button type="submit" class="btn">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal-overlay" style="z-index:3000;">
        <div class="modal-panel" style="text-align:center; background: linear-gradient(145deg, #10b981, #059669);">
            <div style="width:72px;height:72px;margin:0 auto 1rem;background:rgba(255,255,255,0.2);border-radius:50%;display:grid;place-items:center;">
                <i class="fas fa-check-circle" style="font-size:2.2rem;color:#fff;"></i>
            </div>
            <h2 style="margin:0 0 0.75rem;color:#fff;font-family:var(--font-display);">Success!</h2>
            <p id="successMessage" style="margin:0 0 1.5rem;color:rgba(255,255,255,0.95);"></p>
            <button class="btn btn-ghost" onclick="closeSuccessModal()" style="width:100%;">OK</button>
        </div>
    </div>

    <script>
        const FORGOT_API = <?php echo json_encode($forgotApiEndpoint); ?>;
        const PORTAL_HOME = <?php echo json_encode($portalHomePath); ?>;
        const autoOpenLogin = <?php echo $autoOpenLogin ? 'true' : 'false'; ?>;

        const legalContent = {
            privacy: {
                title: 'Privacy Policy',
                html: `
                    <p>AlerTara QC respects your privacy and handles account and operational data responsibly for community safety purposes in Barangay San Agustin, Novaliches, Quezon City.</p>
                    <h3>Information we process</h3>
                    <p>Account details (such as name, email, and role), authentication logs, and system activity needed to secure and operate the platform.</p>
                    <h3>How we use information</h3>
                    <p>To verify identity, send OTP and security notices, maintain service integrity, and support authorized public-safety operations in Barangay San Agustin.</p>
                    <h3>Sharing</h3>
                    <p>Data is shared only with authorized Barangay San Agustin partners and service providers under appropriate controls, or when required by law.</p>
                    <h3>Your choices</h3>
                    <p>Contact administrators to update account details or raise privacy concerns related to your access.</p>
                `
            },
            terms: {
                title: 'Terms of Service',
                html: `
                    <p>By accessing AlerTara QC, you agree to use the platform only for lawful community policing and surveillance operations in Barangay San Agustin, Novaliches, Quezon City.</p>
                    <h3>Acceptable use</h3>
                    <p>Users must protect credentials, follow role permissions, and avoid unauthorized disclosure of sensitive information.</p>
                    <h3>Accounts</h3>
                    <p>Accounts are for designated personnel. Repeated failed logins may trigger temporary lockouts for security.</p>
                    <h3>Availability</h3>
                    <p>We strive for continuous uptime, but maintenance or unforeseen issues may temporarily affect access.</p>
                    <h3>Changes</h3>
                    <p>These terms may be updated to reflect operational or legal requirements. Continued use means you accept the latest version.</p>
                `
            },
            cookies: {
                title: 'Cookie Policy',
                html: `
                    <p>AlerTara QC uses essential cookies and session storage to keep you signed in securely and remember critical login state.</p>
                    <h3>Essential cookies</h3>
                    <p>Required for authentication sessions, OTP verification flow, and basic security protections.</p>
                    <h3>Preferences</h3>
                    <p>We may store limited interface preferences to improve your experience on return visits.</p>
                    <h3>Control</h3>
                    <p>You can clear cookies in your browser, but doing so may sign you out and require login again.</p>
                `
            }
        };

        function openLoginModal() {
            const modal = document.getElementById('loginModal');
            if (!modal) return;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            const email = document.getElementById('email');
            if (email) setTimeout(() => email.focus(), 120);
        }
        function closeLoginModal() {
            const modal = document.getElementById('loginModal');
            if (!modal) return;
            modal.classList.remove('open');
            if (![...document.querySelectorAll('.modal-overlay.open')].length) {
                document.body.style.overflow = '';
            }
        }

        function openLegalModal(key, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const item = legalContent[key];
            if (!item) return;
            const modal = document.getElementById('legalModal');
            document.getElementById('legalTitle').textContent = item.title;
            document.getElementById('legalBody').innerHTML = item.html;
            modal.style.zIndex = '2200';
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeLegalModal() {
            document.getElementById('legalModal').classList.remove('open');
            if (![...document.querySelectorAll('.modal-overlay.open')].length) {
                document.body.style.overflow = '';
            }
        }

        function showSuccessModal(title, message, isError = false) {
            const modal = document.getElementById('successModal');
            const titleElement = modal.querySelector('h2');
            const messageElement = document.getElementById('successMessage');
            const iconElement = modal.querySelector('i');
            const modalContent = modal.querySelector('.modal-panel');
            titleElement.textContent = title;
            messageElement.textContent = message;
            if (isError) {
                modalContent.style.background = 'linear-gradient(145deg, #ef4444, #dc2626)';
                iconElement.className = 'fas fa-exclamation-circle';
            } else {
                modalContent.style.background = 'linear-gradient(145deg, #10b981, #059669)';
                iconElement.className = 'fas fa-check-circle';
            }
            modal.classList.add('open');
        }
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('open');
        }

        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('open');
            resetForgotPasswordModal();
            document.body.style.overflow = 'hidden';
        }
        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('open');
            resetForgotPasswordModal();
            if (![...document.querySelectorAll('.modal-overlay.open')].length) {
                document.body.style.overflow = '';
            }
        }
        function resetForgotPasswordModal() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordForm1').reset();
            document.getElementById('forgotPasswordForm2').reset();
            document.getElementById('forgotPasswordForm3').reset();
            ['forgotPasswordMessage','forgotPasswordMessage2','forgotPasswordMessage3'].forEach(id => {
                const el = document.getElementById(id);
                el.style.display = 'none';
                el.textContent = '';
            });
            document.getElementById('forgotPasswordTitle').textContent = 'Forgot Password';
        }
        function backToStep1() {
            document.getElementById('forgotPasswordStep1').style.display = 'block';
            document.getElementById('forgotPasswordStep2').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Forgot Password';
        }
        function backToStep2() {
            document.getElementById('forgotPasswordStep2').style.display = 'block';
            document.getElementById('forgotPasswordStep3').style.display = 'none';
            document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
        }
        function showForgotMessage(elementId, message, isSuccess) {
            const messageDiv = document.getElementById(elementId);
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
            messageDiv.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-error');
        }

        async function requestOTP(event) {
            event.preventDefault();
            const email = document.getElementById('forgotEmail').value.trim();
            if (!email) return showForgotMessage('forgotPasswordMessage', 'Please enter your email.', false);
            try {
                const response = await fetch(FORGOT_API + '?action=request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const result = await response.json();
                if (result.success) {
                    showForgotMessage('forgotPasswordMessage', result.message, true);
                    setTimeout(() => {
                        document.getElementById('forgotPasswordStep1').style.display = 'none';
                        document.getElementById('forgotPasswordStep2').style.display = 'block';
                        document.getElementById('forgotPasswordTitle').textContent = 'Verify OTP';
                        document.getElementById('forgotOTP').focus();
                    }, 900);
                } else {
                    showForgotMessage('forgotPasswordMessage', result.message, false);
                }
            } catch (err) {
                showForgotMessage('forgotPasswordMessage', 'An error occurred. Please try again.', false);
            }
        }

        async function verifyOTP(event) {
            event.preventDefault();
            const otp = document.getElementById('forgotOTP').value.trim();
            if (!otp || otp.length !== 6) return showForgotMessage('forgotPasswordMessage2', 'Please enter a valid 6-digit OTP code.', false);
            try {
                const response = await fetch(FORGOT_API + '?action=verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('forgotPasswordStep2').style.display = 'none';
                    document.getElementById('forgotPasswordStep3').style.display = 'block';
                    document.getElementById('forgotPasswordTitle').textContent = 'Reset Password';
                    document.getElementById('newPassword').focus();
                } else {
                    showForgotMessage('forgotPasswordMessage2', result.message, false);
                }
            } catch (err) {
                showForgotMessage('forgotPasswordMessage2', 'An error occurred. Please try again.', false);
            }
        }

        async function resetPassword(event) {
            event.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (!newPassword || !confirmPassword) return showForgotMessage('forgotPasswordMessage3', 'Please fill in all fields.', false);
            if (newPassword !== confirmPassword) return showForgotMessage('forgotPasswordMessage3', 'Passwords do not match.', false);
            if (newPassword.length < 6) return showForgotMessage('forgotPasswordMessage3', 'Password must be at least 6 characters long.', false);
            try {
                const response = await fetch(FORGOT_API + '?action=reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ new_password: newPassword, confirm_password: confirmPassword })
                });
                const result = await response.json();
                if (result.success) {
                    closeForgotPasswordModal();
                    showSuccessModal('Password Reset Successful!', result.message, false);
                    setTimeout(() => { window.location.href = PORTAL_HOME; }, 2000);
                } else {
                    showForgotMessage('forgotPasswordMessage3', result.message, false);
                }
            } catch (err) {
                showForgotMessage('forgotPasswordMessage3', 'An error occurred. Please try again.', false);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const nav = document.getElementById('siteNav');
            const bar = document.getElementById('progressBar');
            const onScroll = () => {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                const pct = max > 0 ? (window.scrollY / max) * 100 : 0;
                if (bar) bar.style.width = pct + '%';
                if (nav) nav.classList.toggle('scrolled', window.scrollY > 24);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
            document.querySelectorAll('.reveal').forEach((el) => io.observe(el));

            const sections = [...document.querySelectorAll('section[id], #mission, #vision, #values')];
            const links = [...document.querySelectorAll('#navLinks a')];
            if (sections.length && links.length) {
                const spy = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        const id = entry.target.id;
                        links.forEach((a) => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
                    });
                }, { threshold: 0.35 });
                sections.forEach((s) => spy.observe(s));
            }

            const toggle = document.getElementById('navToggle');
            const navLinks = document.getElementById('navLinks');
            if (toggle && navLinks) {
                toggle.addEventListener('click', () => navLinks.classList.toggle('open'));
                navLinks.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => navLinks.classList.remove('open')));
            }

            const otpInput = document.getElementById('forgotOTP');
            if (otpInput) otpInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
            });
            const loginOtpInput = document.getElementById('login_otp');
            if (loginOtpInput) {
                loginOtpInput.addEventListener('input', (e) => {
                    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
                });
            }

            if (autoOpenLogin) openLoginModal();
            if (window.location.search.includes('reset=1')) {
                openForgotPasswordModal();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        window.addEventListener('click', (event) => {
            if (event.target === document.getElementById('loginModal')) closeLoginModal();
            if (event.target === document.getElementById('legalModal')) closeLegalModal();
            if (event.target === document.getElementById('forgotPasswordModal')) closeForgotPasswordModal();
            if (event.target === document.getElementById('successModal')) closeSuccessModal();
            if (event.target === document.getElementById('registerModal') && typeof closeRegisterModal === 'function') {
                closeRegisterModal();
            }
            if (event.target === document.getElementById('registrationSuccessModal') && typeof closeRegistrationSuccessModal === 'function') {
                closeRegistrationSuccessModal();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLoginModal();
                closeLegalModal();
                closeForgotPasswordModal();
                closeSuccessModal();
                if (typeof closeRegisterModal === 'function') closeRegisterModal();
                if (typeof closeRegistrationSuccessModal === 'function') closeRegistrationSuccessModal();
            }
        });
    </script>
</body>
</html>
