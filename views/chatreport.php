<?php
require_once 'config.php'; 

// Enhanced report data collection with more metrics
function getReportData($con) {
    $reportData = [];
    
    // Total Appointments
    $totalAppointments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM appointments"))['total'];
    
    // Total Revenue (from both payment and treatment_history)
    $paymentRevenue = mysqli_fetch_assoc(mysqli_query($con, "SELECT SUM(amount) as total FROM payment WHERE status = 'paid'"))['total'] ?? 0;
    $treatmentRevenue = mysqli_fetch_assoc(mysqli_query($con, "SELECT SUM(treatment_cost) as total FROM treatment_history"))['total'] ?? 0;
    $totalRevenue = max($paymentRevenue, $treatmentRevenue); // Take the larger value
    
    // Monthly Appointments
    $monthlyAppointments = mysqli_fetch_assoc(mysqli_query($con, 
        "SELECT COUNT(*) as total FROM appointments WHERE MONTH(appointment_date) = MONTH(CURRENT_DATE()) 
         AND YEAR(appointment_date) = YEAR(CURRENT_DATE())"))['total'];
    
    // Popular Service
    $popularService = mysqli_fetch_assoc(mysqli_query($con, 
        "SELECT s.service_category, COUNT(*) as count 
         FROM appointments a 
         LEFT JOIN services s ON a.service_id = s.service_id 
         GROUP BY s.service_category 
         ORDER BY count DESC LIMIT 1"));
    
    // No-Show Rate Calculation
    $noShowData = mysqli_fetch_assoc(mysqli_query($con, "
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) as no_shows
        FROM appointments
    "));
    $noShowRate = $noShowData['total_appointments'] > 0 ? 
        round(($noShowData['no_shows'] / $noShowData['total_appointments']) * 100, 2) : 0;
    
    // Appointment Status Breakdown
    $statusQuery = mysqli_query($con, "
        SELECT status, COUNT(*) as count 
        FROM appointments 
        GROUP BY status
    ");
    $appointmentStatuses = [];
    while ($row = mysqli_fetch_assoc($statusQuery)) {
        $appointmentStatuses[$row['status']] = $row['count'];
    }
    
    // Monthly Service Data
    $monthlyServiceData = [];
    $currentYear = date('Y');
    
    for ($month = 1; $month <= 12; $month++) {
        $sql = "SELECT s.service_category, COUNT(*) AS count
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.service_id
                WHERE MONTH(a.appointment_date) = $month AND YEAR(a.appointment_date) = $currentYear
                GROUP BY s.service_category";
        
        $result = mysqli_query($con, $sql);
        $services = [];
        $counts = [];
        $total = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $services[] = $row['service_category'];
            $counts[] = (int)$row['count'];
            $total += (int)$row['count'];
        }
        
        $monthlyServiceData[$month] = [
            'labels' => $services,
            'counts' => $counts,
            'total' => $total
        ];
    }
    
    // Recent Appointments (Last 30 days)
    $sql = "SELECT appointment_date, COUNT(*) as count FROM appointments 
            WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY appointment_date ORDER BY appointment_date";
    $result = mysqli_query($con, $sql);
    $dates = [];
    $counts = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $dates[] = date('M j', strtotime($row['appointment_date']));
        $counts[] = (int)$row['count'];
    }
    
    // Financial Data (Last 30 days)
    $sql = "SELECT a.appointment_date, SUM(p.amount) as total_amount
            FROM payment p
            INNER JOIN appointments a ON p.appointment_id = a.appointment_id
            WHERE p.status = 'paid' AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY a.appointment_date
            ORDER BY a.appointment_date";
    $result = mysqli_query($con, $sql);
    $datesPaid = [];
    $amountsPaid = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $datesPaid[] = date('M j', strtotime($row['appointment_date']));
        $amountsPaid[] = (float)$row['total_amount'];
    }
    
    // Today's appointments
    $todayAppointments = mysqli_fetch_assoc(mysqli_query($con, "
        SELECT COUNT(*) as total FROM appointments 
        WHERE DATE(appointment_date) = CURDATE()
    "))['total'];
    
    $reportData = [
        'total_appointments' => $totalAppointments,
        'total_revenue' => $totalRevenue,
        'monthly_appointments' => $monthlyAppointments,
        'today_appointments' => $todayAppointments,
        'popular_service' => $popularService['service_category'] ?? 'N/A',
        'no_show_rate' => $noShowRate,
        'appointment_statuses' => $appointmentStatuses,
        'monthly_service_data' => $monthlyServiceData,
        'recent_appointments' => [
            'dates' => $dates,
            'counts' => $counts
        ],
        'financial_data' => [
            'dates' => $datesPaid,
            'amounts' => $amountsPaid
        ]
    ];
    
    return $reportData;
}

$reportContext = getReportData($con);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Analyst AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chatbot-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .chatbot-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
            position: relative;
        }
        
        .chatbot-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
        }
        
        .chatbot-icon i {
            color: white;
            font-size: 24px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            animation: bounce 1s infinite;
        }
        
        .chat-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 420px;
            height: 600px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        
        .chat-header h3 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .chat-header .subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 2px;
        }
        
        .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
            padding: 8px;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 85%;
            word-wrap: break-word;
            line-height: 1.4;
            animation: messageSlide 0.3s ease-out;
        }
        
        .user-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 6px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .ai-message {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .ai-message strong {
            color: #1f2937;
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .chat-input-container {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            background: white;
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            resize: none;
            max-height: 100px;
            min-height: 20px;
            line-height: 1.4;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }
        
        .chat-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .quick-actions {
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .quick-btn {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            color: #4b5563;
        }
        
        .quick-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-1px);
        }
        
        .pdf-download {
            padding: 15px 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: none;
            align-items: center;
            justify-content: space-between;
            animation: slideDown 0.3s ease-out;
        }
        
        .pdf-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        .pdf-info {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            border-bottom-left-radius: 6px;
            color: #6b7280;
            font-style: italic;
        }
        
        .typing-dots {
            display: flex;
            gap: 3px;
        }
        
        .typing-dot {
            width: 6px;
            height: 6px;
            background: #9ca3af;
            border-radius: 50%;
            animation: typingBounce 1.4s infinite ease-in-out;
        }
        
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        .suggestions {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .suggestions-title {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .suggestion-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .suggestion-chip {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 6px 12px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #4b5563;
        }
        
        .suggestion-chip:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        
        @keyframes messageSlide {
            from { 
                opacity: 0;
                transform: translateY(10px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideDown {
            from { 
                opacity: 0;
                transform: translateY(-10px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes typingBounce {
            0%, 80%, 100% { 
                transform: scale(0.8);
                opacity: 0.5;
            }
            40% { 
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Scrollbar styling */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Responsive design */
        @media (max-width: 480px) {
            .chat-window {
                width: 350px;
                right: 10px;
                bottom: 70px;
            }
            
            .quick-actions {
                padding: 12px 15px;
            }
            
            .quick-btn {
                padding: 6px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="chatbot-container">
        <div class="chatbot-icon" id="chatbotIcon">
            <i class="fas fa-robot"></i>
            <div class="notification-badge" id="notificationBadge" style="display: none;">!</div>
        </div>
        
        <div class="chat-window" id="chatWindow">
            <div class="chat-header">
                <div>
                    <h3><i class="fas fa-robot"></i> Report Analyst AI</h3>
                    <div class="subtitle">Powered by Gemini AI • Real-time Analytics</div>
                </div>
                <button class="close-btn" id="closeChat" title="Close chat">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="quick-actions">
                <button class="quick-btn" onclick="askQuickQuestion('Give me key insights from today\'s data')">
                    <i class="fas fa-bolt"></i> Quick Insights
                </button>
                <button class="quick-btn" onclick="askQuickQuestion('Generate comprehensive PDF report')">
                    <i class="fas fa-file-pdf"></i> PDF Report
                </button>
                <button class="quick-btn" onclick="askQuickQuestion('Analyze appointment trends and patterns')">
                    <i class="fas fa-chart-line"></i> Trends
                </button>
            </div>
            
            <div class="suggestions">
                <div class="suggestions-title">SUGGESTED QUESTIONS:</div>
                <div class="suggestion-chips">
                    <div class="suggestion-chip" onclick="askQuickQuestion('What are our most profitable services?')">Profitable Services</div>
                    <div class="suggestion-chip" onclick="askQuickQuestion('How can we reduce no-show rates?')">Reduce No-Shows</div>
                    <div class="suggestion-chip" onclick="askQuickQuestion('Show monthly performance comparison')">Monthly Comparison</div>
                    <div class="suggestion-chip" onclick="askQuickQuestion('Recommendations for improvement')">Improvement Tips</div>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="message ai-message">
                    <strong>🤖 Report Analyst AI</strong>
                    Hello! I'm your AI-powered report analyst. I can help you: 
                    <br>• Analyze clinic performance metrics
                    <br>• Identify trends and patterns  
                    <br>• Generate detailed PDF reports
                    <br>• Provide data-driven recommendations
                    <br><br>What would you like to know about your clinic data today?
                </div>
            </div>
            
            <div class="pdf-download" id="pdfDownload">
                <a href="#" class="pdf-link" id="pdfLink" target="_blank">
                    <i class="fas fa-download"></i> 
                    <div>
                        <div>Download Analytics Report</div>
                        <div class="pdf-info" id="pdfInfo">Ready for download</div>
                    </div>
                </a>
                <button class="close-btn" onclick="hidePdfDownload()" style="background: rgba(255,255,255,0.2);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="chat-input-container">
                <textarea 
                    id="aiQuestion" 
                    class="chat-input" 
                    placeholder="Ask about reports, trends, or generate PDF..." 
                    rows="1"
                    onkeydown="handleKeyPress(event)"
                    oninput="autoResize(this)"
                ></textarea>
                <button class="send-btn" id="sendButton" onclick="askAI()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
  
    // Enhanced report data from PHP
    const reportContext = <?php echo json_encode($reportContext); ?>;
    let conversationHistory = [];
    let isChatOpen = false;
    let isProcessing = false;
    
    // DOM Elements
    const chatbotIcon = document.getElementById('chatbotIcon');
    const chatWindow = document.getElementById('chatWindow');
    const chatMessages = document.getElementById('chatMessages');
    const closeChat = document.getElementById('closeChat');
    const aiQuestion = document.getElementById('aiQuestion');
    const sendButton = document.getElementById('sendButton');
    const pdfDownload = document.getElementById('pdfDownload');
    const pdfLink = document.getElementById('pdfLink');
    
    // Toggle chat window
    chatbotIcon.addEventListener('click', () => {
        isChatOpen = !isChatOpen;
        chatWindow.style.display = isChatOpen ? 'flex' : 'none';
        if (isChatOpen) {
            aiQuestion.focus();
        }
    });
    
    // Close chat
    closeChat.addEventListener('click', () => {
        isChatOpen = false;
        chatWindow.style.display = 'none';
    });
    
    // Auto-resize textarea
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
    }
    
    // Handle Enter key (with Shift for new line)
    function handleKeyPress(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            askAI();
        }
    }
    
    // Quick question buttons
    function askQuickQuestion(question) {
        aiQuestion.value = question;
        askAI();
    }
    
    // Enhanced AI function with better UX
    async function askAI() {
        const question = aiQuestion.value.trim();
        if (!question || isProcessing) return;
        
        isProcessing = true;
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Add user message to chat
        addMessageToChat('user', question, 'user-message');
        aiQuestion.value = '';
        aiQuestion.style.height = 'auto';
        
        // Show typing indicator
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'message ai-message typing-indicator';
        typingIndicator.innerHTML = 'Analyzing data...';
        chatMessages.appendChild(typingIndicator);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        try {
            const response = await fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    question: question,
                    context: reportContext,
                    history: conversationHistory
                })
            });
            
            const data = await response.json();
            
            // Remove typing indicator
            typingIndicator.remove();
            
            if (data.error) {
                addMessageToChat('ai', 'Sorry, I encountered an error: ' + data.error, 'ai-message');
            } else {
                addMessageToChat('ai', data.answer, 'ai-message');
                conversationHistory = data.history || [];
                
                // Handle PDF download
                if (data.pdf_url) {
                    showPdfDownload(data.pdf_url);
                }
            }
        } catch (error) {
            typingIndicator.remove();
            addMessageToChat('ai', 'Sorry, I encountered a network error. Please try again.', 'ai-message');
            console.error('AI Chat Error:', error);
        } finally {
            isProcessing = false;
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
        }
    }
    
    // Simple message creation
    function addMessageToChat(sender, message, className) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${className}`;
        messageDiv.textContent = message;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // PDF download handling
    function showPdfDownload(url) {
        pdfLink.href = url;
        pdfDownload.style.display = 'flex';
    }
    
    function hidePdfDownload() {
        pdfDownload.style.display = 'none';
    }
    
    // Auto-focus input when chat opens
    chatbotIcon.addEventListener('click', () => {
        setTimeout(() => aiQuestion.focus(), 100);
    });

    </script>
</body>
</html>