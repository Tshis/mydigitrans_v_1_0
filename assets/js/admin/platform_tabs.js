// public/js/admin/platform_tabs.js

document.addEventListener('DOMContentLoaded', () => {
    const tabsButtons = document.querySelectorAll('.tab-nav-btn');
    const tabsPanels = document.querySelectorAll('.tab-panel-content');

    tabsButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');

            tabsButtons.forEach(btn => btn.classList.remove('active'));
            tabsPanels.forEach(panel => panel.classList.remove('active'));

            button.classList.add('active');
            const targetPanel = document.querySelector(`#${targetId}`);
            if (targetPanel) targetPanel.classList.add('active');
        });
    });
});
