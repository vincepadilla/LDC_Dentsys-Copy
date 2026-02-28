<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Popup - Smart Time Suggestions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .test-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .test-title {
            color: #1c3344;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .test-subtitle {
            color: #64748b;
            font-size: 1.2rem;
            margin-bottom: 30px;
        }

        .test-button {
            background: linear-gradient(135deg, #1c3344 0%, #2d4a5e 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .test-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(28, 51, 68, 0.3);
        }

        .test-button i {
            margin-right: 10px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .feature-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #1c3344;
        }

        .feature-card h3 {
            color: #1c3344;
            margin-bottom: 10px;
        }

        .feature-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Popup Styles */
        .suggestion-popup {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
        }

        .popup-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 9999;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .popup-header {
            background: linear-gradient(135deg, #1c3344 0%, #2d4a5e 100%);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
        }

        .popup-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }

        .popup-body {
            padding: 25px;
        }

        .popup-footer {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: right;
        }

        .loading-state {
            text-align: center;
            padding: 30px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1c3344;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        .date-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .time-slot {
            background: #f8fafc;
            color: #374151;
            border: 2px solid #e2e8f0;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            min-width: 80px;
            text-align: center;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 5px;
        }

        .time-slot:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .time-slot.selected {
            background: #1c3344;
            color: white;
            border-color: #1c3344;
        }

        .slot-count {
            background: #e2e8f0;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        .btn-disregard {
            background: #6b7280;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            margin-right: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-ok {
            background: linear-gradient(135deg, #1c3344 0%, #2d4a5e 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 class="test-title">Popup Test Page</h1>
        <p class="test-subtitle">Test the smart time suggestions popup</p>
        
        <button class="test-button" onclick="openSuggestionPopup()">
            <i class="fas fa-play"></i> Test Popup Now
        </button>

        <div class="features">
            <div class="feature-card">
                <h3><i class="fas fa-database"></i> Real Data</h3>
                <p>Shows actual available time slots from your schedule</p>
            </div>
            <div class="feature-card">
                <h3><i class="fas fa-clock"></i> Smart Suggestions</h3>
                <p>Intelligent time slot recommendations</p>
            </div>
            <div class="feature-card">
                <h3><i class="fas fa-mobile-alt"></i> Responsive</h3>
                <p>Works perfectly on all devices</p>
            </div>
        </div>

        <p style="color: #64748b; margin-top: 20px;">
            Click the button above to test the popup functionality
        </p>
    </div>

    <!-- Popup Structure -->
    <div id="suggestionPopup" class="suggestion-popup">
        <div class="popup-overlay" onclick="closeSuggestionPopup()"></div>
        
        <div class="popup-content">
            <!-- Popup Header -->
            <div class="popup-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>
                        <i class="fas fa-clock"></i> Available Time Slots
                    </h3>
                    <button onclick="closeSuggestionPopup()" style="background: none; border: none; color: white; 
                            font-size: 1.5rem; cursor: pointer; padding: 5px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 0.9rem;">
                    Real-time availability from our schedule
                </p>
            </div>
            
            <!-- Popup Body -->
            <div class="popup-body">
                <!-- Loading State -->
                <div id="popupLoading" class="loading-state">
                    <div class="spinner"></div>
                    <p style="color: #64748b; margin: 0;">Checking real-time availability...</p>
                </div>
                
                <!-- Results -->
                <div id="popupResults" style="display: none;">
                    <!-- Results will be populated here -->
                </div>
                
                <!-- No Results -->
                <div id="popupNoResults" class="no-results" style="display: none; text-align: center; padding: 30px;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #a0aec0; margin-bottom: 15px;"></i>
                    <h4 style="color: #374151; margin-bottom: 10px;">No Available Slots Found</h4>
                    <p style="color: #64748b; margin-bottom: 20px;">All slots are booked for the next few days.</p>
                </div>
            </div>
            
            <!-- Popup Footer -->
            <div class="popup-footer">
                <button onclick="closeSuggestionPopup()" class="btn-disregard">
                    Close
                </button>
                <button onclick="confirmSuggestion()" class="btn-ok" id="okButton">
                    Book Selected Time
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedSlot = null;
        let selectedDate = null;
        let selectedDisplayTime = null;

        // Mock data for testing
        const mockSuggestions = [
            {
                date: new Date().toISOString().split('T')[0],
                slots: [
                    { start: '09:00:00', display: '9:00 AM' },
                    { start: '10:30:00', display: '10:30 AM' },
                    { start: '14:00:00', display: '2:00 PM' },
                    { start: '15:30:00', display: '3:30 PM' }
                ],
                message: 'Today'
            },
            {
                date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
                slots: [
                    { start: '09:30:00', display: '9:30 AM' },
                    { start: '11:00:00', display: '11:00 AM' },
                    { start: '13:30:00', display: '1:30 PM' },
                    { start: '16:00:00', display: '4:00 PM' }
                ],
                message: 'Tomorrow'
            },
            {
                date: new Date(Date.now() + 172800000).toISOString().split('T')[0],
                slots: [
                    { start: '10:00:00', display: '10:00 AM' },
                    { start: '14:30:00', display: '2:30 PM' }
                ],
                message: 'Day After Tomorrow'
            }
        ];

        // Function to open popup
        function openSuggestionPopup() {
            const popup = document.getElementById('suggestionPopup');
            const loading = document.getElementById('popupLoading');
            const results = document.getElementById('popupResults');
            const noResults = document.getElementById('popupNoResults');
            
            // Reset states
            popup.style.display = 'block';
            loading.style.display = 'block';
            results.style.display = 'none';
            noResults.style.display = 'none';
            selectedSlot = null;
            selectedDate = null;
            selectedDisplayTime = null;
            
            // Simulate API call delay
            setTimeout(() => {
                loading.style.display = 'none';
                
                // Use mock data for testing
                if (mockSuggestions.length > 0) {
                    displayPopupSuggestions(mockSuggestions);
                    results.style.display = 'block';
                } else {
                    noResults.style.display = 'block';
                }
            }, 1500);
        }

        // Function to close popup
        function closeSuggestionPopup() {
            document.getElementById('suggestionPopup').style.display = 'none';
            selectedSlot = null;
            selectedDate = null;
            selectedDisplayTime = null;
        }

        // Function to confirm selection
        function confirmSuggestion() {
            if (selectedSlot && selectedDate && selectedDisplayTime) {
                const formattedDate = new Date(selectedDate).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                alert(`✅ Appointment Booked!\n\nDate: ${formattedDate}\nTime: ${selectedDisplayTime}\n\nWe look forward to seeing you!`);
                closeSuggestionPopup();
            } else {
                alert('Please select a time slot first!');
            }
        }

        // Display suggestions in popup
        function displayPopupSuggestions(suggestions) {
            const resultsDiv = document.getElementById('popupResults');
            let html = '';
            
            suggestions.forEach(suggestion => {
                const formattedDate = new Date(suggestion.date).toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                });
                
                html += `
                    <div class="date-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div style="display: flex; align-items: center;">
                                <h4 style="color: #1c3344; margin: 0; font-size: 1.1rem;">
                                    ${suggestion.message}
                                </h4>
                                <span class="slot-count">${suggestion.slots.length} slots</span>
                            </div>
                            <span style="color: #64748b; font-size: 0.9rem;">
                                ${formattedDate}
                            </span>
                        </div>
                        
                        <div>
                `;
                
                suggestion.slots.forEach(slot => {
                    const isSelected = selectedDate === suggestion.date && selectedSlot === slot.start;
                    html += `
                        <div class="time-slot ${isSelected ? 'selected' : ''}" 
                             onclick="selectTimeSlot('${suggestion.date}', '${slot.start}', '${slot.display}')">
                            ${slot.display}
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            resultsDiv.innerHTML = html;
        }

        // Select a time slot
        function selectTimeSlot(date, time, displayTime) {
            selectedDate = date;
            selectedSlot = time;
            selectedDisplayTime = displayTime;
            
            // Update UI to show selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Highlight selected slot
            event.target.classList.add('selected');
            
            // Update OK button text
            document.getElementById('okButton').textContent = `Book ${displayTime}`;
        }

        // Add keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSuggestionPopup();
            }
        });

        console.log('Popup test page loaded successfully!');
        console.log('Click "Test Popup Now" to see the popup in action.');
    </script>
</body>
</html>