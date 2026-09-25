
const ctaBtns = document.querySelectorAll('#ctaBtn');
const questionModal = document.querySelector('.question-modal');
const question = document.querySelector('.question');
const confirm_link = document.querySelector('#confirm-link');

question.addEventListener('click',function(e){
    e.stopPropagation();;
})

questionModal.addEventListener('click',toggleModalQuestion)

function toggleModalQuestion(){
    questionModal.classList.toggle('active');
}


if (ctaBtns !== undefined) {
    ctaBtns.forEach((ctaBtn) => {
        ctaBtn.addEventListener('click',()=>{
            let target = ctaBtn.dataset.target;
            confirm_link.setAttribute('href',target);
            toggleModalQuestion();
        });
    });
}