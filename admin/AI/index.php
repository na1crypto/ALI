<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../../middleware/admin_check.php"; 
require_once "../../config/dokon_db.php"; 

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "SMART POS";
$admin_name = $_SESSION['name'] ?? "Rahbar";
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Tahlilchi | <?= htmlspecialchars($site_name) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <style>
        * { box-sizing: border-box; }
        
        body { 
            background: #F4F7FE; 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow: hidden; 
            height: 100vh;
            /* DIQQAT: padding-left: 280px sidebar.php orqali keladi */
        }

        /* 🔥 ASOSIY MAYDON: Qolgan ochiq maydonni aniq markazlashtiradi */
        .main-content { 
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;    /* 🎯 GORIZONTAL MARKAZ */
            justify-content: center; /* 🎯 VERTIKAL MARKAZ */
            padding: 20px;
        }
        
        /* CHAT QUTISI */
        .chat-container { 
            width: 100%;
            max-width: 900px; 
            height: 95vh;
            display: flex; 
            flex-direction: column; 
        }
        
        /* TEPADAGI SHAPKA */
        .chat-header {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 15px 30px;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(17, 28, 68, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        /* XABARLAR QUTISI */
        .chat-box { 
            flex: 1; 
            background: #FFFFFF; 
            border-radius: 24px; 
            padding: 25px; 
            overflow-y: auto; 
            box-shadow: 0 10px 30px rgba(17, 28, 68, 0.03);
            display: flex; 
            flex-direction: column;
            gap: 20px;
            margin-bottom: 15px;
            scroll-behavior: smooth;
        }
        
        .msg-wrapper { display: flex; width: 100%; }
        .msg-wrapper.ai { justify-content: flex-start; }
        .msg-wrapper.user { justify-content: flex-end; }
        
        .msg { max-width: 80%; padding: 15px 20px; border-radius: 18px; font-size: 15px; line-height: 1.6; }
        .ai-msg { background: #F4F7FE; color: #111C44; border-radius: 0 18px 18px 18px; }
        .user-msg { background: linear-gradient(135deg, #111C44 0%, #4318FF 100%); color: #FFFFFF; border-radius: 18px 18px 0 18px; }

        .ai-msg strong { color: #2B3674; font-weight: 700; }
        .ai-msg table { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .ai-msg th { background: #E9EDF7; padding: 10px; text-align: left; font-size: 13px; color: #A3AED0; }
        .ai-msg td { border-bottom: 1px solid #E9EDF7; padding: 10px; font-size: 14px; }
        .ai-msg p:last-child { margin-bottom: 0; }

        /* YOZISH QISMI */
        .input-area { 
            background: #FFFFFF; 
            padding: 8px 12px; 
            border-radius: 20px; 
            display: flex;
            align-items: center;
            box-shadow: 0 10px 30px rgba(17, 28, 68, 0.03);
            flex-shrink: 0;
            border: 1px solid #E9EDF7;
        }
        .chat-input { border: none; outline: none; padding: 10px 15px; font-size: 15px; flex: 1; background: transparent; color: #111C44; }
        .chat-input::placeholder { color: #A3AED0; }
        
        .send-btn { 
            background: #4318FF; color: white; border: none; border-radius: 14px; 
            width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; transition: 0.2s; 
        }
        .send-btn:hover { background: #2B3674; transform: scale(0.95); }

        .typing-indicator { display: flex; gap: 5px; padding: 5px; }
        .typing-dot { width: 8px; height: 8px; background: #A3AED0; border-radius: 50%; animation: typing 1.4s infinite ease-in-out; }
        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); background: #4318FF; } }

        @media (max-width: 992px) {
            .main-content { padding: 10px; height: calc(100vh - 60px); }
            .chat-container { height: 100%; }
        }
    </style>
</head>
<body>

    <?php 
        $active_menu = 'ai'; 
        include "../includes/sidebar.php"; 
    ?>

    <div class="main-content">
        <div class="chat-container">
            
            <div class="chat-header">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 45px; height: 45px; border-radius: 12px; background: #F4F7FE; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-brain" style="color: #4318FF; font-size: 20px;"></i>
                    </div>
                    <div>
                        <h5 style="margin: 0; font-weight: 800; color: #2B3674; font-size: 18px;"><?= htmlspecialchars($site_name) ?> AI</h5>
                        <small style="color: #05CD99; font-weight: 600;"><i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>Online</small>
                    </div>
                </div>
                <div style="color: #A3AED0; font-size: 13px; font-weight: 500;">
                    <i class="fas fa-shield-alt mr-1"></i> Xavfsiz ulanish
                </div>
            </div>
            
            <div class="chat-box" id="chat_display">
                <div class="msg-wrapper ai">
                    <div class="msg ai-msg">
                        Assalomu alaykum <strong><?= htmlspecialchars($admin_name) ?></strong>! 👋<br><br>
                        Men <strong><?= htmlspecialchars($site_name) ?></strong> do'konining aqlli tahlilchisiman. Savdo, ombor va moliyaviy hisobotlar bo'yicha qanday savolingiz bor?
                    </div>
                </div>
            </div>

            <div class="input-area">
                <input type="text" id="user_text" class="chat-input" placeholder="Savolingizni yozing..." autocomplete="off">
                <button class="send-btn" id="btn_send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    $(document).ready(function() {
        function sendMessage() {
            let msg = $('#user_text').val();
            if(msg.trim() === "") return;
            
            $('#chat_display').append(`<div class="msg-wrapper user"><div class="msg user-msg">${msg}</div></div>`);
            $('#user_text').val('');
            $('#chat_display').scrollTop($('#chat_display')[0].scrollHeight);
            
            let typingId = 'typing_' + Date.now();
            $('#chat_display').append(`
                <div class="msg-wrapper ai" id="${typingId}">
                    <div class="msg ai-msg" style="padding: 18px 24px;">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
            `);
            $('#chat_display').scrollTop($('#chat_display')[0].scrollHeight);

            $.ajax({
                url: 'ai_engine.php',
                method: 'POST',
                data: { message: msg },
                success: function(res) {
                    $('#'+typingId).remove(); 
                    let parsedHTML = marked.parse(res); 
                    $('#chat_display').append(`<div class="msg-wrapper ai"><div class="msg ai-msg">${parsedHTML}</div></div>`);
                    $('#chat_display').scrollTop($('#chat_display')[0].scrollHeight);
                },
                error: function() {
                    $('#'+typingId).remove();
                    $('#chat_display').append(`
                        <div class="msg-wrapper ai">
                            <div class="msg ai-msg" style="color: #EE5D50; border: 1px solid #FEECEF; background: #FEECEF;">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Tarmoq yoki server xatosi yuz berdi.
                            </div>
                        </div>
                    `);
                    $('#chat_display').scrollTop($('#chat_display')[0].scrollHeight);
                }
            });
        }
        
        $('#btn_send').click(sendMessage);
        $('#user_text').keypress(function(e) { if(e.which == 13) sendMessage(); });
    });
</script>
</body>
</html>