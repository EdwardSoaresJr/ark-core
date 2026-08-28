function initPublicMobileContactBar() {
    const watchTarget =
        document.getElementById('public-book-cta') ||
        document.getElementById('tell-the-shop') ||
        document.getElementById('send-a-message') ||
        document.getElementById('book');
    const bar = document.getElementById('public-mobile-contact-bar');

    if (!watchTarget || !bar) {
        return;
    }

    const showBar = (visible) => {
        bar.hidden = !visible;
        bar.setAttribute('aria-hidden', visible ? 'false' : 'true');
        bar.classList.toggle('public-mobile-contact-bar--visible', visible);
    };

    const observer = new IntersectionObserver(
        ([entry]) => {
            showBar(!entry.isIntersecting);
        },
        { threshold: 0 },
    );

    observer.observe(watchTarget);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicMobileContactBar);
} else {
    initPublicMobileContactBar();
}
