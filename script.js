// ===========================
// Theme Toggle
// ===========================

const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

// Check for saved theme preference or default to 'light'
const currentTheme = localStorage.getItem('theme') || 'light';
html.setAttribute('data-theme', currentTheme);

themeToggle.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // Add a subtle animation
    themeToggle.style.transform = 'rotate(360deg)';
    setTimeout(() => {
        themeToggle.style.transform = 'rotate(0deg)';
    }, 300);
});

// ===========================
// Navigation Menu Toggle
// ===========================

const navToggle = document.getElementById('navToggle');
const navMenu = document.getElementById('navMenu');
const navLinks = document.querySelectorAll('.nav-link');

// Toggle mobile menu
navToggle.addEventListener('click', () => {
    navToggle.classList.toggle('active');
    navMenu.classList.toggle('active');
});

// Close mobile menu when a link is clicked
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        navToggle.classList.remove('active');
        navMenu.classList.remove('active');
    });
});

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    const isClickInsideNav = navToggle.contains(e.target) || navMenu.contains(e.target);
    if (!isClickInsideNav && navMenu.classList.contains('active')) {
        navToggle.classList.remove('active');
        navMenu.classList.remove('active');
    }
});

// ===========================
// Copy to Clipboard
// ===========================

function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = button.innerHTML;
        button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        `;

        setTimeout(() => {
            button.innerHTML = originalHTML;
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

const copyBtn = document.getElementById('copyBtn');
const copyBtnFooter = document.getElementById('copyBtnFooter');
const installCommand = document.getElementById('installCommand');

if (copyBtn) {
    copyBtn.addEventListener('click', () => {
        copyToClipboard(installCommand.textContent, copyBtn);
    });
}

if (copyBtnFooter) {
    copyBtnFooter.addEventListener('click', () => {
        const command = copyBtnFooter.previousElementSibling.textContent;
        copyToClipboard(command, copyBtnFooter);
    });
}

// ===========================
// FAQ Accordion
// ===========================

const faqQuestions = document.querySelectorAll('.faq-question');

faqQuestions.forEach(question => {
    question.addEventListener('click', () => {
        const isExpanded = question.getAttribute('aria-expanded') === 'true';

        // Close all other FAQs
        faqQuestions.forEach(q => {
            if (q !== question) {
                q.setAttribute('aria-expanded', 'false');
            }
        });

        // Toggle current FAQ
        question.setAttribute('aria-expanded', !isExpanded);
    });
});

// ===========================
// Command Groups Accordion
// ===========================

const commandGroupHeaders = document.querySelectorAll('.command-group-header');

commandGroupHeaders.forEach(header => {
    header.addEventListener('click', () => {
        const isExpanded = header.getAttribute('aria-expanded') === 'true';

        // Toggle current command group
        header.setAttribute('aria-expanded', !isExpanded);
    });

    // Keyboard navigation
    header.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            header.click();
        }
    });
});

// ===========================
// Smooth Scroll
// ===========================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');

        // Only prevent default for internal links
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();

            const target = document.querySelector(href);
            const offsetTop = target.offsetTop - 80;

            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
        }
    });
});

// ===========================
// Intersection Observer for Animations
// ===========================

const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe feature cards
document.querySelectorAll('.feature-card').forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = `all 0.6s ease-out ${index * 0.1}s`;
    observer.observe(card);
});

// Observe steps
document.querySelectorAll('.step').forEach((step, index) => {
    step.style.opacity = '0';
    step.style.transform = 'translateY(30px)';
    step.style.transition = `all 0.6s ease-out ${index * 0.2}s`;
    observer.observe(step);
});

// ===========================
// Parallax Effect for Gradient Orbs
// ===========================

let mouseX = 0;
let mouseY = 0;
let currentX = 0;
let currentY = 0;

document.addEventListener('mousemove', (e) => {
    mouseX = e.clientX / window.innerWidth - 0.5;
    mouseY = e.clientY / window.innerHeight - 0.5;
});

function animateOrbs() {
    currentX += (mouseX - currentX) * 0.05;
    currentY += (mouseY - currentY) * 0.05;

    const orbs = document.querySelectorAll('.gradient-orb');
    orbs.forEach((orb, index) => {
        const speed = (index + 1) * 20;
        orb.style.transform = `translate(${currentX * speed}px, ${currentY * speed}px)`;
    });

    requestAnimationFrame(animateOrbs);
}

animateOrbs();

// ===========================
// Add Scroll Progress Indicator
// ===========================

const progressBar = document.createElement('div');
progressBar.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    z-index: 9999;
    transition: width 0.1s ease-out;
`;
document.body.appendChild(progressBar);

