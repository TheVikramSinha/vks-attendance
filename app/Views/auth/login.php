<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#121212">
    <title>Login - VKS Attendance System</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo BASE_PATH; ?>pwa/manifest.json">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_PATH; ?>public/assets/favicon.png">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>public/css/main.css">
    
    <style>
        :root {
            --bg-color: <?php echo getSetting('theme_bg_color', '#121212'); ?>;
            --primary-color: <?php echo getSetting('theme_primary_color', '#BFC6C4'); ?>;
            --text-color: <?php echo getSetting('theme_text_color', '#E8E2D8'); ?>;
            --success-color: <?php echo getSetting('theme_success_color', '#6F8F72'); ?>;
            --alert-color: <?php echo getSetting('theme_alert_color', '#F2A65A'); ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-container img {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .subtitle {
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.9;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid transparent;
            border-radius: 12px;
            color: var(--text-color);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.12);
        }
        
        .form-group input::placeholder {
            color: var(--text-color);
            opacity: 0.5;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            color: var(--bg-color);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(191, 198, 196, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .error-message {
            background: rgba(242, 166, 90, 0.1);
            color: var(--alert-color);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
            border-left: 4px solid var(--alert-color);
        }
        
        .error-message.show {
            display: block;
        }
        
        .location-status {
            text-align: center;
            font-size: 13px;
            margin-top: 15px;
            opacity: 0.7;
        }
        
        .location-status.success {
            color: var(--success-color);
        }
        
        .location-status.error {
            color: var(--alert-color);
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .pwa-install {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .pwa-install button {
            background: none;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pwa-install button:hover {
            background: var(--primary-color);
            color: var(--bg-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <?php 
            $logo = getSetting('company_logo');
            if ($logo): ?>
                <img src="<?php echo BASE_PATH; ?>public/assets/uploads/<?php echo $logo; ?>" alt="Company Logo">
            <?php endif; ?>
            <div class="company-name"><?php echo getSetting('company_name', 'VKS Solutions'); ?></div>
            <div class="subtitle">Attendance System</div>
        </div>
        
        <div class="error-message" id="errorMessage"></div>
        
        <form id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="location" id="location">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                <span id="btnText">Sign In</span>
                <div class="loading-spinner" id="loadingSpinner"></div>
            </button>
        </form>
        
        <div class="location-status" id="locationStatus"></div>
        
        <div class="forgot-password">
            <a href="<?php echo BASE_PATH; ?>auth/forgot-password">Forgot Password?</a>
        </div>
        
        <div class="pwa-install" id="pwaInstall" style="display: none;">
            <button id="installBtn">📱 Install App</button>
        </div>
    </div>
    
    <script>
        // Geolocation handling
        let locationData = null;
        
        function getLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject('Geolocation is not supported by your browser');
                    return;
                }
                
                const statusEl = document.getElementById('locationStatus');
                statusEl.textContent = 'Getting your location...';
                statusEl.className = 'location-status';
                
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        locationData = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy,
                            timestamp: new Date().toISOString()
                        };
                        
                        statusEl.textContent = '✓ Location captured';
                        statusEl.className = 'location-status success';
                        
                        document.getElementById('location').value = JSON.stringify(locationData);
                        resolve(locationData);
                    },
                    (error) => {
                        let message = 'Location access denied';
                        
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                message = 'Please allow location access to continue';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                message = 'Location information unavailable';
                                break;
                            case error.TIMEOUT:
                                message = 'Location request timed out';
                                break;
                        }
                        
                        statusEl.textContent = '⚠ ' + message;
                        statusEl.className = 'location-status error';
                        
                        reject(message);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        }
        
        // Get location on page load
        window.addEventListener('load', () => {
            getLocation().catch(err => {
                console.error('Location error:', err);
            });
        });
        
        // Login form handling
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('loadingSpinner');
            const errorMsg = document.getElementById('errorMessage');
            
            // Check location
            if (!locationData) {
                try {
                    await getLocation();
                } catch (err) {
                    showError('Location access is required. Please allow location and try again.');
                    return;
                }
            }
            
            // Disable button and show loading
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'block';
            errorMsg.classList.remove('show');
            
            // Submit form
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('<?php echo BASE_PATH; ?>auth/processLogin', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to dashboard
                    window.location.href = result.redirect;
                } else {
                    showError(result.message);
                    
                    // If location required, try to get it again
                    if (result.require_location) {
                        getLocation();
                    }
                }
            } catch (error) {
                console.error('Login error:', error);
                showError('Network error. Please check your connection and try again.');
            } finally {
                btn.disabled = false;
                btnText.style.display = 'block';
                spinner.style.display = 'none';
            }
        });
        
        function showError(message) {
            const errorMsg = document.getElementById('errorMessage');
            errorMsg.textContent = message;
            errorMsg.classList.add('show');
        }
        
        // PWA Install prompt
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('pwaInstall').style.display = 'block';
        });
        
        document.getElementById('installBtn').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                document.getElementById('pwaInstall').style.display = 'none';
            }
            
            deferredPrompt = null;
        });
        
        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo BASE_PATH; ?>pwa/sw.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.error('Service Worker registration failed:', err));
        }
    </script>
</body>
</html>
