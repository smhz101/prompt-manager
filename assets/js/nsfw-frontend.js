// Global function to show NSFW modal
function showNSFWModal() {
  const modal = document.getElementById('nsfw-modal');
  if (modal && document.body.classList.contains('nsfw-blocked-page')) {
    // Force modal styles to ensure proper positioning
    modal.style.cssText = `
      display: flex !important;
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      z-index: 2147483647 !important;
      align-items: center !important;
      justify-content: center !important;
      background: rgba(0, 0, 0, 0.95) !important;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    `;

    modal.classList.add('active');

    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';

    // Ensure modal content is properly styled
    const modalContent = modal.querySelector('.nsfw-modal-content');
    if (modalContent) {
      modalContent.style.cssText = `
        position: relative !important;
        background: white !important;
        border-radius: 15px !important;
        max-width: 500px !important;
        width: 90% !important;
        max-height: 90vh !important;
        overflow-y: auto !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
        z-index: 2147483647 !important;
        animation: modalSlideIn 0.3s ease-out !important;
        margin: 0 !important;
      `;
    }

    // Apply heavy blur to all content except modal
    const contentSelectors = [
      '.site-content',
      '.content',
      'main',
      'article',
      '.entry-content',
      '.post-content',
      '#content',
      '.page-content',
      '.single-content',
      '.site-main',
      '.main-content',
      '.primary-content',
      '#main',
      '.container',
      '.wrapper',
      '#wrapper',
      'header',
      'nav',
      'footer',
      '.header',
      '.nav',
      '.footer',
    ];

    contentSelectors.forEach((selector) => {
      const elements = document.querySelectorAll(selector);
      elements.forEach((el) => {
        // Don't blur the modal itself
        if (el !== modal && !modal.contains(el)) {
          el.style.filter = 'blur(25px) brightness(0.7)';
          el.style.pointerEvents = 'none';
          el.style.userSelect = 'none';
          el.style.webkitUserSelect = 'none';
          el.style.mozUserSelect = 'none';
          el.style.msUserSelect = 'none';
        }
      });
    });

    // Prevent all interactions
    document.addEventListener('contextmenu', preventInteraction, true);
    document.addEventListener('selectstart', preventInteraction, true);
    document.addEventListener('dragstart', preventInteraction, true);
    document.addEventListener('keydown', preventKeyboardShortcuts, true);
  }
}

function preventInteraction(e) {
  if (document.body.classList.contains('nsfw-blocked-page')) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  }
}

function preventKeyboardShortcuts(e) {
  if (document.body.classList.contains('nsfw-blocked-page')) {
    // Prevent common shortcuts
    if (
      e.ctrlKey &&
      (e.key === 's' ||
        e.key === 'a' ||
        e.key === 'c' ||
        e.key === 'v' ||
        e.key === 'u' ||
        e.key === 'p')
    ) {
      e.preventDefault();
      return false;
    }
    // Prevent F12, F5, etc.
    if (e.key === 'F12' || e.key === 'F5' || e.key === 'F11') {
      e.preventDefault();
      return false;
    }
  }
}

// Declare the promptManagerNSFW variable
const promptManagerNSFW = {
  isBlocked: true, // Example value, should be set according to actual logic
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Check if this is an NSFW blocked page
  if (
    document.body.classList.contains('nsfw-blocked-page') &&
    typeof promptManagerNSFW !== 'undefined' &&
    promptManagerNSFW.isBlocked
  ) {
    // Show modal immediately
    showNSFWModal();

    // Handle modal interactions
    const modal = document.getElementById('nsfw-modal');
    const closeBtn = document.querySelector('.nsfw-modal-close-btn');
    const overlay = document.querySelector('.nsfw-modal-overlay');

    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        if (document.referrer && document.referrer !== window.location.href) {
          window.history.back();
        } else {
          window.location.href = '/';
        }
      });
    }

    // Prevent modal from closing when clicking inside content
    const modalContent = document.querySelector('.nsfw-modal-content');
    if (modalContent) {
      modalContent.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    }

    // Optional: Close modal when clicking overlay
    if (overlay) {
      overlay.addEventListener('click', () => {
        if (closeBtn) {
          closeBtn.click();
        }
      });
    }

    // Disable right-click completely
    document.addEventListener('contextmenu', (e) => {
      e.preventDefault();
      return false;
    });

    // Disable text selection
    document.addEventListener('selectstart', (e) => {
      e.preventDefault();
      return false;
    });

    // Disable drag and drop
    document.addEventListener('dragstart', (e) => {
      e.preventDefault();
      return false;
    });

    // Disable keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      // Disable F12 (Developer Tools)
      if (e.key === 'F12') {
        e.preventDefault();
        return false;
      }

      // Disable Ctrl+Shift+I (Developer Tools)
      if (e.ctrlKey && e.shiftKey && e.key === 'I') {
        e.preventDefault();
        return false;
      }

      // Disable Ctrl+Shift+J (Console)
      if (e.ctrlKey && e.shiftKey && e.key === 'J') {
        e.preventDefault();
        return false;
      }

      // Disable Ctrl+U (View Source)
      if (e.ctrlKey && e.key === 'u') {
        e.preventDefault();
        return false;
      }

      // Disable Ctrl+S (Save)
      if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        return false;
      }

      // Disable Ctrl+A (Select All)
      if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        return false;
      }

      // Disable Ctrl+P (Print)
      if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        return false;
      }
    });
  }
});

// Ensure modal persists even if page content changes
const observer = new MutationObserver((mutations) => {
  if (
    document.body.classList.contains('nsfw-blocked-page') &&
    !document.getElementById('nsfw-modal').classList.contains('active')
  ) {
    showNSFWModal();
  }
});

observer.observe(document.body, {
  childList: true,
  subtree: true,
});
