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
