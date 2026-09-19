<?php
require_once 'config/config.php';
require_once 'config/firebase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!FIREBASE_PHONE_AUTH_ENABLED || !isset($_SESSION['google_user_data'])) {
    header('Location: index.php');
    exit;
}

$userEmail = $_SESSION['google_user_data']['email'] ?? '';
$userName = $_SESSION['google_user_data']['name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Mobile Number - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1rem;
            background: linear-gradient(rgba(15, 23, 42, 0.82), rgba(15, 23, 42, 0.9)), url('images/villasoledadbg.png') center/cover fixed;
        }

        .phone-auth-card {
            width: min(100%, 440px);
            padding: 2.25rem;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
        }

        .phone-auth-card h1 {
            margin: 0 0 0.5rem;
            color: #1e3a8a;
            font-size: 1.8rem;
        }

        .phone-auth-card p {
            color: #64748b;
            line-height: 1.5;
        }

        .phone-auth-card label {
            display: block;
            margin: 1.5rem 0 0.5rem;
            color: #334155;
            font-weight: 600;
        }

        .phone-auth-card input {
            width: 100%;
            padding: 0.8rem 0.9rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        .phone-auth-card input:focus {
            outline: none;
            border-color: #f97316;
        }

        .phone-auth-card button {
            width: 100%;
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            border: 0;
            border-radius: 8px;
            background: #f97316;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        .phone-auth-card button:disabled {
            opacity: 0.6;
            cursor: wait;
        }

        #recaptcha-container {
            margin-top: 1rem;
        }

        .message {
            margin-top: 1rem;
            color: #b91c1c;
        }

        .message.success {
            color: #047857;
        }
    </style>
</head>
<body>
    <main class="phone-auth-card">
        <h1>Verify your mobile number</h1>
        <p>Hi <?php echo htmlspecialchars($userName); ?>. Enter your mobile number to finish signing in with <?php echo htmlspecialchars($userEmail); ?>.</p>

        <label for="phone-number">Mobile number</label>
        <input type="tel" id="phone-number" placeholder="+639XXXXXXXXX" autocomplete="tel" inputmode="tel">
        <div id="recaptcha-container"></div>
        <button type="button" id="send-code-button">Send SMS code</button>

        <div id="code-section" hidden>
            <label for="verification-code">Verification code</label>
            <input type="text" id="verification-code" placeholder="Enter the 6-digit code" autocomplete="one-time-code" inputmode="numeric" maxlength="6">
            <button type="button" id="verify-code-button">Verify mobile number</button>
        </div>

        <p id="message" class="message" role="status"></p>
    </main>

    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-auth-compat.js"></script>
    <script>
        const firebaseConfig = <?php echo json_encode([
            'apiKey' => FIREBASE_API_KEY,
            'authDomain' => FIREBASE_AUTH_DOMAIN,
            'projectId' => FIREBASE_PROJECT_ID,
            'storageBucket' => FIREBASE_STORAGE_BUCKET,
            'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
            'appId' => FIREBASE_APP_ID,
            'measurementId' => FIREBASE_MEASUREMENT_ID
        ], JSON_UNESCAPED_SLASHES); ?>;

        firebase.initializeApp(firebaseConfig);
        const auth = firebase.auth();
        const sendCodeButton = document.getElementById('send-code-button');
        const verifyCodeButton = document.getElementById('verify-code-button');
        const phoneInput = document.getElementById('phone-number');
        const codeInput = document.getElementById('verification-code');
        const codeSection = document.getElementById('code-section');
        const message = document.getElementById('message');
        let confirmationResult;

        const recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
            size: 'normal'
        });
        recaptchaVerifier.render();

        function setMessage(text, success = false) {
            message.textContent = text;
            message.classList.toggle('success', success);
        }

        sendCodeButton.addEventListener('click', async function() {
            const phoneNumber = phoneInput.value.trim();
            if (!/^\+[1-9]\d{7,14}$/.test(phoneNumber)) {
                setMessage('Enter a valid number in international format, for example +639XXXXXXXXX.');
                return;
            }

            sendCodeButton.disabled = true;
            setMessage('Sending verification code...');
            try {
                confirmationResult = await auth.signInWithPhoneNumber(phoneNumber, recaptchaVerifier);
                codeSection.hidden = false;
                setMessage('The verification code was sent by SMS.', true);
                codeInput.focus();
            } catch (error) {
                setMessage(error.message || 'Unable to send the SMS code.');
                recaptchaVerifier.clear();
            } finally {
                sendCodeButton.disabled = false;
            }
        });

        verifyCodeButton.addEventListener('click', async function() {
            const verificationCode = codeInput.value.trim();
            if (!/^\d{6}$/.test(verificationCode) || !confirmationResult) {
                setMessage('Enter the 6-digit verification code.');
                return;
            }

            verifyCodeButton.disabled = true;
            setMessage('Verifying mobile number...');
            try {
                const credential = await confirmationResult.confirm(verificationCode);
                const idToken = await credential.user.getIdToken();
                const response = await fetch('api/firebase_verify_phone.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_token: idToken })
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Server verification failed.');
                }

                setMessage('Mobile number verified. Redirecting...', true);
                window.location.href = result.redirect_url || 'index.php';
            } catch (error) {
                setMessage(error.message || 'Unable to verify the mobile number.');
                verifyCodeButton.disabled = false;
            }
        });
    </script>
</body>
</html>
