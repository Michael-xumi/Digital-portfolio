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

// --- Mobile Menu Toggle ---
document.getElementById('mobile-menu-button').addEventListener('click', function() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('hidden');
});

document.querySelectorAll('#mobile-menu a').forEach(link => {
    link.addEventListener('click', () => {
        document.getElementById('mobile-menu').classList.add('hidden');
    });
});

// --- Academic Portfolio Accordion Logic ---

// Utility function to recursively update the maxHeight of parent accordion containers
const updateParentHeights = (element) => {
    let current = element.parentElement;
    while (current) {
        // Look for the parent accordion content containers
        if (current.classList.contains('year-content') || current.classList.contains('semester-content')) {
            // Check if the parent is currently OPEN (i.e., its button's icon is rotated)
            const parentButton = document.querySelector(`[data-target="${current.id}"]`);
            // Ensure parentButton exists and check if it's an accordion toggle (which is how we track open state)
            const isParentOpen = parentButton && parentButton.querySelector('.accordion-icon').classList.contains('rotated');
            
            if (isParentOpen) {
                 // Recalculate height to accommodate the change in the child
                const newHeight = current.scrollHeight;
                // Only update if the height has changed significantly to avoid jitter
                // Using clientHeight since maxHeight might be set to 'fit-content' (effectively auto)
                if (Math.abs(current.clientHeight - newHeight) > 2) { 
                    current.style.maxHeight = newHeight + 'px';
                }
            }
        }
        // Stop walking up if we hit the main container or body
        if (current.id === 'portfolio-container' || current.tagName === 'BODY') break;
        current = current.parentElement;
    }
};


const handleAccordionToggle = (button) => {
    const targetId = button.getAttribute('data-target');
    const targetContent = document.getElementById(targetId);
    const icon = button.querySelector('.accordion-icon');
    
    if (!targetContent || !icon) return;

    // Check if the content is currently collapsed (uses the 'hidden' class from HTML initial state)
    const isOpening = targetContent.classList.contains('hidden');

    if (isOpening) {
        // OPENING
        
        // 1. Make it visible so scrollHeight can be calculated
        targetContent.classList.remove('hidden'); 
        
        requestAnimationFrame(() => {
            // 2. Set max-height and opacity to start the transition
            // Set a very large max-height if the content will be dynamically resized after transition
            // For nested structures, using scrollHeight is better before transition end
            targetContent.style.maxHeight = targetContent.scrollHeight + 'px';
            targetContent.style.opacity = '1';
            icon.classList.add('rotated');
            
            // 3. Crucial: Recursively update parent heights to accommodate new size
            updateParentHeights(targetContent);
        });
        
        // Optional: After transition, set maxHeight to 'fit-content' so the section can grow
        // or shrink with content changes without triggering another transition.
        targetContent.addEventListener('transitionend', function handler() {
            // Check if max-height is still what we set, indicating it finished opening
            if (targetContent.style.maxHeight !== '0px') { 
                targetContent.style.maxHeight = 'fit-content';
            }
            targetContent.removeEventListener('transitionend', handler);
        }, { once: true });


    } else {
        // CLOSING
        // 1. Set max-height explicitly to current size before transitioning to 0
        // We must read the actual height (scrollHeight) now, not 'fit-content' or a fixed pixel value
        // that might be incorrect after content has been dynamic.
        targetContent.style.maxHeight = targetContent.scrollHeight + 'px';
        
        requestAnimationFrame(() => {
            // 2. Transition to collapse
            targetContent.style.maxHeight = '0';
            targetContent.style.opacity = '0';
            icon.classList.remove('rotated');
        });

        // 3. Hide completely and update parent heights after the transition ends
        setTimeout(() => {
            targetContent.classList.add('hidden');
            // Clean up inline styles after closing
            targetContent.style.removeProperty('max-height'); 
            targetContent.style.removeProperty('opacity');
            // Crucial: Recursively update parent heights to accommodate lost size
            updateParentHeights(targetContent);
        }, ACCORDION_TRANSITION_DURATION + 50);
    }
};


document.querySelectorAll('.year-toggle, .semester-toggle, .period-toggle').forEach(button => {
    button.addEventListener('click', () => handleAccordionToggle(button));
});


// --- Simple Form Submission Handler (Non-functional without a backend) ---
const contactForm = document.querySelector('#contact form');
const statusMessage = document.getElementById('status-message');

contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Clear previous styles and set processing message
    statusMessage.textContent = 'Sending message...';
    statusMessage.classList.remove('hidden', 'success-bg', 'success-text', 'error-bg', 'error-text');
    statusMessage.classList.add('bg-secondary', 'text-accent');

    setTimeout(() => {
        // Simulate success
        statusMessage.textContent = 'Thank you! Your message has been sent successfully.';
        statusMessage.classList.remove('bg-secondary', 'text-accent');
        statusMessage.classList.add('success-bg', 'success-text');
        
        contactForm.reset();

        setTimeout(() => {
            statusMessage.classList.add('hidden');
        }, 5000);

    }, 1500); 
});
