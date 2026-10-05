// public/js/partner/copy_link.js

document.addEventListener('DOMContentLoaded', () => {
    const copyBtn = document.querySelector('#js-copy-link-btn');
    const linkInput = document.querySelector('#js-referral-link-input');

    if (copyBtn && linkInput) {
        copyBtn.addEventListener('click', () => {
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // Pour mobiles

            navigator.clipboard.writeText(linkInput.value).then(() => {
                const originalContent = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Copié !';
                copyBtn.classList.replace('btn-primary-gradient', 'btn-success-gradient');

                setTimeout(() => {
                    copyBtn.innerHTML = originalContent;
                    copyBtn.classList.replace('btn-success-gradient', 'btn-primary-gradient');
                }, 2000);
            });
        });
    }
});
