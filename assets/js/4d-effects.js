document.addEventListener('DOMContentLoaded', () => {
    // Initialize 4D Tilt Effects
    const tiltCards = document.querySelectorAll('.tilt-card');

    tiltCards.forEach(card => {
        // Add glare element if not present
        if (!card.querySelector('.glare-overlay')) {
            const glare = document.createElement('div');
            glare.className = 'glare-overlay';
            card.appendChild(glare);
        }

        card.addEventListener('mousemove', handleTilt);
        card.addEventListener('mouseleave', resetTilt);
    });

    // Initialize Hero Parallax
    const heroSection = document.querySelector('.hero-4d');
    if (heroSection) {
        document.addEventListener('mousemove', (e) => handleHeroParallax(e, heroSection));
    }

    // Initialize Page Entry Animation
    document.body.classList.add('page-enter');
});

function handleTilt(e) {
    const card = e.currentTarget;
    const cardRect = card.getBoundingClientRect();
    const cardCenterX = cardRect.left + cardRect.width / 2;
    const cardCenterY = cardRect.top + cardRect.height / 2;

    // Mouse position relative to card center
    const mouseX = e.clientX - cardCenterX;
    const mouseY = e.clientY - cardCenterY;

    // Calculate rotation (max 15 degrees)
    const rotateX = -1 * (mouseY / (cardRect.height / 2)) * 10;
    const rotateY = (mouseX / (cardRect.width / 2)) * 10;

    // Calculate glare position
    const glareX = (mouseX / (cardRect.width / 2)) * 50 + 50;
    const glareY = (mouseY / (cardRect.height / 2)) * 50 + 50;

    const glare = card.querySelector('.glare-overlay');

    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;

    if (glare) {
        glare.style.background = `radial-gradient(circle at ${glareX}% ${glareY}%, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 80%)`;
        glare.style.opacity = '1';
    }
}

function resetTilt(e) {
    const card = e.currentTarget;
    const glare = card.querySelector('.glare-overlay');

    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';

    if (glare) {
        glare.style.opacity = '0';
    }
}

function handleHeroParallax(e, hero) {
    const layers = hero.querySelectorAll('[class*="hero-4d-layer-"]');
    const mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
    const mouseY = (e.clientY / window.innerHeight - 0.5) * 2;

    layers.forEach(layer => {
        const depth = layer.getAttribute('data-depth') || 20;
        const x = mouseX * depth;
        const y = mouseY * depth;

        // Preserve existing transforms if any (like translateZ)
        // This is a simplified approach; usually you'd want to parse current transform
        // For now we assume a cleaner state or append
        const originalTransform = getComputedStyle(layer).transform;
        if (originalTransform === 'none') {
            layer.style.transform = `translateX(${x}px) translateY(${y}px)`;
        } else {
            // We'll rely on CSS transition for smoothness, but ideally we shouldn't overwrite translateZ if set in CSS
            // So let's use CSS variables for safe composition
            layer.style.setProperty('--mouse-x', `${x}px`);
            layer.style.setProperty('--mouse-y', `${y}px`);
        }
    });
}
