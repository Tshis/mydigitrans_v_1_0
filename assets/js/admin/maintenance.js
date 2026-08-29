document.addEventListener('DOMContentLoaded', () => {
    const modalWrapper = document.getElementById('wrapper-modal');
    const confirm = document.getElementById('confirm');
    const cancelButtons = document.querySelectorAll('.js-modal');

    function modalToggle(){
        modalWrapper.classList.toggle('active');
    } 
    
    cancelButtons.forEach((cancelBtn)=>{
        cancelBtn.addEventListener("click",(e)=>{
            modalToggle();
            confirm.setAttribute('href',e.target.dataset.href)
        })
    })

    modalWrapper.addEventListener('click',modalToggle);
})