import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/lecture-chair.js', import.meta.url), 'utf8');

function setup(idleMs) {
    function target(properties = {}) {
        const listeners = new Map();
        return Object.assign({
            addEventListener(name, callback) { listeners.set(name, [...(listeners.get(name) ?? []), callback]); },
            emit(name, event = {}) { listeners.get(name)?.forEach((callback) => callback(event)); },
        }, properties);
    }
    let now = 0;
    let nextId = 0;
    const timers = new Map();
    const styles = new Map();
    const chair = { style: {
        setProperty(name, value) { styles.set(name, value); },
        removeProperty(name) { styles.delete(name); },
    } };
    const preview = target({ hidden: true, focus() {} });
    const dismiss = target();
    const popupStyles = new Map();
    const frames = new Map();
    const hero = target({
        dataset: { chairIdleMs: idleMs },
        querySelector: (selector) => selector === '[data-lecture-chair]' ? chair : preview,
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 400 }),
    });
    const dialog = target({ hidden: true, contains: () => false, querySelectorAll: () => [dismiss], style: { setProperty(name, value) { popupStyles.set(name, value); } } });
    const document = target({
        hidden: false,
        querySelector: (selector) => selector === '[data-lecture-hero]' ? hero : dialog,
    });
    const media = target({ matches: false });
    const window = target({
        scrollY: 0,
        matchMedia: () => media,
        setTimeout(callback, delay) { timers.set(++nextId, { callback, due: now + delay }); return nextId; },
        clearTimeout(id) { timers.delete(id); },
        requestAnimationFrame(callback) { frames.set(++nextId, callback); return nextId; },
        cancelAnimationFrame(id) { frames.delete(id); },
    });
    runInNewContext(source, { document, window });
    function tick(ms) {
        now += ms;
        for (const [id, timer] of timers) {
            if (timer.due <= now) { timers.delete(id); timer.callback(); }
        }
    }
    function animate(time = 16) {
        const pending = [...frames.values()];
        frames.clear();
        pending.forEach((callback) => callback(time));
    }
    return { document, dialog, hero, preview, media, styles, tick, dismiss, window, popupStyles, animate, frames };
}

test('waits a full 90 seconds and restarts after mouse, keyboard, touch or scroll activity', () => {
    for (const activity of ['pointermove', 'keydown', 'pointerdown', 'scroll']) {
        const { document, dialog, tick } = setup();
        tick(89_999);
        assert.equal(dialog.hidden, true);
        document.emit(activity);
        tick(89_999);
        assert.equal(dialog.hidden, true);
        tick(1);
        assert.equal(dialog.hidden, false);
    }
});

test('uses the delay from the rendered page without rebuilding JavaScript', () => {
    for (const delay of [1000, 5000, 90000]) {
        const { dialog, tick } = setup(String(delay));
        tick(delay - 1);
        assert.equal(dialog.hidden, true);
        tick(1);
        assert.equal(dialog.hidden, false);
    }
});

test('invalid page configuration falls back to 90 seconds', () => {
    for (const delay of ['', 'invalid', '0', '-100', 'Infinity']) {
        const { dialog, tick } = setup(delay);
        tick(89_999);
        assert.equal(dialog.hidden, true);
        tick(1);
        assert.equal(dialog.hidden, false);
    }
});

test('pauses in a hidden tab and waits 90 seconds on return', () => {
    const { document, dialog, tick } = setup();
    tick(80_000);
    document.hidden = true;
    document.emit('visibilitychange');
    tick(100_000);
    assert.equal(dialog.hidden, true);
    document.hidden = false;
    document.emit('visibilitychange');
    tick(89_999);
    assert.equal(dialog.hidden, true);
    tick(1);
    assert.equal(dialog.hidden, false);
});

test('dismissal suppresses automatic popups but allows manual reopening', () => {
    const { preview, dialog, tick, dismiss, document } = setup();
    preview.emit('click');
    assert.equal(dialog.hidden, false);
    dismiss.emit('click');
    document.emit('pointermove');
    tick(180_000);
    assert.equal(dialog.hidden, true);
    preview.emit('click');
    assert.equal(dialog.hidden, false);
    document.emit('keydown', { key: 'Escape' });
    assert.equal(dialog.hidden, true);
});

test('scroll following is bounded and settles without a perpetual animation loop', () => {
    const { preview, window, popupStyles, animate, frames, dialog } = setup();
    preview.emit('click');
    window.scrollY = 500;
    window.emit('scroll');
    assert.equal(popupStyles.get('--popup-lag'), '-18px');
    animate(16);
    assert.ok(Number.parseFloat(popupStyles.get('--popup-lag')) > -18);
    for (let time = 32; time < 1500; time += 16) animate(time);
    assert.equal(popupStyles.get('--popup-lag'), '0px');
    assert.equal(frames.size, 0);
    dialog.emit('pointerenter');
    window.scrollY = 1000;
    window.emit('scroll');
    assert.equal(popupStyles.get('--popup-lag'), '0px');
});

test('hidden popups and reduced motion do not animate while scrolling', () => {
    const { preview, window, media, frames } = setup();
    window.scrollY = 100;
    window.emit('scroll');
    assert.equal(frames.size, 0);
    preview.emit('click');
    media.matches = true;
    window.scrollY = 200;
    window.emit('scroll');
    assert.equal(frames.size, 0);
});

test('chair follows mouse and returns to rest, respecting reduced motion and touch', () => {
    const { hero, media, styles, animate } = setup();
    const movement = { pointerType: 'mouse', clientX: 800, clientY: 400 };
    hero.emit('pointermove', movement);
    animate();
    assert.equal(styles.get('--chair-ry'), '10deg');
    hero.emit('pointerleave');
    assert.equal(styles.size, 0);
    hero.emit('pointermove', { ...movement, pointerType: 'touch' });
    assert.equal(styles.size, 0);
    media.matches = true;
    hero.emit('pointermove', movement);
    animate();
    assert.equal(styles.size, 0);
});
