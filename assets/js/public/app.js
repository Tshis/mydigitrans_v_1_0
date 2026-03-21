const navToggle = document.querySelector('.nav-toggle');
//const mobileMenu = document.querySelector('.mobile-menu');
const overlayDark = document.querySelector('.overlay-dark');

function toggleMenu() {
    navToggle.classList.toggle('active');
    mobileMenu.classList.toggle('active');
    overlayDark.classList.toggle('active');
}//toggleMenu

navToggle.addEventListener("click",toggleMenu);
//mobileMenu.addEventListener("click",toggleMenu);
overlayDark.addEventListener("click",toggleMenu);