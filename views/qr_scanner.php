<?php
session_start();
include_once("../database/config.php");
define("TITLE", "QR Scanner");

// Check if user is logged in and has admin/cashier/dentist role
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$user_role = strtolower($_SESSION['role'] ?? '');
$allowed_roles = ['admin', 'cashier', 'dentist', 'receptionist'];
if (!in_array($user_role, $allowed_roles)) {
    header("Location: account.php");
    exit();
}

include_once('../layouts/header.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/accountstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Poppins', sans-serif;
        }
        
        .scanner-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .scanner-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .scanner-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .scanner-controls {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 10px 0 15px 0;
        }

        .btn-scanner {
            padding: 14px 22px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            min-width: 180px;
            justify-content: center;
        }

        .btn-start-scanner {
            background: #2196f3;
            color: #fff;
        }

        .btn-start-scanner:hover {
            background: #1976d2;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(33, 150, 243, 0.25);
        }

        .btn-stop-scanner {
            background: #ef4444;
            color: #fff;
        }

        .btn-stop-scanner:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.20);
        }

        .btn-scanner:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        #reader {
            width: 100%;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            background: #0b1220;
            color: #fff;
        }

        .reader-idle {
            min-height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 18px;
            border-radius: 12px;
        }
        
        .scanner-instructions {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: #1976d2;
        }
        
        .scanner-instructions p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .scanned-info {
            display: none;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .scanned-info.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
        }
        
        .ticket-code {
            font-size: 24px;
            font-weight: 700;
            color: #2196f3;
            letter-spacing: 2px;
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .patient-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            text-align: center;
            padding: 10px 0;
        }
        
        .btn-process-payment {
            width: 100%;
            padding: 15px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-process-payment:hover:not(:disabled) {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-process-payment:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-reset {
            width: 100%;
            padding: 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-reset:hover {
            background: #5a6268;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: none;
        }
        
        .success-message.show {
            display: block;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .scanner-container {
                padding: 10px;
            }
            
            .scanner-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="scanner-card">
            <h1 class="scanner-title">
                <i class="fas fa-qrcode"></i> QR Code Scanner
            </h1>
            
            <div class="scanner-instructions">
                <p><strong>Instructions:</strong></p>
                <p>1. Position the QR code within the scanner frame</p>
                <p>2. Wait for automatic detection</p>
                <p>3. Verify the ticket code and patient name</p>
                <p>4. Click "Process Payment" to finalize</p>
            </div>

            <!-- Default State: Scanner OFF -->
            <div class="scanner-controls">
                <button type="button" class="btn-scanner btn-start-scanner" id="startScannerBtn">
                    <i class="fas fa-camera"></i> Start Scanner
                </button>
                <button type="button" class="btn-scanner btn-stop-scanner" id="stopScannerBtn" style="display:none;">
                    <i class="fas fa-stop-circle"></i> Stop Scanner
                </button>
            </div>

            <!-- Scanner viewport container -->
            <div id="reader">
                <div class="reader-idle" id="readerIdle">
                    <div>
                        <div style="font-size: 18px; font-weight: 700; margin-bottom: 6px;">Scanner is OFF</div>
                        <div style="font-size: 13px; opacity: 0.85;">Tap <strong>Start Scanner</strong> to enable the camera.</div>
                    </div>
                </div>
            </div>
            
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>
            
            <div class="scanned-info" id="scannedInfo">
                <div class="loading" id="loadingInfo" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Fetching appointment details...
                </div>
                <div id="appointmentDetails" style="display: none;">
                    <div class="info-item">
                        <span class="info-label">Ticket Code:</span>
                    </div>
                    <div class="ticket-code" id="ticketCodeDisplay"></div>
                    <div class="patient-name" id="patientNameDisplay"></div>
                    
                    <div class="info-item">
                        <span class="info-label">Appointment ID:</span>
                        <span class="info-value" id="appointmentIdDisplay"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date:</span>
                        <span class="info-value" id="appointmentDateDisplay"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Time:</span>
                        <span class="info-value" id="appointmentTimeDisplay"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge" id="statusBadge"></span>
                        </span>
                    </div>
                    
                    <button class="btn-process-payment" id="processPaymentBtn" onclick="processPayment()" style="display: none;">
                        <i class="fas fa-check-circle"></i> Confirm Payment
                    </button>
                    <button class="btn-reset" onclick="resetScanner()">
                        <i class="fas fa-redo"></i> Scan Another
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let currentAppointmentId = null;
        let isProcessing = false;
        let isScannerRunning = false;

        function setScannerUiState(running) {
            const startBtn = document.getElementById('startScannerBtn');
            const stopBtn = document.getElementById('stopScannerBtn');
            const idle = document.getElementById('readerIdle');

            if (running) {
                startBtn.style.display = 'none';
                stopBtn.style.display = 'inline-flex';
                if (idle) idle.style.display = 'none';
            } else {
                startBtn.style.display = 'inline-flex';
                stopBtn.style.display = 'none';
                if (idle) idle.style.display = 'flex';
            }
        }

        // Start scanner ONLY on user click (camera permission prompt happens here)
        async function startScanner() {
            const startBtn = document.getElementById('startScannerBtn');
            const stopBtn = document.getElementById('stopScannerBtn');

            if (isScannerRunning) return;

            startBtn.disabled = true;
            startBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';

            try {
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5Qrcode("reader");
                }

                await html5QrcodeScanner.start(
                    { facingMode: "environment" }, // Use back camera
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    onScanSuccess,
                    onScanError
                );

                isScannerRunning = true;
                setScannerUiState(true);
            } catch (err) {
                console.error("Unable to start scanning", err);
                showError("Unable to access camera. Please allow camera permission and try again.");
                isScannerRunning = false;
                setScannerUiState(false);
            } finally {
                startBtn.disabled = false;
                startBtn.innerHTML = '<i class="fas fa-camera"></i> Start Scanner';
                stopBtn.disabled = false;
            }
        }

        async function stopScanner() {
            const stopBtn = document.getElementById('stopScannerBtn');
            if (!html5QrcodeScanner || !isScannerRunning) {
                isScannerRunning = false;
                setScannerUiState(false);
                return;
            }

            stopBtn.disabled = true;
            stopBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Stopping...';

            try {
                await html5QrcodeScanner.stop();
                await html5QrcodeScanner.clear();
            } catch (err) {
                console.error("Stop scanning failed", err);
            } finally {
                isScannerRunning = false;
                setScannerUiState(false);
                stopBtn.disabled = false;
                stopBtn.innerHTML = '<i class="fas fa-stop-circle"></i> Stop Scanner';
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Stop scanning once QR code is detected
            if (html5QrcodeScanner && isScannerRunning) {
                html5QrcodeScanner.stop().then(() => {
                    isScannerRunning = false;
                    setScannerUiState(false);
                    console.log("QR Code scanning stopped.");
                }).catch(err => {
                    console.error("Stop scanning failed", err);
                });
            }
            
            // Process the scanned data
            console.log("Scanned QR Code:", decodedText);
            fetchAppointmentDetails(decodedText);
        }

        function onScanError(errorMessage) {
            // Ignore scanning errors (they're frequent during scanning)
            // Only show errors for actual failures
        }

        // Fetch appointment details via AJAX
        function fetchAppointmentDetails(scannedId) {
            const scannedInfo = document.getElementById('scannedInfo');
            const loadingInfo = document.getElementById('loadingInfo');
            const appointmentDetails = document.getElementById('appointmentDetails');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            
            // Hide previous messages
            errorMessage.classList.remove('show');
            successMessage.classList.remove('show');
            
            // Show loading state
            scannedInfo.classList.add('show');
            loadingInfo.style.display = 'block';
            appointmentDetails.style.display = 'none';
            
            fetch('../controllers/getAppointmentByScan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'scanned_id=' + encodeURIComponent(scannedId)
            })
            .then(response => response.json())
            .then(data => {
                loadingInfo.style.display = 'none';
                
                if (data.success) {
                    // Display appointment details
                    currentAppointmentId = data.appointment_id;
                    
                    document.getElementById('ticketCodeDisplay').textContent = data.ticket_code || 'N/A';
                    document.getElementById('patientNameDisplay').textContent = data.patient_name || 'N/A';
                    document.getElementById('appointmentIdDisplay').textContent = data.appointment_id || 'N/A';
                    document.getElementById('appointmentDateDisplay').textContent = data.appointment_date || 'N/A';
                    document.getElementById('appointmentTimeDisplay').textContent = data.appointment_time || 'N/A';
                    
                    // Set status badge
                    const statusBadge = document.getElementById('statusBadge');
                    statusBadge.textContent = data.status || 'N/A';
                    statusBadge.className = 'status-badge status-' + (data.status ? data.status.toLowerCase() : 'pending');
                    
                    // Show and enable/disable payment button based on status
                    const processBtn = document.getElementById('processPaymentBtn');
                    if (data.status === 'Paid' || data.status === 'Complete' || data.status === 'Completed') {
                        processBtn.style.display = 'none'; // Hide button if already paid
                    } else {
                        processBtn.style.display = 'block'; // Reveal "Confirm Payment" button
                        processBtn.disabled = false;
                        processBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Payment';
                    }
                    
                    appointmentDetails.style.display = 'block';
                } else {
                    scannedInfo.classList.remove('show');
                    errorMessage.textContent = data.message || 'Appointment not found. Please verify the QR code.';
                    errorMessage.classList.add('show');
                    
                    // Restart scanner after error
                    setTimeout(() => {
                        resetScanner();
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingInfo.style.display = 'none';
                scannedInfo.classList.remove('show');
                errorMessage.textContent = 'An error occurred while fetching appointment details. Please try again.';
                errorMessage.classList.add('show');
                
                // Restart scanner after error
                setTimeout(() => {
                    resetScanner();
                }, 3000);
            });
        }

        // Process payment
        function processPayment() {
            if (!currentAppointmentId || isProcessing) {
                return;
            }
            
            if (!confirm('Are you sure you want to process payment for this appointment?')) {
                return;
            }
            
            isProcessing = true;
            const processBtn = document.getElementById('processPaymentBtn');
            processBtn.disabled = true;
            processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            fetch('../controllers/processPaymentByScan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'appointment_id=' + encodeURIComponent(currentAppointmentId)
            })
            .then(response => response.json())
            .then(data => {
                isProcessing = false;
                
                if (data.success) {
                    const successMessage = document.getElementById('successMessage');
                    successMessage.textContent = data.message || 'Payment processed successfully!';
                    successMessage.classList.add('show');
                    
                    // Update UI
                    processBtn.disabled = true;
                    processBtn.style.display = 'none'; // Hide button after payment is processed
                    
                    const statusBadge = document.getElementById('statusBadge');
                    statusBadge.textContent = 'Paid';
                    statusBadge.className = 'status-badge status-paid';
                    
                    // Auto reset after 3 seconds
                    setTimeout(() => {
                        resetScanner();
                    }, 3000);
                } else {
                    processBtn.disabled = false;
                    processBtn.innerHTML = '<i class="fas fa-check-circle"></i> Process Payment';
                    
                    const errorMessage = document.getElementById('errorMessage');
                    errorMessage.textContent = data.message || 'Failed to process payment. Please try again.';
                    errorMessage.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                isProcessing = false;
                processBtn.disabled = false;
                processBtn.innerHTML = '<i class="fas fa-check-circle"></i> Process Payment';
                
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = 'An error occurred while processing payment. Please try again.';
                errorMessage.classList.add('show');
            });
        }

        // Reset scanner
        function resetScanner() {
            currentAppointmentId = null;
            isProcessing = false;
            
            const scannedInfo = document.getElementById('scannedInfo');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            
            scannedInfo.classList.remove('show');
            errorMessage.classList.remove('show');
            successMessage.classList.remove('show');

            // Restart scanner ONLY if user previously started it
            // (otherwise keep the default OFF state)
            startScanner();
        }

        function showError(message) {
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = message;
            errorMessage.classList.add('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Default state: camera OFF
            setScannerUiState(false);

            const startBtn = document.getElementById('startScannerBtn');
            const stopBtn = document.getElementById('stopScannerBtn');

            startBtn.addEventListener('click', startScanner);
            stopBtn.addEventListener('click', stopScanner);
        });
    </script>

<?php include_once('../layouts/footer.php'); ?>
</body>
</html>
