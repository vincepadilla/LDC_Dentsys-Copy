<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dental Clinic</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary-color: #5b94f6ff;
      --secondary-color: #f0f8ff;
      --bot-bubble-color: #e9ecef;
      --user-bubble-color: #48A6A7;
      --text-color: #333;
      --body-bg: #f4f7f6;
      --quick-question-bg: #f8f9fa;
      --quick-question-hover: #e2e6ea;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Lato', sans-serif;
      background-color: var(--body-bg);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Floating Chat Button */
    #chat-toggle {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      font-size: 30px;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
      display: flex;
      justify-content: center;
      align-items: center;
      transition: background-color 0.3s, transform 0.3s;
      z-index: 999;
    }

    #chat-toggle:hover {
      background-color: #0056b3;
      transform: scale(1.1);
    }

    /* Chat Widget Window */
    #chat-widget {
      position: fixed;
      bottom: 90px;
      right: 20px;
      width: 380px;
      height: 560px;
      background-color: #fff;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
      display: none;
      flex-direction: column;
      overflow: hidden;
      border: 1px solid #ddd;
      z-index: 5000;
      animation: fadeInUp 0.3s ease;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    #chat-header {
      background-color: var(--primary-color);
      color: white;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    #chat-header h3 {
      margin: 0;
      font-size: 1.1em;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    #close-chat {
      background: transparent;
      border: none;
      color: white;
      font-size: 1.3em;
      cursor: pointer;
    }

    #chat-box {
      flex-grow: 1;
      padding: 15px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 10px;
      scroll-behavior: smooth;
    }

    .chat-message {
      padding: 10px 14px;
      border-radius: 20px;
      max-width: 85%;
      line-height: 1.2;
      font-size: 0.95em;
      white-space: pre-wrap;
      word-wrap: break-word;
      border: 1px solid black;
    }

    .user-message {
      background-color: var(--user-bubble-color);
      color: white;
      align-self: flex-end;
      border-bottom-right-radius: 5px;
    }

    .bot-message {
      background-color: var(--bot-bubble-color);
      color: var(--text-color);

    }

    /* Quick Questions Section */
    .quick-questions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
      margin-bottom: 5px;
    }

    .quick-question-btn {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1.5px solid #dee2e6;
        border-radius: 12px;
        padding: 9px 12px;
        font-size: 0.8em;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #495057;
        flex: 0 0 calc(50% - 3px);
        text-align: center;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        min-height: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .quick-question-btn:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .quick-question-btn i {
        font-size: 0.8em;
        flex-shrink: 0;
    }
    

    /* Chat Actions */
    .chat-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 8px;
      padding: 0 5px;
    }

    .action-btn {
      background: transparent;
      border: none;
      color: var(--primary-color);
      font-size: 0.8em;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      border-radius: 12px;
      transition: background-color 0.2s;
    }

    .action-btn:hover {
      background-color: rgba(91, 148, 246, 0.1);
    }

    #chat-form-container {
      background-color: #ffffff;
      padding: 10px;
      border-top: 1px solid #eee;
    }

    #chat-form {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    #user-input {
      flex-grow: 1;
      border: 1px solid black;
      border-radius: 20px;
      padding: 10px 15px;
      font-size: 0.95em;
      outline: none;
      transition: border-color 0.3s;
    }

    #user-input:focus {
      border-color: var(--primary-color);
    }

    #send-btn {
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      cursor: pointer;
      font-size: 1.2em;
      display: flex;
      justify-content: center;
      align-items: center;
      transition: background-color 0.3s;
    }

    #send-btn:hover {
      background-color: #0056b3;
    }

    /* Typing indicator */
    .typing-indicator {
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 10px 14px;
      background-color: var(--bot-bubble-color);
      border-radius: 20px;
      align-self: flex-start;
      border-bottom-left-radius: 5px;
      max-width: 85%;
    }

    .typing-dot {
      width: 8px;
      height: 8px;
      background-color: #888;
      border-radius: 50%;
      animation: typing 1.4s infinite ease-in-out;
    }

    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    @keyframes typing {
      0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
      40% { transform: scale(1); opacity: 1; }
    }

    /* Service List Styles */
    .service-category {
    font-weight: bold;
    margin-top: 8px;
    color: var(--primary-color);
    line-height: 1.2;
    }

    .service-list {
        margin: 2px 0 4px 0;
        padding-left: 15px;
    }

    .service-list li {
        margin-bottom: 1px;
        font-size: 0.9em;
    }

    /* 📱 Responsive Adjustments */
    @media (max-width: 768px) {
      #chat-widget {
        width: 90%;
        height: 75%;
        bottom: 80px;
        right: 5%;
        left: 5%;
        border-radius: 12px;
      }

      #chat-header h3 {
        font-size: 1em;
      }

      #chat-toggle {
        width: 55px;
        height: 55px;
        font-size: 26px;
        bottom: 15px;
        right: 15px;
      }

      .chat-message {
        font-size: 0.9em;
      }

      .quick-questions {
        gap: 5px;
      }

      .quick-question-btn {
        font-size: 0.75em;
        padding: 5px 8px;
      }
    }

    @media (max-width: 480px) {
      #chat-widget {
        width: 95%;
        height: 80%;
        bottom: 70px;
        right: 2.5%;
        left: 2.5%;
      }

      #chat-header h3 {
        font-size: 0.9em;
      }

      #chat-toggle {
        width: 50px;
        height: 50px;
        font-size: 24px;
      }

      #user-input {
        font-size: 0.85em;
        padding: 8px 12px;
      }

      #send-btn {
        width: 36px;
        height: 36px;
        font-size: 1em;
      }

      .quick-questions {
        flex-direction: row;
      }

      .quick-question-btn {
        max-width: calc(50% - 3px);
      }
    }
  </style>
