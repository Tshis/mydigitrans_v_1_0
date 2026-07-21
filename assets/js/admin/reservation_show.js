document.addEventListener('DOMContentLoaded', () => {
    const cancelBtn = document.getElementById('cancelBtn');
    const cancelBtn2 = document.getElementById('cancel2');
    const cancelBox = document.getElementById('cancel-reason');

    if (!cancelBtn) return;
    if (!cancelBox) return;

    cancelBtn.addEventListener('click',cancelBoxToggle);
    cancelBtn2.addEventListener('click',cancelBoxToggle);

    function cancelBoxToggle() {
        cancelBox.classList.toggle('active')
    }
});