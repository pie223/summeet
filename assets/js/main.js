/**
 * Summeet - small client-side helpers.
 * Kept dependency-free on purpose.
 */

// Mirrors includes/functions.php political_label() so the live slider
// readout matches exactly what the server will store/display.
function politicalLabel(rawValue) {
    const value = parseInt(rawValue, 10);
    if (value <= -60) return 'Radical left';
    if (value <= -20) return 'Left';
    if (value < 20) return 'Centrist';
    if (value < 60) return 'Right';
    return 'Alt-right';
}

document.addEventListener('DOMContentLoaded', function () {
    // Horseshoe political slider (markup: includes/horseshoe_slider.php).
    // Geometry must match the PHP: centre (150,120), 300deg ring, 0deg = bottom
    // (the horseshoe opens upwards; the two extremes meet at the top).
    document.querySelectorAll('[data-horseshoe]').forEach(function (wrap) {
        const svg = wrap.querySelector('.horseshoe-svg');
        const handle = wrap.querySelector('.horseshoe-handle');
        const input = wrap.querySelector('input[name="political_orientation"]');
        const label = wrap.querySelector('#political_label');
        const CX = 150, CY = 120, R = 100, MAX = 150;
        let value = parseInt(input.value, 10) || 0;

        function setValue(v) {
            value = Math.max(-100, Math.min(100, Math.round(v)));
            const rad = (value * MAX / 100) * Math.PI / 180;
            handle.setAttribute('cx', (CX + R * Math.sin(rad)).toFixed(1));
            handle.setAttribute('cy', (CY + R * Math.cos(rad)).toFixed(1));
            input.value = value;
            if (label) label.textContent = politicalLabel(value);
            svg.setAttribute('aria-valuenow', value);
            svg.setAttribute('aria-valuetext', politicalLabel(value));
        }

        function fromPointer(e) {
            // Convert the mouse/touch position into the SVG's own coordinates.
            const pt = svg.createSVGPoint();
            pt.x = e.clientX; pt.y = e.clientY;
            const p = pt.matrixTransform(svg.getScreenCTM().inverse());
            let deg = Math.atan2(p.x - CX, p.y - CY) * 180 / Math.PI;   // 0 = bottom, right = +
            if (deg > MAX) deg = MAX;        // the gap at the top snaps
            if (deg < -MAX) deg = -MAX;      // to the nearest end
            setValue(deg * 100 / MAX);
        }

        // Listeners live on the document while dragging (no pointer capture), which is
        // more reliable in Firefox; a drag can never get "stuck" because it ends as soon
        // as no mouse button / finger is down any more.
        let dragging = false;
        function onMove(e) {
            if (!dragging) return;
            if (e.pointerType === 'mouse' && e.buttons === 0) { stop(); return; }
            fromPointer(e);
        }
        function stop() {
            dragging = false;
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', stop);
            document.removeEventListener('pointercancel', stop);
        }
        svg.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            stop();
            dragging = true;
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', stop);
            document.addEventListener('pointercancel', stop);
            svg.focus();
            fromPointer(e);
            e.preventDefault();
        });
        // Stop the browser from starting its own text-selection / drag on the SVG.
        ['dragstart', 'selectstart'].forEach(function (t) {
            svg.addEventListener(t, function (e) { e.preventDefault(); });
        });
        svg.addEventListener('keydown', function (e) {
            const step = e.shiftKey ? 10 : 2;
            if (e.key === 'ArrowRight' || e.key === 'ArrowUp') setValue(value + step);
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') setValue(value - step);
            else if (e.key === 'Home') setValue(-100);
            else if (e.key === 'End') setValue(100);
            else return;
            e.preventDefault();
        });

        setValue(value);
    });

    // Confirm before leaving a page with unsaved textarea content on join forms.
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.dataset.originalText = btn.textContent;
                btn.textContent = 'Please wait…';
                // Re-enable shortly after in case of client-side validation blocking submit
                setTimeout(function () {
                    btn.disabled = false;
                    if (btn.dataset.originalText) btn.textContent = btn.dataset.originalText;
                }, 1500);
            }
        });
    });
});
