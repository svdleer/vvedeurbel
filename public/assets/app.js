const channelSelect = document.querySelector('[data-channel-select]');
if (channelSelect) {
    const telegramRow = document.querySelector('[data-channel=telegram]');
    const smsRow = document.querySelector('[data-channel=sms]');
    const pushRow = document.querySelector('[data-channel=push]');

    const refresh = () => {
        const value = channelSelect.value;
        const blocks = [telegramRow, smsRow, pushRow];

        blocks.forEach((block) => {
            if (!block) {
                return;
            }
            block.style.display = block.dataset.channel === value ? '' : 'none';
        });
    };

    channelSelect.addEventListener('change', refresh);
    refresh();
}

// Telegram verification flow
const autoDetectBtn = document.getElementById('auto-detect-telegram-btn');
const verifyBtn = document.getElementById('verify-telegram-btn');
const resetBtn = document.getElementById('telegram-reset-btn');
const chatIdField = document.getElementById('telegram_chat_id_field');
const chatIdDisplay = document.getElementById('telegram_chat_id_display');
const stepDetect = document.getElementById('telegram-step-detect');
const stepVerify = document.getElementById('telegram-step-verify');
const stepDone = document.getElementById('telegram-step-done');
const detectedLabel = document.getElementById('detected-chat-id-label');
const verifiedLabel = document.getElementById('verified-chat-id-label');
const codeInput = document.getElementById('telegram-verify-code');

const smsSendBtn = document.getElementById('sms-send-code-btn');
const smsVerifyBtn = document.getElementById('sms-verify-btn');
const smsResetBtn = document.getElementById('sms-reset-btn');
const smsPhoneInput = document.getElementById('sms-phone-input');
const smsPhoneField = document.getElementById('sms_phone_number_field');
const smsPhoneDisplay = document.getElementById('sms_phone_number_display');
const smsStepSend = document.getElementById('sms-step-send');
const smsStepVerify = document.getElementById('sms-step-verify');
const smsStepDone = document.getElementById('sms-step-done');
const smsDetectedLabel = document.getElementById('sms-detected-label');
const smsVerifiedLabel = document.getElementById('sms-verified-label');
const smsCodeInput = document.getElementById('sms-verify-code');

let detectedChatId = null;
let detectedSmsPhone = null;

if (autoDetectBtn) {
    autoDetectBtn.addEventListener('click', async () => {
        autoDetectBtn.disabled = true;
        autoDetectBtn.textContent = '⏳ Detecteren...';

        try {
            const res = await fetch('/api/telegram_get_id.php');
            const data = await res.json();

            if (!data.ok) {
                alert('Fout: ' + (data.error || 'Onbekende fout'));
                autoDetectBtn.disabled = false;
                autoDetectBtn.textContent = '🔍 Stap 1: Detecteer mijn chat ID';
                return;
            }

            detectedChatId = String(data.chat_id);

            // Stuur verificatiecode
            const sendRes = await fetch('/api/telegram_send_code.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({chat_id: detectedChatId}),
            });
            const sendData = await sendRes.json();

            if (!sendData.ok) {
                alert('Fout bij versturen code: ' + (sendData.error || 'Onbekende fout'));
                autoDetectBtn.disabled = false;
                autoDetectBtn.textContent = '🔍 Stap 1: Detecteer mijn chat ID';
                return;
            }

            detectedLabel.textContent = detectedChatId;
            stepDetect.style.display = 'none';
            stepVerify.style.display = 'block';
            codeInput.focus();
        } catch (err) {
            alert('Fout: ' + err.message);
            autoDetectBtn.disabled = false;
            autoDetectBtn.textContent = '🔍 Stap 1: Detecteer mijn chat ID';
        }
    });
}

if (verifyBtn) {
    verifyBtn.addEventListener('click', async () => {
        const code = codeInput.value.trim();
        if (code.length !== 6) {
            alert('Voer de 6-cijferige code in die je via Telegram hebt ontvangen.');
            return;
        }

        verifyBtn.disabled = true;
        verifyBtn.textContent = '⏳ Verifiëren...';

        try {
            const res = await fetch('/api/telegram_verify_code.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({chat_id: detectedChatId, code}),
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Fout: ' + (data.error || 'Ongeldige code'));
                verifyBtn.disabled = false;
                verifyBtn.textContent = '✓ Verifieer';
                return;
            }

            // Succes: vul hidden field in, toon leesbaar veld, toon bevestiging
            chatIdField.value = detectedChatId;
            if (chatIdDisplay) {
                chatIdDisplay.value = detectedChatId;
                chatIdDisplay.style.display = '';
            }
            verifiedLabel.textContent = detectedChatId;
            stepVerify.style.display = 'none';
            stepDone.style.display = 'block';
        } catch (err) {
            alert('Fout: ' + err.message);
            verifyBtn.disabled = false;
            verifyBtn.textContent = '✓ Verifieer';
        }
    });
}