</head>

<body>
  <!-- Floating chat toggle button -->
  <button id="chat-toggle" title="Chat with us">
    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#FFFFFF">
      <path d="M240-520h60v-80h-60v80Zm100 80h60v-240h-60v240Zm110 80h60v-400h-60v400Zm110-80h60v-240h-60v240Zm100-80h60v-80h-60v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z"/>
    </svg>
  </button>

  <!-- Chat widget -->
  <div id="chat-widget">
    <div id="chat-header">
      <h3><i class="fas fa-tooth"></i> Landero Dental Clinic</h3>
      <button id="close-chat" title="Close">&times;</button>
    </div>

    <div id="chat-box">
      <div class="chat-message bot-message">
        <strong>Hello! 👋 Welcome to Landero Dental Clinic.</strong><br>
        I'm here to help you with information about our services, appointments, and dental care. How can I assist you today?
        
        <div class="quick-questions">
          <button class="quick-question-btn" data-question="What services do you offer?">
            <i class="fas fa-teeth"></i> Our Services
          </button>
          <button class="quick-question-btn" data-question="How do I book an appointment?">
            <i class="fas fa-calendar-check"></i> Book Appointment
          </button>
          <button class="quick-question-btn" data-question="What are your opening hours?">
            <i class="fas fa-clock"></i> Opening Hours
          </button>
          <button class="quick-question-btn" data-question="Where are you located?">
            <i class="fas fa-map-marker-alt"></i> Location
          </button>
          <button class="quick-question-btn" data-question="How much is a dental checkup?">
            <i class="fas fa-dollar-sign"></i> Checkup Cost
          </button>
        </div>
      </div>
    </div>

    <div id="chat-form-container">
      <form id="chat-form">
        <input type="text" id="user-input" placeholder="Type your message..." autocomplete="off" />
        <button id="send-btn" type="submit">
          <i class="fas fa-paper-plane"></i>
        </button>
      </form>
      <div class="chat-actions">
        <button class="action-btn" id="clear-chat">
          <i class="fas fa-trash-alt"></i> Clear Chat
        </button>
        <button class="action-btn" id="suggest-questions">
          <i class="fas fa-lightbulb"></i> More Questions
        </button>
      </div>
    </div>
  </div>

  <script>
        const chatToggle = document.getElementById('chat-toggle');
    const chatWidget = document.getElementById('chat-widget');
    const closeChat = document.getElementById('close-chat');
    const chatForm = document.getElementById('chat-form');
    const userInput = document.getElementById('user-input');
    const chatBox = document.getElementById('chat-box');
    const sendBtn = document.getElementById('send-btn');
    const clearChatBtn = document.getElementById('clear-chat');
    const suggestQuestionsBtn = document.getElementById('suggest-questions');
    const quickQuestionBtns = document.querySelectorAll('.quick-question-btn');
    
    // API endpoint candidates (supports being served from "/" or from "/views/")
    const apiUrlCandidates = [
      'controllers/chat_api.php',
      '../controllers/chat_api.php'
    ];

    // Dental services information
    const dentalServices = {
      "General Dentistry": [
        "Oral Prophylaxis",
        "Fluoride Application",
        "Pit & Fissure Sealants",
        "Tooth Restoration (Pasta)",
        "Root Canal Treatment"
      ],
      "Orthodontics": [
        "Braces",
        "Retainers"
      ],
      "Oral Surgery": [
        "Tooth Extraction (Bunot)"
      ],
      "Endodontics": [
        "Root Canal Treatment"
      ],
      "Prosthodontics": [
        "Crowns",
        "Dentures"
      ]
    };

    // Predefined responses for common questions
    const predefinedResponses = {
      "What services do you offer?": `We offer comprehensive dental services including:

<span class="service-category">General Dentistry</span>
<ul class="service-list">
  <li>Oral Prophylaxis</li>
  <li>Fluoride Application</li>
  <li>Pit & Fissure Sealants</li>
  <li>Tooth Restoration (Pasta)</li>
  <li>Root Canal Treatment</li>
</ul>

<span class="service-category">Orthodontics</span>
<ul class="service-list">
  <li>Braces</li>
  <li>Retainers</li>
</ul>

<span class="service-category">Oral Surgery</span>
<ul class="service-list">
  <li>Tooth Extraction (Bunot)</li>
</ul>

<span class="service-category">Endodontics</span>
<ul class="service-list">
  <li>Root Canal Treatment</li>
</ul>

<span class="service-category">Prosthodontics</span>
<ul class="service-list">
  <li>Crowns</li>
  <li>Dentures</li>
</ul>

Is there a specific service you'd like to know more about?`,

      "How do I book an appointment?": `Booking an appointment is easy! Here's our step-by-step process:

Step 1: Select Service
- Choose from our dental services

Step 2: Select Sub-service
- Pick the specific treatment you need

Step 3: Choose Date & Time
- Select your preferred appointment schedule

Step 4: Payment Method
Choose between:

Digital Payment (GCash/PayMaya):
- Input necessary payment details
- Upload the transaction receipt
- Your slot will be confirmed immediately

Cash Payment:
- Your slot will be placed on HOLD
- You need to pay at the clinic to confirm your appointment
- Payment must be made before your scheduled date

Final Step:
After booking, you'll receive a confirmation via email and SMS once the dentist approves your appointment.

Ready to book your appointment? Visit our booking page or contact us:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com`,

      "What are your opening hours?": `Our clinic hours are:

Monday - Sunday: 8:00 AM - 8:00 PM

We recommend booking appointments in advance.`,

      "Where are you located?": `We're conveniently located at:

Landero Dental Clinic
Anahaw St. Comembo
Taguig City`,

      "Do you accept insurance?": `Yes, we accept most major dental insurance plans including:

Delta Dental
MetLife
Cigna
Aetna
Blue Cross Blue Shield

Please bring your insurance card to your appointment. We also offer flexible payment plans.`,

      "How much is a dental checkup?": `The cost of dental checkups varies based on the specific treatment procedure needed. 

We provide personalized treatment plans and cost estimates after your initial examination. The final price depends on the services required and your individual dental needs.

For accurate pricing information, we recommend scheduling a consultation where we can assess your specific requirements and provide a detailed cost breakdown.`
    };

    // Show chat
    chatToggle.addEventListener('click', () => {
      chatWidget.style.display = 'flex';
      chatToggle.style.display = 'none';
      userInput.focus();
    });

    // Close chat
    closeChat.addEventListener('click', () => {
      chatWidget.style.display = 'none';
      chatToggle.style.display = 'flex';
    });

    // Quick question buttons
    quickQuestionBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const question = btn.getAttribute('data-question');
        userInput.value = question;
        chatForm.dispatchEvent(new Event('submit'));
      });
    });

    // Clear chat history
    clearChatBtn.addEventListener('click', () => {
      if (confirm('Are you sure you want to clear the chat history?')) {
        // Keep only the first bot message (welcome message)
        const messages = chatBox.querySelectorAll('.chat-message');
        messages.forEach((message, index) => {
          if (index > 0) {
            message.remove();
          }
        });
        
        // Re-add quick questions if they were removed
        if (!chatBox.querySelector('.quick-questions')) {
          const welcomeMessage = chatBox.querySelector('.bot-message');
          if (welcomeMessage) {
            const quickQuestionsHTML = `
              <div class="quick-questions">
                <button class="quick-question-btn" data-question="What services do you offer?">
                  <i class="fas fa-teeth"></i> Our Services
                </button>
                <button class="quick-question-btn" data-question="How do I book an appointment?">
                  <i class="fas fa-calendar-check"></i> Book Appointment
                </button>
                <button class="quick-question-btn" data-question="What are your opening hours?">
                  <i class="fas fa-clock"></i> Opening Hours
                </button>
                <button class="quick-question-btn" data-question="Where are you located?">
                  <i class="fas fa-map-marker-alt"></i> Location
                </button>
                <button class="quick-question-btn" data-question="Do you accept insurance?">
                  <i class="fas fa-file-invoice-dollar"></i> Insurance
                </button>
                <button class="quick-question-btn" data-question="How much is a dental checkup?">
                  <i class="fas fa-dollar-sign"></i> Checkup Cost
                </button>
              </div>
            `;
            welcomeMessage.innerHTML += quickQuestionsHTML;
            
            // Re-attach event listeners to new buttons
            document.querySelectorAll('.quick-question-btn').forEach(btn => {
              btn.addEventListener('click', () => {
                const question = btn.getAttribute('data-question');
                userInput.value = question;
                chatForm.dispatchEvent(new Event('submit'));
              });
            });
          }
        }
      }
    });

    // Suggest questions
    suggestQuestionsBtn.addEventListener('click', () => {
      addMessage("Here are more questions you can ask:", 'bot');
      
      const questions = [
        "What is your cancellation policy?",
        "Do you handle dental emergencies?",
        "How long does a typical cleaning take?",
        "Do you offer teeth whitening?",
        "What should I do for a toothache?",
        "How often should I get a dental checkup?"
      ];
      
      setTimeout(() => {
        const questionList = document.createElement('div');
        questionList.classList.add('chat-message', 'bot-message');
        
        let questionsHTML = '<strong>More Questions:</strong><br><br>';
        questions.forEach((q, index) => {
          questionsHTML += `${index + 1}. ${q}<br>`;
        });
        
        questionsHTML += '<br>Click on any question or type your own.';
        questionList.innerHTML = questionsHTML;
        chatBox.appendChild(questionList);
        chatBox.scrollTop = chatBox.scrollHeight;
      }, 500);
    });

    // Send message
    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const messageText = userInput.value.trim();
      if (messageText === '') return;

      addMessage(messageText, 'user');
      userInput.value = '';
      showTypingIndicator();
      processMessage(messageText);
    });

    function addMessage(text, sender) {
      const messageElement = document.createElement('div');
      messageElement.classList.add('chat-message', sender + '-message');
      
      // Remove any quick questions from previous messages when adding new ones
      if (sender === 'user') {
        const quickQuestions = document.querySelectorAll('.quick-questions');
        quickQuestions.forEach(qq => {
          if (qq.parentNode && qq.parentNode !== messageElement) {
            qq.remove();
          }
        });
      }
      
      messageElement.innerHTML = text;
      chatBox.appendChild(messageElement);
      chatBox.scrollTop = chatBox.scrollHeight;
    }

    function showTypingIndicator() {
      if (document.querySelector('.typing-indicator')) return;
      const indicator = document.createElement('div');
      indicator.classList.add('typing-indicator');
      indicator.innerHTML = `
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      `;
      chatBox.appendChild(indicator);
      chatBox.scrollTop = chatBox.scrollHeight;
    }

    function removeTypingIndicator() {
      const indicator = document.querySelector('.typing-indicator');
      if (indicator) indicator.remove();
    }

    function processMessage(message) {
      // Check if we have a predefined response first
      const response = predefinedResponses[message];
      
      if (response) {
        setTimeout(() => {
          removeTypingIndicator();
          addMessage(response, 'bot');
          addFollowUpQuestions();
        }, 1000);
      } else {
        // Send to AI API for processing
        sendToAI(message);
      }
    }

    // New function to handle AI API calls
    async function sendToAI(message) {
      const chatHistory = getChatHistory();
      const payload = { message, history: chatHistory };
      
      let lastErr = null;
      
      for (const apiUrl of apiUrlCandidates) {
        try {
          const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          
          const contentType = response.headers.get('content-type') || '';
          const data = contentType.includes('application/json')
            ? await response.json()
            : { answer: await response.text() };
          
          if (!response.ok) {
            // If this path doesn't exist, try the next candidate
            if (response.status === 404) {
              lastErr = new Error(`API not found at ${apiUrl}`);
              continue;
            }
            // Surface server-provided error message (e.g., missing GEMINI_API_KEY)
            throw new Error(data?.answer || `Request failed (${response.status})`);
          }
          
          removeTypingIndicator();
          
          if (data && data.answer) {
            let cleanedAnswer = data.answer;
            if (cleanedAnswer.length > 100) {
              cleanedAnswer = cleanedAnswer.replace(/\*/g, '');
            }
            addMessage(cleanedAnswer, 'bot');
          } else {
            addMessage("I'm sorry, I didn't get a response. Please try again.", 'bot');
          }
          
          addFollowUpQuestions();
          return;
        } catch (err) {
          lastErr = err;
        }
      }
      
      console.error('Error:', lastErr);
      removeTypingIndicator();
      addMessage(
        (lastErr && lastErr.message)
          ? `Chat service error: ${lastErr.message}`
          : "I'm having trouble connecting right now. You can ask me about our services, hours, location, or contact us directly at:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
        'bot'
      );
      addFollowUpQuestions();
    }

    // Helper function to get chat history for AI context
    function getChatHistory() {
      const messages = chatBox.querySelectorAll('.chat-message');
      const history = [];
      
      messages.forEach(message => {
        if (message.classList.contains('user-message')) {
          history.push({
            role: 'user',
            parts: [{ text: message.textContent.trim() }]
          });
        } else if (message.classList.contains('bot-message') && !message.querySelector('.quick-questions')) {
          // Only add bot messages that aren't just quick questions
          const textContent = message.textContent.trim();
          if (textContent && !textContent.includes('Hello! 👋')) {
            history.push({
              role: 'model',
              parts: [{ text: textContent }]
            });
          }
        }
      });
      
      return history;
    }

    // Helper function to add follow-up questions
    function addFollowUpQuestions() {
      setTimeout(() => {
        const followUpQuestions = document.createElement('div');
        followUpQuestions.classList.add('quick-questions');
        followUpQuestions.innerHTML = `
          <button class="quick-question-btn" data-question="What services do you offer?">
            <i class="fas fa-teeth"></i> Services
          </button>
          <button class="quick-question-btn" data-question="How do I book an appointment?">
            <i class="fas fa-calendar-plus"></i> Book Now
          </button>
          <button class="quick-question-btn" data-question="What are your opening hours?">
            <i class="fas fa-clock"></i> Hours
          </button>
          <button class="quick-question-btn" data-question="Anything else I should know?">
            <i class="fas fa-question-circle"></i> More Info
          </button>
        `;
        
        // Add to the last bot message
        const lastBotMessage = document.querySelector('.bot-message:last-child');
        if (lastBotMessage) {
          lastBotMessage.appendChild(followUpQuestions);
          
          // Re-attach event listeners
          lastBotMessage.querySelectorAll('.quick-question-btn').forEach(btn => {
            btn.addEventListener('click', () => {
              const question = btn.getAttribute('data-question');
              userInput.value = question;
              chatForm.dispatchEvent(new Event('submit'));
            });
          });
        }
      }, 300);
    }

    // Allow Enter key to send message (but Shift+Enter for new line)
    userInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.dispatchEvent(new Event('submit'));
      }
    });

    // Auto-focus on input when chat opens
    document.addEventListener('click', (e) => {
      if (e.target === chatToggle || chatToggle.contains(e.target)) {
        setTimeout(() => userInput.focus(), 300);
      }
    });
  </script>
</body>
</html>