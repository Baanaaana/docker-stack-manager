<?php
// Prevent search engine indexing and crawling
header('X-Robots-Tag: noindex, nofollow, nosnippet, noarchive, nocache');

// Function to get client IP address
function getClientIp() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    
    return '127.0.0.1'; // Default fallback
}

// Function to check if IP is in CIDR range
function ipInCidr($ip, $cidr) {
    if (strpos($cidr, '/') === false) {
        // Single IP address
        return $ip === $cidr;
    }
    
    list($network, $mask) = explode('/', $cidr);
    $ipLong = ip2long($ip);
    $networkLong = ip2long($network);
    $maskLong = -1 << (32 - $mask);
    
    return ($ipLong & $maskLong) === ($networkLong & $maskLong);
}

// Function to check if client IP is allowed
function isIpAllowed($allowedIps) {
    if (empty($allowedIps)) {
        return true; // If no IPs specified, allow all
    }
    
    $clientIp = getClientIp();
    $allowedIpsList = array_map('trim', explode(',', $allowedIps));
    
    foreach ($allowedIpsList as $allowedIp) {
        if (ipInCidr($clientIp, $allowedIp)) {
            return true;
        }
    }
    
    return false;
}

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    
    return $env;
}

try {
    $config = loadEnv('.env');
    $portainerUrl = $config['PORTAINER_URL'] ?? '';
    $accessToken = $config['PORTAINER_TOKEN'] ?? '';
    $stackName = $config['STACK_NAME'] ?? '';
    $allowedIps = $config['ALLOWED_IPS'] ?? '';
    
    // Check IP access first
    if (!isIpAllowed($allowedIps)) {
        http_response_code(403);
        die('<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>403 - Access Denied</h1><p>Your IP address is not authorized to access this page.</p></body></html>');
    }
    
    if (empty($portainerUrl) || empty($accessToken) || empty($stackName)) {
        throw new Exception('Missing required environment variables: PORTAINER_URL, PORTAINER_TOKEN, STACK_NAME');
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, nosnippet, noarchive, nocache">
    <title>Docker Stack Manager</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@latest/css/materialdesignicons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .docker-icon {
            font-size: 6em;
            color: #1d63ed;
            margin-bottom: 20px;
            display: block;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5em;
            font-weight: 300;
        }

        .config-section {
            margin-bottom: 30px;
            text-align: left;
        }

        .config-section h3 {
            color: #34495e;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .stack-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
            text-align: left;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-running { background-color: #27ae60; }
        .status-stopped { background-color: #e74c3c; }
        .status-unknown { background-color: #f39c12; }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            min-width: 120px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-success {
            background: linear-gradient(45deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        .btn.loading {
            pointer-events: none;
            position: relative;
        }

        .btn.loading .btn-text {
            visibility: hidden;
        }

        .btn.loading .btn-spinner {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
            display: inline-block !important;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            padding: 16px 24px;
            min-width: 300px;
            max-width: 500px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            pointer-events: auto;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast.removing {
            animation: slideOut 0.3s ease-in;
        }

        .toast-success {
            border-left: 4px solid #27ae60;
        }

        .toast-error {
            border-left: 4px solid #e74c3c;
        }

        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .toast-success .toast-icon {
            color: #27ae60;
        }

        .toast-error .toast-icon {
            color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: #2c3e50;
        }

        .toast-message {
            color: #5a6c7d;
            font-size: 14px;
        }

        .toast-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: #95a5a6;
            cursor: pointer;
            font-size: 20px;
            padding: 4px;
            line-height: 1;
            transition: color 0.2s;
        }

        .toast-close:hover {
            color: #2c3e50;
        }

        @media (max-width: 600px) {
            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
            }
            
            .toast {
                min-width: auto;
                max-width: 100%;
            }
        }

        .services-list {
            text-align: left;
            margin-top: 15px;
        }

        .service-item {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .service-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        #configStatus {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .config-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .config-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container">
        <i class="mdi mdi-docker docker-icon"></i>
        <h1>Docker Stack Manager</h1>
        
        <?php if (isset($error)): ?>
        <div class="config-section">
            <div id="configStatus" class="config-error">
                <p><strong>❌ Configuration Error</strong></p>
                <p><?php echo htmlspecialchars($error); ?></p>
                <p>Make sure .env file exists with: PORTAINER_URL, PORTAINER_TOKEN, STACK_NAME</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="stack-info" id="stackInfo" style="display: none;">
            <p style="margin-bottom: 15px;"><strong>Stack:</strong> <span id="stackNameDisplay"></span></p>
            <p style="margin-bottom: 15px;"><strong>Status:</strong> <span id="stackStatus"></span></p>
            
            <div class="services-list" id="servicesList">
                <h4>Services:</h4>
            </div>
        </div>

        <div class="button-group">
            <button class="btn btn-primary" onclick="getStackStatus(event)" <?php echo isset($error) ? 'disabled' : ''; ?>>
                <span class="btn-spinner" style="display: none;"></span>
                <span class="btn-text">Status</span>
            </button>
            <button class="btn btn-success" id="startBtn" onclick="startStack(event)" style="display: none;" <?php echo isset($error) ? 'disabled' : ''; ?>>
                <span class="btn-spinner" style="display: none;"></span>
                <span class="btn-text">Start</span>
            </button>
            <button class="btn btn-danger" id="stopBtn" onclick="stopStack(event)" style="display: none;" <?php echo isset($error) ? 'disabled' : ''; ?>>
                <span class="btn-spinner" style="display: none;"></span>
                <span class="btn-text">Stop</span>
            </button>
            <button class="btn btn-warning" id="restartBtn" onclick="restartStack(event)" style="display: none;" <?php echo isset($error) ? 'disabled' : ''; ?>>
                <span class="btn-spinner" style="display: none;"></span>
                <span class="btn-text">Restart</span>
            </button>
        </div>
    </div>

    <script>
        // Configuration loaded from PHP
        const CONFIG = {
            portainerUrl: '<?php echo isset($error) ? '' : $portainerUrl; ?>',
            accessToken: '<?php echo isset($error) ? '' : $accessToken; ?>',
            stackName: '<?php echo isset($error) ? '' : $stackName; ?>',
            hasError: <?php echo isset($error) ? 'true' : 'false'; ?>
        };

        let endpointId = '';
        let activeButton = null;

        function showLoading(show, button = null) {
            const allButtons = document.querySelectorAll('.btn');
            
            if (button) {
                if (show) {
                    activeButton = button;
                    button.classList.add('loading');
                    button.querySelector('.btn-spinner').style.display = 'inline-block';
                    
                    // Disable all buttons
                    allButtons.forEach(btn => {
                        btn.disabled = true;
                    });
                } else {
                    button.classList.remove('loading');
                    button.querySelector('.btn-spinner').style.display = 'none';
                    
                    // Re-enable all buttons (except those with error state)
                    if (!CONFIG.hasError) {
                        allButtons.forEach(btn => {
                            btn.disabled = false;
                        });
                    }
                    
                    activeButton = null;
                }
            } else if (activeButton && !show) {
                // Hide loading on the active button if no specific button provided
                activeButton.classList.remove('loading');
                activeButton.querySelector('.btn-spinner').style.display = 'none';
                
                // Re-enable all buttons (except those with error state)
                if (!CONFIG.hasError) {
                    allButtons.forEach(btn => {
                        btn.disabled = false;
                    });
                }
                
                activeButton = null;
            }
        }

        function showAlert(message, type = 'success') {
            showToast(message, type);
        }

        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Create toast content
            const icon = type === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle';
            const title = type === 'success' ? 'Success' : 'Error';
            
            toast.innerHTML = `
                <i class="mdi ${icon} toast-icon"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="removeToast(this)">&times;</button>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    removeToast(toast.querySelector('.toast-close'));
                }
            }, 5000);
        }

        function removeToast(closeBtn) {
            const toast = closeBtn.closest('.toast');
            toast.classList.add('removing');
            
            setTimeout(() => {
                toast.remove();
            }, 300);
        }

        async function validateConfig() {
            if (CONFIG.hasError) {
                showAlert('Configuration error. Please check your .env file.', 'error');
                return false;
            }

            try {
                // Get endpoint ID
                const response = await fetch(`${CONFIG.portainerUrl}/api/endpoints`, {
                    headers: {
                        'X-API-Key': CONFIG.accessToken
                    }
                });

                if (!response.ok) {
                    throw new Error(`Token validation failed: ${response.status}`);
                }

                const endpoints = await response.json();
                endpointId = endpoints[0]?.Id || 1;
                
                return true;
            } catch (error) {
                showAlert(`Configuration validation failed: ${error.message}`, 'error');
                return false;
            }
        }

        async function getStackStatus(event) {
            const button = event ? event.target.closest('button') : document.querySelector('.btn-primary');
            showLoading(true, button);

            try {
                const isValid = await validateConfig();
                if (!isValid) {
                    showLoading(false);
                    return;
                }

                const response = await fetch(`${CONFIG.portainerUrl}/api/stacks`, {
                    headers: {
                        'X-API-Key': CONFIG.accessToken
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch stacks');
                }

                const stacks = await response.json();
                const stack = stacks.find(s => s.Name === CONFIG.stackName);

                if (!stack) {
                    throw new Error(`Stack '${CONFIG.stackName}' not found`);
                }

                // Get containers for Docker Compose stacks (Type 2)
                let services = [];
                if (stack.Type === 2) {
                    // Docker Compose - get containers
                    const containersResponse = await fetch(`${CONFIG.portainerUrl}/api/endpoints/${endpointId}/docker/containers/json?all=true`, {
                        headers: {
                            'X-API-Key': CONFIG.accessToken
                        }
                    });

                    if (containersResponse.ok) {
                        const allContainers = await containersResponse.json();
                        services = allContainers.filter(container => 
                            container.Labels && (
                                container.Labels['com.docker.compose.project'] === CONFIG.stackName ||
                                container.Labels['com.docker.stack.namespace'] === CONFIG.stackName
                            )
                        );
                    }
                } else {
                    // Docker Swarm - get services (Type 1)
                    const servicesResponse = await fetch(`${CONFIG.portainerUrl}/api/endpoints/${endpointId}/docker/services`, {
                        headers: {
                            'X-API-Key': CONFIG.accessToken
                        }
                    });

                    if (servicesResponse.ok) {
                        const allServices = await servicesResponse.json();
                        services = allServices.filter(service => 
                            service.Spec?.Labels?.['com.docker.stack.namespace'] === CONFIG.stackName
                        );
                    }
                }

                displayStackInfo(stack, services);
                showAlert('Stack status updated successfully');

            } catch (error) {
                showAlert(`Error: ${error.message}`, 'error');
                // Ensure buttons remain hidden on error
                const startBtn = document.getElementById('startBtn');
                const stopBtn = document.getElementById('stopBtn');
                const restartBtn = document.getElementById('restartBtn');
                if (startBtn) startBtn.style.display = 'none';
                if (stopBtn) stopBtn.style.display = 'none';
                if (restartBtn) restartBtn.style.display = 'none';
            } finally {
                showLoading(false);
            }
        }


        async function displayStackInfo(stack, services) {
            document.getElementById('stackInfo').style.display = 'block';
            document.getElementById('stackNameDisplay').textContent = stack.Name;
            
            const statusElement = document.getElementById('stackStatus');
            const statusClass = stack.Status === 1 ? 'status-running' : 'status-stopped';
            const statusText = stack.Status === 1 ? 'Running' : 'Stopped';
            const stackType = stack.Type === 1 ? 'Docker Swarm' : 'Docker Compose';
            
            statusElement.innerHTML = `<span class="status-indicator ${statusClass}"></span>${statusText} (${stackType})`;
            
            // Show/hide buttons based on stack status
            const isRunning = stack.Status === 1;
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const restartBtn = document.getElementById('restartBtn');

            // Safety check for button elements
            if (startBtn && stopBtn && restartBtn) {
                if (isRunning) {
                    // Stack is running - show stop and restart buttons
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'inline-block';
                    restartBtn.style.display = 'inline-block';
                } else {
                    // Stack is stopped - show only start button
                    startBtn.style.display = 'inline-block';
                    stopBtn.style.display = 'none';
                    restartBtn.style.display = 'none';
                }
            } else {
                console.warn('Button elements not found:', { startBtn, stopBtn, restartBtn });
            }

            // Display services/containers
            const servicesList = document.getElementById('servicesList');
            servicesList.innerHTML = stack.Type === 1 ? '<h4>Services:</h4>' : '<h4>Containers:</h4>';
            
            if (services.length > 0) {
                // Sort services/containers alphabetically
                services.sort((a, b) => {
                    let nameA, nameB;
                    if (stack.Type === 1) {
                        // Docker Swarm services
                        nameA = a.Spec?.Name || '';
                        nameB = b.Spec?.Name || '';
                    } else {
                        // Docker Compose containers
                        nameA = a.Names ? a.Names[0].replace('/', '') : a.Id.substring(0, 12);
                        nameB = b.Names ? b.Names[0].replace('/', '') : b.Id.substring(0, 12);
                    }
                    return nameA.localeCompare(nameB);
                });
                
                services.forEach(service => {
                    const serviceDiv = document.createElement('div');
                    serviceDiv.className = 'service-item';
                    
                    if (stack.Type === 1) {
                        // Docker Swarm service
                        serviceDiv.innerHTML = `
                            <span>${service.Spec.Name}</span>
                            <span>${service.Spec.Mode.Replicated ? `${service.Spec.Mode.Replicated.Replicas} replicas` : 'Global'}</span>
                        `;
                    } else {
                        // Docker Compose container
                        const containerName = service.Names ? service.Names[0].replace('/', '') : service.Id.substring(0, 12);
                        const containerStatus = service.State || 'unknown';
                        serviceDiv.innerHTML = `
                            <span>${containerName}</span>
                            <span class="service-status">
                                <span class="status-indicator ${containerStatus === 'running' ? 'status-running' : 'status-stopped'}"></span>
                            </span>
                            <span>${containerStatus}</span>
                        `;
                    }
                    servicesList.appendChild(serviceDiv);
                });
            } else {
                const noServicesDiv = document.createElement('div');
                noServicesDiv.textContent = stack.Type === 1 ? 'No services found' : 'No containers found';
                servicesList.appendChild(noServicesDiv);
            }
        }

        async function performStackAction(action, event) {
            const button = event ? event.target.closest('button') : null;
            showLoading(true, button);

            try {
                const isValid = await validateConfig();
                if (!isValid) {
                    showLoading(false);
                    return;
                }

                // Get stack ID first
                const stacksResponse = await fetch(`${CONFIG.portainerUrl}/api/stacks`, {
                    headers: {
                        'X-API-Key': CONFIG.accessToken
                    }
                });

                if (!stacksResponse.ok) {
                    throw new Error('Failed to fetch stacks');
                }

                const stacks = await stacksResponse.json();
                const stack = stacks.find(s => s.Name === CONFIG.stackName);

                if (!stack) {
                    throw new Error(`Stack '${CONFIG.stackName}' not found`);
                }

                console.log('Stack found:', stack);
                console.log('Stack type:', stack.Type); // 1 = Swarm, 2 = Compose
                console.log('Endpoint ID:', endpointId);
                console.log('Attempting action:', action);

                if (stack.Type === 2) {
                    // Docker Compose Stack
                    const endpoint = action === 'start' ? 'start' : 'stop';
                    const response = await fetch(`${CONFIG.portainerUrl}/api/stacks/${stack.Id}/${endpoint}?endpointId=${endpointId}`, {
                        method: 'POST',
                        headers: {
                            'X-API-Key': CONFIG.accessToken,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('API Error Response:', errorText);
                        throw new Error(`Failed to ${action} stack: ${response.status} - ${errorText}`);
                    }

                } else {
                    // Docker Swarm Stack (Type 1) - use service scaling
                    const servicesResponse = await fetch(`${CONFIG.portainerUrl}/api/endpoints/${endpointId}/docker/services`, {
                        headers: {
                            'X-API-Key': CONFIG.accessToken
                        }
                    });

                    if (!servicesResponse.ok) {
                        throw new Error('Failed to fetch services');
                    }

                    const allServices = await servicesResponse.json();
                    const stackServices = allServices.filter(service => 
                        service.Spec?.Labels?.['com.docker.stack.namespace'] === CONFIG.stackName
                    );

                    console.log('Found stack services:', stackServices.length);

                    for (const service of stackServices) {
                        const targetReplicas = action === 'stop' ? 0 : 1;
                        const updatePayload = {
                            ...service.Spec,
                            Mode: {
                                ...service.Spec.Mode,
                                Replicated: {
                                    Replicas: targetReplicas
                                }
                            }
                        };

                        const updateResponse = await fetch(`${CONFIG.portainerUrl}/api/endpoints/${endpointId}/docker/services/${service.ID}`, {
                            method: 'POST',
                            headers: {
                                'X-API-Key': CONFIG.accessToken,
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                spec: updatePayload,
                                version: service.Version.Index
                            })
                        });

                        if (!updateResponse.ok) {
                            console.warn(`Failed to ${action} service ${service.Spec.Name}`);
                        }
                    }
                }

                showAlert(`Stack ${action} operation completed successfully`);
                
                // Auto-refresh status after action
                setTimeout(() => getStackStatus(), 3000);

            } catch (error) {
                console.error('Full error:', error);
                showAlert(`Error: ${error.message}`, 'error');
            } finally {
                showLoading(false);
            }
        }

        async function startStack(event) {
            await performStackAction('start', event);
        }

        async function stopStack(event) {
            await performStackAction('stop', event);
        }

        async function restartStack(event) {
            await performStackAction('stop', event);
            setTimeout(() => performStackAction('start', event), 2000);
        }

        // Auto-load stack status if configuration is valid
        if (!CONFIG.hasError) {
            window.addEventListener('load', () => {
                setTimeout(() => getStackStatus(), 1000);
            });
        }
    </script>
</body>
</html>