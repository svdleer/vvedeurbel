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
            block.style.display = block.dataset.channel === value ? 'grid' : 'none';
        });
    };

    channelSelect.addEventListener('change', refresh);
    refresh();
}

// Auto-detect Telegram chat ID in registration
const autoDetectBtn = document.getElementById('auto-detect-telegram-btn');
const chatIdField = document.querySelector('input[name="telegram_chat_id"]');

if (autoDetectBtn && chatIdField) {
    autoDetectBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const originalText = autoDetectBtn.textContent;
        autoDetectBtn.disabled = true;
        autoDetectBtn.textContent = '⏳ Een moment...';

        try {
            const res = await fetch('/api/telegram_get_id.php');
            const data = await res.json();

            if (data.ok && data.chat_id) {
                chatIdField.value = data.chat_id;
                autoDetectBtn.textContent = '✓ Gevonden!';
                setTimeout(() => {
                    autoDetectBtn.textContent = originalText;
                    autoDetectBtn.disabled = false;
                }, 2000);
            } else {
                alert('Fout: ' + (data.error || 'Onbekende fout'));
                autoDetectBtn.textContent = originalText;
                autoDetectBtn.disabled = false;
            }
        } catch (err) {
            alert('Fout: ' + err.message);
            autoDetectBtn.textContent = originalText;
            autoDetectBtn.disabled = false;
        }
    });
}

// Auto-extract chat ID from pasted JSON (fallback)
if (chatIdField) {
    chatIdField.addEventListener('paste', (e) => {
        setTimeout(() => {
            const value = chatIdField.value;
            const match = value.match(/[0-9]{8,}/);
            if (match) {
                chatIdField.value = match[0];
            }
        }, 10);
    });
}
