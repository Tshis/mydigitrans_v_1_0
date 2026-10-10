// public/js/partner/copy_link.js

document.addEventListener('DOMContentLoaded', () => {
    const copyButtons = document.querySelectorAll('.js-dual-copy-trigger');

    copyButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');
            const linkInput = document.getElementById(targetId);

            if (linkInput) {
                linkInput.select();
                linkInput.setSelectionRange(0, 99999); // Sécurité Mobile

                navigator.clipboard.writeText(linkInput.href).then(() => {
                    const originalContent = button.innerHTML;
                    
                    // Animation visuelle de confirmation
                    button.innerHTML = '<i class="fa-solid fa-circle-check"></i> Lien Copié !';
                    
                    setTimeout(() => {
                        button.innerHTML = originalContent;
                    }, 2000);
                });
            }
        });
    });
});
