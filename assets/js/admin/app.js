const adminContainer = document.getElementById('adminContainer');
const collapseBtn = document.getElementById('collapseBtn');
const darkToggle = document.getElementById('darkToggle');
const mobileToggle = document.getElementById('mobileToggle');
const sidebar = document.getElementById('sidebar');
const notifToggle = document.getElementById('notifToggle');
const notifDropdown = document.getElementById('notifDropdown');
const topbar = document.querySelector('.topbar');


/* COLLAPSE SIDEBAR */
collapseBtn?.addEventListener('click', () => {
    adminContainer.classList.toggle('collapsed');
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
notifToggle?.addEventListener('click', () => {
    notifDropdown.classList.toggle('active');
});

/* STICKY SHADOW */
window.addEventListener('scroll', () => {
    if (window.scrollY > 10) {
        topbar.classList.add('scrolled');
    } else {
        topbar.classList.remove('scrolled');
    }
});