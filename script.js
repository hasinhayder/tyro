// ===========================
// Theme Toggle
// ===========================

const themeToggle = document.getElementById('themeToggle');
const spotlight = document.getElementById('spotlight');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const navbarLinks = document.getElementById('navbarLinks');

// Initialize Theme
const savedTheme = localStorage.getItem('tyro-landing-theme');
if (savedTheme) { 
    document.documentElement.setAttribute('data-theme', savedTheme); 
}

// Toggle Theme
themeToggle?.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('tyro-landing-theme', newTheme);
});

// ===========================
// Spotlight
// ===========================

document.addEventListener('mousemove', (event) => {
    if (!spotlight) return;
    spotlight.style.display = 'block';
    spotlight.style.left = `${event.clientX}px`;
    spotlight.style.top = `${event.clientY}px`;
});

// ===========================
// Mobile Menu
// ===========================

mobileMenuBtn?.addEventListener('click', () => {
    navbarLinks?.classList.toggle('open');
});

navbarLinks?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
        navbarLinks.classList.remove('open');
    });
});

// ===========================
// Copy to Clipboard
// ===========================

function copyToClipboard(btn, text) {
    navigator.clipboard.writeText(text).then(() => {
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        btn.style.color = '#22c55e';

        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.style.color = '';
        }, 2000);
    });
}

// ===========================
// Code Block Tabs
// ===========================

document.querySelectorAll('.code-block-bar-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        const container = tab.closest('.code-block');

        // Find all tabs in this code block
        container.querySelectorAll('.code-block-bar-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Handle both tab structures:
        const tabsWrapper = container.querySelector('.code-block-tabs-wrapper');
        if (tabsWrapper) {
            tabsWrapper.querySelectorAll('.code-tab-content, .code-block-content').forEach(c => c.classList.remove('active'));
            const targetEl = tabsWrapper.querySelector(`#${target}`);
            if (targetEl) targetEl.classList.add('active');
        } else {
            container.querySelectorAll('.code-tab-content').forEach(c => c.classList.remove('active'));
            container.querySelector(`#${target}`)?.classList.add('active');
        }
    });
});

// ===========================
// Scroll Reveal
// ===========================

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ===========================
// Smooth Scroll
// ===========================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            const target = document.querySelector(href);
            const offsetTop = target.offsetTop - 80;
            window.scrollTo({ top: offsetTop, behavior: 'smooth' });
        }
    });
});