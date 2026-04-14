const adminContainer = document.getElementById('adminContainer');
const notifOverlay = document.getElementById('notif-overlay');
const collapseBtn = document.getElementById('collapseBtn');
const collapseMobile = document.getElementById('collapseMobile');
const darkToggle = document.getElementById('darkToggle');
const mobileToggle = document.getElementById('mobileToggle');
const sidebar = document.getElementById('sidebar');
const notifToggle = document.getElementById('notifToggle');
const notifDropdown = document.getElementById('notifDropdown');
const topbar = document.querySelector('.topbar');
const main = document.querySelector('.main-wrapper');

/* COLLAPSE SIDEBAR */
collapseBtn?.addEventListener('click', () => {
    adminContainer.classList.toggle('collapsed');
});

/* COLLAPSE SIDEBAR */
collapseMobile?.addEventListener('click', () => {
    sidebar.classList.toggle('mobile-active');;
});

/* MOBILE SIDEBAR */
mobileToggle?.addEventListener('click', () => {
    sidebar.classList.toggle('mobile-active');
});

/* DARK MODE */
if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark');
}

darkToggle?.addEventListener('click', () => {
    document.body.classList.toggle('dark');

    localStorage.setItem(
        'darkMode',
        document.body.classList.contains('dark') ? 'enabled' : 'disabled'
    );
});

/* NOTIFICATIONS */
notifToggle?.addEventListener('click', notifToggleActive);
notifOverlay?.addEventListener('click', notifToggleActive);

console.log(main)

function notifToggleActive(params) {
    notifDropdown.classList.toggle('active');
    notifOverlay.classList.toggle('active'); 
}


/* STICKY SHADOW */
main?.addEventListener('scroll', () => {
    if (main.scrollTop > 10) {
        topbar.classList.add('scrolled');
    } else {
        topbar.classList.remove('scrolled');
    }
});