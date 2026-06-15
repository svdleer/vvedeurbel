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

// Auto-extract chat ID from pasted JSON
const chatIdField = document.querySelector('input[name="telegram_chat_id"]');
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