window.addEventListener('scroll', () => {
    const windowHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    const scrolled = (window.scrollY / windowHeight) * 100;
    progressBar.style.width = scrolled + '%';
});

// ===========================
// Add Active State to Theme Toggle on Scroll
// ===========================

let lastScrollTop = 0;
const themeToggleBtn = document.getElementById('themeToggle');

window.addEventListener('scroll', () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    if (scrollTop > lastScrollTop && scrollTop > 100) {
        // Scrolling down
        themeToggleBtn.style.transform = 'scale(0.9)';
    } else {
        // Scrolling up
        themeToggleBtn.style.transform = 'scale(1)';
    }

    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
}, false);

// ===========================
// Keyboard Navigation for FAQ
// ===========================

faqQuestions.forEach((question, index) => {
    question.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            question.click();
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = faqQuestions[index + 1];
            if (next) next.focus();
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = faqQuestions[index - 1];
            if (prev) prev.focus();
        }
    });
});

// ===========================
// Add Loading Animation
// ===========================

window.addEventListener('load', () => {
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease-in';
        document.body.style.opacity = '1';
    }, 100);
});

// ===========================
// Easter Egg: Konami Code
// ===========================

let konamiCode = [];
const konamiSequence = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];

document.addEventListener('keydown', (e) => {
    konamiCode.push(e.key);
    konamiCode = konamiCode.slice(-10);

    if (konamiCode.join('') === konamiSequence.join('')) {
        // Easter egg activated!
        const orbs = document.querySelectorAll('.gradient-orb');
        orbs.forEach(orb => {
            orb.style.animation = 'float 2s ease-in-out infinite';
        });

        // Show a fun message
        const message = document.createElement('div');
        message.textContent = '🎉 Tyro Power Activated! 🎉';
        message.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 3rem;
            border-radius: 1rem;
            font-size: 1.5rem;
            font-weight: 900;
            z-index: 10000;
            animation: slideUp 0.5s ease-out;
        `;
        document.body.appendChild(message);

        setTimeout(() => {
            message.style.animation = 'slideDown 0.5s ease-out';
            setTimeout(() => message.remove(), 500);
        }, 2000);

        konamiCode = [];
    }
});

// ===========================
// Social Share Functions
// ===========================

function shareOnTwitter() {
    const text = "🔐 Just discovered Tyro - the ultimate Auth, Roles & Privileges package for Laravel 12! 40+ CLI commands, 7 Blade directives, and complete RBAC out of the box. Check it out! #Laravel #PHP #WebDev";
    const url = "https://github.com/hasinhayder/tyro";
    window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`, '_blank', 'width=550,height=420');
}

function shareOnLinkedIn() {
    const url = "https://github.com/hasinhayder/tyro";
    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`, '_blank', 'width=550,height=420');
}

// ===========================
// Blade Directives Tabs
// ===========================

const directiveTabs = document.querySelectorAll('.directive-tab');
const directivePanels = document.querySelectorAll('.directive-panel');

directiveTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const directive = tab.getAttribute('data-directive');

        // Remove active class from all tabs
        directiveTabs.forEach(t => t.classList.remove('active'));

        // Remove active class from all panels
        directivePanels.forEach(p => p.classList.remove('active'));

        // Add active class to clicked tab
        tab.classList.add('active');

        // Show corresponding panel
        const panel = document.getElementById(`directive-${directive}`);
        if (panel) {
            panel.classList.add('active');
        }
    });

    // Keyboard navigation
    tab.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            tab.click();
        }
    });
});

// ===========================
// Middleware Tabs
// ===========================

const middlewareTabs = document.querySelectorAll('.middleware-tab');
const middlewarePanels = document.querySelectorAll('.middleware-panel');

middlewareTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const middleware = tab.getAttribute('data-middleware');

        // Remove active class from all tabs
        middlewareTabs.forEach(t => t.classList.remove('active'));

        // Remove active class from all panels
        middlewarePanels.forEach(p => p.classList.remove('active'));

        // Add active class to clicked tab
        tab.classList.add('active');

        // Show corresponding panel
        const panel = document.getElementById(`middleware-${middleware}`);
        if (panel) {
            panel.classList.add('active');
            // Re-highlight code blocks in the newly visible panel
            panel.querySelectorAll('pre code').forEach(block => {
                if (window.hljs) {
                    window.hljs.highlightElement(block);
                }
            });
        }
    });

    // Keyboard navigation
    tab.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            tab.click();
        }
    });
});



// ===========================
// Syntax Highlighting (highlight.js)
// ===========================

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('pre code').forEach(block => {
        if (window.hljs) {
            window.hljs.highlightElement(block);
        }
    });
});
