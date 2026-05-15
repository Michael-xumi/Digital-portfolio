const ACCORDION_TRANSITION_DURATION = 300; // Match CSS transition time

// --- Theme Toggle Logic ---
const htmlElement = document.documentElement;
const toggleButtons = document.querySelectorAll('#theme-toggle-desktop, #theme-toggle-mobile');
const iconIds = ['theme-icon-desktop', 'theme-icon-mobile'];

const sunIcon = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" fill="none"></path><path d="M12 7V3h1v4h-1zM5.63 5.63l.71.71L6.34 7.07 7.05 7.78 6.34 7.07 5.63 6.36 4.92 5.65zM12 21v-4h1v4h-1zM5.63 18.37l.71-.71.71.71.71.71-1.42 1.42zM21 12h-4v1h4v-1zM3 12h4v1H3v-1zM18.37 5.63l-.71-.71-.71.71-.71.71 1.42 1.42zM12 18a6 6 0 100-12 6 6 0 000 12z"></path></svg>';
const moonIcon = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" fill="none"></path><path d="M12 3a9 9 0 109 9c0-.46-.04-.92-.12-1.36a6.997 6.997 0 01-5.88 5.88A9 9 0 0012 3z"></path></svg>';

const setIcons = (isLight) => {
    iconIds.forEach(id => {
        const iconElement = document.getElementById(id);
        if (iconElement) {
            iconElement.innerHTML = isLight ? moonIcon : sunIcon;
        }
    });
};

const applyTheme = (isLight) => {
    if (isLight) {
        htmlElement.classList.add('light');
        localStorage.setItem('theme', 'light');
    } else {
        htmlElement.classList.remove('light');
        localStorage.setItem('theme', 'dark');
    }
    setIcons(isLight);
};

const initializeTheme = () => {
    const savedTheme = localStorage.getItem('theme');
    const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
    
    let isLightMode = false;

    if (savedTheme) {
        isLightMode = savedTheme === 'light';
    } else {
        isLightMode = prefersLight;
    }
    applyTheme(isLightMode);
};

const toggleTheme = () => {
    const isLight = htmlElement.classList.contains('light');
    applyTheme(!isLight);
};

toggleButtons.forEach(button => {
    button.addEventListener('click', toggleTheme);
});

initializeTheme();

// --- Mobile Menu Toggle (portfolio.php only) ---
const mobileMenuBtn = document.getElementById('mobile-menu-button');
if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', function() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });

    document.querySelectorAll('#mobile-menu a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.add('hidden');
        });
    });
}

// --- Academic Portfolio Accordion Logic (portfolio.php only) ---

// Utility function to recursively update the maxHeight of parent accordion containers
const updateParentHeights = (element) => {
    let current = element.parentElement;
    while (current) {
        if (current.classList.contains('year-content') || current.classList.contains('semester-content')) {
            const parentButton = document.querySelector(`[data-target="${current.id}"]`);
            const isParentOpen = parentButton && parentButton.querySelector('.accordion-icon').classList.contains('rotated');
            
            if (isParentOpen) {
                const newHeight = current.scrollHeight;
                if (Math.abs(current.clientHeight - newHeight) > 2) { 
                    current.style.maxHeight = newHeight + 'px';
                }
            }
        }
        if (current.id === 'portfolio-container' || current.tagName === 'BODY') break;
        current = current.parentElement;
    }
};

const handleAccordionToggle = (button) => {
    const targetId = button.getAttribute('data-target');
    const targetContent = document.getElementById(targetId);
    const icon = button.querySelector('.accordion-icon');
    
    if (!targetContent || !icon) return;

    const isOpening = targetContent.classList.contains('hidden');

    if (isOpening) {
        targetContent.classList.remove('hidden'); 
        
        requestAnimationFrame(() => {
            targetContent.style.maxHeight = targetContent.scrollHeight + 'px';
            targetContent.style.opacity = '1';
            icon.classList.add('rotated');
            updateParentHeights(targetContent);
        });
        
        targetContent.addEventListener('transitionend', function handler() {
            if (targetContent.style.maxHeight !== '0px') { 
                targetContent.style.maxHeight = 'fit-content';
            }
            targetContent.removeEventListener('transitionend', handler);
        }, { once: true });

    } else {
        targetContent.style.maxHeight = targetContent.scrollHeight + 'px';
        
        requestAnimationFrame(() => {
            targetContent.style.maxHeight = '0';
            targetContent.style.opacity = '0';
            icon.classList.remove('rotated');
        });

        setTimeout(() => {
            targetContent.classList.add('hidden');
            targetContent.style.removeProperty('max-height'); 
            targetContent.style.removeProperty('opacity');
            updateParentHeights(targetContent);
        }, ACCORDION_TRANSITION_DURATION + 50);
    }
};