if (resetBtn) {
    resetBtn.addEventListener('click', () => {
        detectedChatId = null;
        chatIdField.value = '';
        if (chatIdDisplay) {
            chatIdDisplay.value = '';
            chatIdDisplay.style.display = 'none';
        }
        codeInput.value = '';
        stepDone.style.display = 'none';
        stepVerify.style.display = 'none';
        stepDetect.style.display = 'block';
        autoDetectBtn.disabled = false;
        autoDetectBtn.textContent = '🔍 Stap 1: Detecteer mijn chat ID';
    });
}

if (smsSendBtn) {
    smsSendBtn.addEventListener('click', async () => {
        const phone = (smsPhoneInput?.value || '').trim();
        if (!/^\+\d{8,15}$/.test(phone)) {
            alert('Gebruik E.164 formaat, bijvoorbeeld +31612345678.');
            return;
        }

        smsSendBtn.disabled = true;
        smsSendBtn.textContent = '⏳ Versturen...';

        try {
            const res = await fetch('/api/sms_send_code.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({phone_number: phone}),
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Fout: ' + (data.error || 'Onbekende fout'));
                smsSendBtn.disabled = false;
                smsSendBtn.textContent = '📩 SMS-code verzenden';
                return;
            }

            detectedSmsPhone = phone;
            smsDetectedLabel.textContent = phone;
            smsStepSend.style.display = 'none';
            smsStepVerify.style.display = 'block';
            smsCodeInput.focus();
        } catch (err) {
            alert('Fout: ' + err.message);
        } finally {
            smsSendBtn.disabled = false;
            smsSendBtn.textContent = '📩 SMS-code verzenden';
        }
    });
}

if (smsVerifyBtn) {
    smsVerifyBtn.addEventListener('click', async () => {
        const code = (smsCodeInput?.value || '').trim();
        if (code.length !== 6) {
            alert('Voer de 6-cijferige SMS-code in.');
            return;
        }

        smsVerifyBtn.disabled = true;
        smsVerifyBtn.textContent = '⏳ Verifiëren...';

        try {
            const res = await fetch('/api/sms_verify_code.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({phone_number: detectedSmsPhone, code}),
            });
            const data = await res.json();

            if (!data.ok) {
                alert('Fout: ' + (data.error || 'Ongeldige code'));
                smsVerifyBtn.disabled = false;
                smsVerifyBtn.textContent = '✓ SMS-code verifiëren';
                return;
            }

            smsPhoneField.value = detectedSmsPhone;
            if (smsPhoneDisplay) {
                smsPhoneDisplay.value = detectedSmsPhone;
                smsPhoneDisplay.style.display = '';
            }
            smsVerifiedLabel.textContent = detectedSmsPhone;
            smsStepVerify.style.display = 'none';
            smsStepDone.style.display = 'block';
        } catch (err) {
            alert('Fout: ' + err.message);
        } finally {
            smsVerifyBtn.disabled = false;
            smsVerifyBtn.textContent = '✓ SMS-code verifiëren';
        }
    });
}

if (smsResetBtn) {
    smsResetBtn.addEventListener('click', () => {
        detectedSmsPhone = null;
        smsPhoneField.value = '';
        if (smsPhoneDisplay) {
            smsPhoneDisplay.value = '';
            smsPhoneDisplay.style.display = 'none';
        }
        if (smsPhoneInput) smsPhoneInput.value = '';
        if (smsCodeInput) smsCodeInput.value = '';
        smsStepDone.style.display = 'none';
        smsStepVerify.style.display = 'none';
        smsStepSend.style.display = 'block';
        smsSendBtn.disabled = false;
        smsSendBtn.textContent = '📩 SMS-code verzenden';
    });
}

// Auto-extract chat ID from pasted JSON (fallback)
if (codeInput) {
    codeInput.addEventListener('input', () => {
        codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
    });
}
