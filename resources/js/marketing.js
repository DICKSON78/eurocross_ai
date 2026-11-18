// EuroCross marketing interactions — intentionally small and dependency-free.
document.documentElement.classList.add('js');

const menu = document.querySelector('[data-ec-menu]');
const openButtons = document.querySelectorAll('[data-ec-menu-open]');
const closeButtons = document.querySelectorAll('[data-ec-menu-close]');

function setMenu(open) {
    if (!menu) return;
    menu.classList.toggle('hidden', !open);
    menu.setAttribute('aria-hidden', String(!open));
    openButtons.forEach((button) => button.setAttribute('aria-expanded', String(open)));
    document.body.style.overflow = open ? 'hidden' : '';
}

openButtons.forEach((button) => button.addEventListener('click', () => setMenu(true)));
closeButtons.forEach((button) => button.addEventListener('click', () => setMenu(false)));
menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenu(false);
});

const reveals = document.querySelectorAll('.ec-scroll-reveal');

if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-shown');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    reveals.forEach((element) => observer.observe(element));
} else {
    reveals.forEach((element) => element.classList.add('is-shown'));
}