document.querySelectorAll('.year-toggle, .semester-toggle, .period-toggle').forEach(button => {
    button.addEventListener('click', () => handleAccordionToggle(button));
});

// --- Contact Form Handler (portfolio.php only) ---
const contactForm = document.querySelector('#contact form');
if (contactForm) {
    const statusMessage = document.getElementById('status-message');
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        statusMessage.textContent = 'Sending message...';
        statusMessage.classList.remove('hidden', 'success-bg', 'success-text', 'error-bg', 'error-text');
        statusMessage.classList.add('bg-secondary', 'text-accent');

        setTimeout(() => {
            statusMessage.textContent = 'Thank you! Your message has been sent successfully.';
            statusMessage.classList.remove('bg-secondary', 'text-accent');
            statusMessage.classList.add('success-bg', 'success-text');
            
            contactForm.reset();

            setTimeout(() => {
                statusMessage.classList.add('hidden');
            }, 5000);

        }, 1500); 
    });
}

// =============================================================
// --- Admin Dashboard Functions (admin_upload.php only) ---
// CSRF_TOKEN is declared inline in admin_upload.php before this
// script loads, making it available to these functions.
// =============================================================

function escapeHTML(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag])
    );
}

function editFile(button) {
    document.getElementById('edit_file_id').value = button.dataset.id;
    document.getElementById('edit_title').value = button.dataset.title;
    document.getElementById('edit_description').value = button.dataset.description;

    // Clear all checkboxes
    document.querySelectorAll('.edit-visitor-checkbox').forEach(cb => cb.checked = false);

    // Check appropriate boxes based on visitor names
    const visitors = button.dataset.visitors ? button.dataset.visitors.split(',') : [];
    document.querySelectorAll('.edit-visitor-checkbox').forEach(cb => {
        const label = cb.parentElement.textContent.trim();
        if (visitors.some(v => label.includes(v.trim()))) {
            cb.checked = true;
        }
    });

    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function openCommentsModal(fileId, comments) {
    document.getElementById('comment_file_id').value = fileId;
    const container = document.getElementById('commentsContainer');
    container.innerHTML = '';

    if (comments.length === 0) {
        container.innerHTML = '<p class="text-gray-500 italic">No comments yet.</p>';
    } else {
        comments.forEach(c => {
            const isClosed = c.status === 'Closed';
            const bgClass = isClosed ? 'bg-gray-100' : 'bg-blue-50 border border-blue-100';
            const closeBtn = !isClosed ? `
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="${escapeHTML(CSRF_TOKEN)}">
                    <input type="hidden" name="comment_id" value="${c.id}">
                    <button type="submit" name="close_comment" class="text-xs text-gray-500 hover:text-green-600 underline">Resolve</button>
                </form>` : '<span class="text-xs text-green-600 font-medium">Resolved</span>';

            container.innerHTML += `
                <div class="p-3 rounded ${bgClass}">
                    <div class="flex justify-between items-start mb-1">
                        <span class="font-bold text-sm text-gray-800">${escapeHTML(c.username)}</span>
                        <div class="flex space-x-2 items-center">
                            <span class="text-xs text-gray-500">${c.created_at}</span>
                            ${closeBtn}
                        </div>
                    </div>
                    <p class="text-sm text-gray-700 ${isClosed ? 'opacity-70' : ''}">${escapeHTML(c.comment)}</p>
                </div>
            `;
        });
    }
    document.getElementById('commentsModal').classList.remove('hidden');
}

function closeCommentsModal() {
    document.getElementById('commentsModal').classList.add('hidden');
}

function openVersionsModal(fileId, versions) {
    const container = document.getElementById('versionsContainer');
    container.innerHTML = '';
    
    versions.forEach((v, index) => {
        const isLatest = index === 0;
        container.innerHTML += `
            <div class="p-3 border rounded flex justify-between items-center ${isLatest ? 'bg-blue-50 border-blue-200' : 'bg-white'}">
                <div>
                    <span class="font-bold text-sm">Version ${v.version_number} ${isLatest ? '<span class="text-xs text-blue-600 font-normal">(Current)</span>' : ''}</span>
                    <div class="text-xs text-gray-500">Uploaded by ${escapeHTML(v.username)} on ${v.created_at}</div>
                </div>
                <a href="${escapeHTML(v.file_path)}" target="_blank" class="text-sm text-blue-500 hover:underline">Download</a>
            </div>
        `;
    });

    document.getElementById('versionsModal').classList.remove('hidden');
}

function closeVersionsModal() {
    document.getElementById('versionsModal').classList.add('hidden');
}
