const hero = document.querySelector('[data-lecture-hero]');
const dialog = document.querySelector('[data-chair-dialog]');

if (hero && dialog) {
    const chair = hero.querySelector('[data-lecture-chair]');
    const preview = hero.querySelector('[data-chair-preview]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    // Keep the editable delay in Blade so changes do not require rebuilding JS.
    const configuredDelay = Number(hero.dataset.chairIdleMs);
    const idleDelay = Number.isFinite(configuredDelay) && configuredDelay > 0
        ? configuredDelay
        : 90_000;
    let idleTimer;
    let frame;
    let dismissed = false;
    let followFrame;
    let lastScroll = window.scrollY;
    let lag = 0;
    let lastFrameTime;
    let interacting = false;

    function resetFollow() {
        window.cancelAnimationFrame(followFrame);
        followFrame = null;
        lastFrameTime = null;
        lag = 0;
        lastScroll = window.scrollY;
        dialog.style.setProperty('--popup-lag', '0px');
    }

    function settleFollow(time) {
        const elapsed = lastFrameTime === null ? 16 : Math.min(time - lastFrameTime, 64);
        lastFrameTime = time;
        // Time-based easing keeps the same gentle catch-up on 60 Hz and 120 Hz screens.
        lag *= Math.exp(-elapsed / 140);
        dialog.style.setProperty('--popup-lag', `${lag}px`);
        if (Math.abs(lag) > 0.1) {
            followFrame = window.requestAnimationFrame(settleFollow);
        } else {
            resetFollow();
        }
    }

    window.addEventListener('scroll', () => {
        const delta = window.scrollY - lastScroll;
        lastScroll = window.scrollY;
        if (dialog.hidden || document.hidden || reducedMotion.matches || interacting || dialog.contains(document.activeElement)) return;
        lag = Math.max(-18, Math.min(18, lag - delta * 0.22));
        dialog.style.setProperty('--popup-lag', `${lag}px`);
        if (!followFrame) followFrame = window.requestAnimationFrame(settleFollow);
    }, { passive: true });

    dialog.addEventListener('pointerenter', () => { interacting = true; resetFollow(); });
    dialog.addEventListener('pointerleave', () => { interacting = false; });
    dialog.addEventListener('focusin', resetFollow);

    function dismissDialog() {
        dismissed = true;
        window.clearTimeout(idleTimer);
        if (dialog.contains(document.activeElement)) preview.focus({ preventScroll: true });
        dialog.hidden = true;
        interacting = false;
        resetFollow();
    }

    for (const button of dialog.querySelectorAll('[data-chair-dismiss]')) {
        button.addEventListener('click', dismissDialog);
    }
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.hidden) dismissDialog();
    });

    function openDialog() {
        window.clearTimeout(idleTimer);
        if (!document.hidden && dialog.hidden) {
            resetFollow();
            dialog.hidden = false;
        }
    }

    function restartTimer() {
        window.clearTimeout(idleTimer);
        if (!document.hidden && dialog.hidden && !dismissed) {
            idleTimer = window.setTimeout(openDialog, idleDelay);
        }
    }

    function resetChair() {
        window.cancelAnimationFrame(frame);
        chair.style.removeProperty('--chair-x');
        chair.style.removeProperty('--chair-y');
        chair.style.removeProperty('--chair-rx');
        chair.style.removeProperty('--chair-ry');
    }

    hero.addEventListener('pointermove', (event) => {
        if (event.pointerType === 'touch' || reducedMotion.matches) return;
        window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(() => {
            const bounds = hero.getBoundingClientRect();
            const x = Math.max(-1, Math.min(1, ((event.clientX - bounds.left) / bounds.width - 0.5) * 2));
            const y = Math.max(-1, Math.min(1, ((event.clientY - bounds.top) / bounds.height - 0.5) * 2));
            chair.style.setProperty('--chair-x', `${x * 9}px`);
            chair.style.setProperty('--chair-y', `${y * 5}px`);
            chair.style.setProperty('--chair-rx', `${-y * 5}deg`);
            chair.style.setProperty('--chair-ry', `${x * 10}deg`);
        });
    });

    hero.addEventListener('pointerleave', resetChair);
    reducedMotion.addEventListener('change', () => { resetChair(); resetFollow(); });
    // Keyboard, touch and scrolling also count as activity for non-mouse users.
    for (const event of ['pointermove', 'pointerdown', 'keydown', 'scroll']) {
        document.addEventListener(event, restartTimer, { passive: true, capture: true });
    }
    document.addEventListener('visibilitychange', () => {
        resetChair();
        resetFollow();
        restartTimer();
    });
    window.addEventListener('pagehide', () => {
        window.clearTimeout(idleTimer);
        resetChair();
        resetFollow();
    });
    window.addEventListener('pageshow', restartTimer);
    preview.hidden = false;
    preview.addEventListener('click', openDialog);
    restartTimer();
}
