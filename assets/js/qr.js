/**
 * Tiny QR code generator for Summeet (no libraries, no internet needed).
 *   SummeetQR.svg('https://example.com/')  ->  SVG markup of the QR code
 * Byte mode, error correction level M, versions 1-10 (up to ~210 characters).
 */
(function (global) {
    'use strict';

    // ---- Reed-Solomon over GF(256) ----
    var EXP = [], LOG = [];
    (function () {
        var x = 1, i;
        for (i = 0; i < 255; i++) { EXP[i] = x; LOG[x] = i; x <<= 1; if (x & 256) x ^= 0x11d; }
        for (i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
    })();
    function gmul(a, b) { return (a && b) ? EXP[LOG[a] + LOG[b]] : 0; }
    function rsGenerator(n) {
        var g = [1], i, j;
        for (i = 0; i < n; i++) {
            var ng = [];
            for (j = 0; j <= g.length; j++) ng[j] = 0;
            for (j = 0; j < g.length; j++) { ng[j] ^= g[j]; ng[j + 1] ^= gmul(g[j], EXP[i]); }
            g = ng;
        }
        return g;
    }
    function rsRemainder(data, n) {
        var g = rsGenerator(n), res = [], i, j;
        for (i = 0; i < n; i++) res[i] = 0;
        for (i = 0; i < data.length; i++) {
            var f = data[i] ^ res[0];
            res.shift(); res.push(0);
            for (j = 0; j < n; j++) res[j] ^= gmul(g[j + 1], f);
        }
        return res;
    }

    // Per version (level M): [EC codewords per block, blocks A, data per block A, blocks B, data per block B]
    var VER = [null,
        [10, 1, 16, 0, 0], [16, 1, 28, 0, 0], [26, 1, 44, 0, 0], [18, 2, 32, 0, 0], [24, 2, 43, 0, 0],
        [16, 4, 27, 0, 0], [18, 4, 31, 0, 0], [22, 2, 38, 2, 39], [22, 3, 36, 2, 37], [26, 4, 43, 1, 44]];
    var ALIGN = [null, [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50]];

    function dataCapacity(v) { var t = VER[v]; return t[1] * t[2] + t[3] * t[4]; }

    function utf8Bytes(text) {
        var s = unescape(encodeURIComponent(text)), out = [], i;
        for (i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
        return out;
    }

    function makeCodewords(bytes, v) {
        var bits = [], i;
        function put(val, len) { for (var k = len - 1; k >= 0; k--) bits.push((val >>> k) & 1); }
        put(4, 4);                              // byte mode
        put(bytes.length, v < 10 ? 8 : 16);
        for (i = 0; i < bytes.length; i++) put(bytes[i], 8);
        var capBits = dataCapacity(v) * 8;
        for (i = 0; i < 4 && bits.length < capBits; i++) bits.push(0);   // terminator
        while (bits.length % 8) bits.push(0);
        var cw = [];
        for (i = 0; i < bits.length; i += 8) {
            var b = 0; for (var k = 0; k < 8; k++) b = (b << 1) | bits[i + k];
            cw.push(b);
        }
        for (i = 0; cw.length < dataCapacity(v); i++) cw.push(i % 2 ? 0x11 : 0xEC);   // padding

        // Split into blocks, add error correction, interleave
        var t = VER[v], blocks = [], pos = 0, n;
        for (n = 0; n < t[1] + t[3]; n++) {
            var len = n < t[1] ? t[2] : t[4];
            blocks.push(cw.slice(pos, pos + len)); pos += len;
        }
        var ecs = blocks.map(function (bl) { return rsRemainder(bl, t[0]); });
        var out = [], maxLen = t[3] ? t[4] : t[2];
        for (i = 0; i < maxLen; i++) blocks.forEach(function (bl) { if (i < bl.length) out.push(bl[i]); });
        for (i = 0; i < t[0]; i++) ecs.forEach(function (ec) { out.push(ec[i]); });
        var allBits = [];
        out.forEach(function (c) { for (var k = 7; k >= 0; k--) allBits.push((c >>> k) & 1); });
        return allBits;
    }

    function bit(x, i) { return (x >>> i) & 1; }

    function buildMatrix(v, dataBits) {
        var size = 17 + 4 * v, m = [], fn = [], r, c, i;
        for (r = 0; r < size; r++) { m[r] = []; fn[r] = []; for (c = 0; c < size; c++) { m[r][c] = 0; fn[r][c] = false; } }
        function setFn(x, y, dark) { m[y][x] = dark ? 1 : 0; fn[y][x] = true; }   // x = column, y = row

        // Timing patterns
        for (i = 0; i < size; i++) { setFn(6, i, i % 2 === 0); setFn(i, 6, i % 2 === 0); }
        // Finder patterns (with their white borders)
        [[0, 0], [size - 7, 0], [0, size - 7]].forEach(function (p) {
            for (var dy = -1; dy <= 7; dy++) for (var dx = -1; dx <= 7; dx++) {
                var x = p[0] + dx, y = p[1] + dy;
                if (x < 0 || y < 0 || x >= size || y >= size) continue;
                var d = Math.max(Math.abs(dx - 3), Math.abs(dy - 3));
                setFn(x, y, d !== 2 && d !== 4);
            }
        });
        // Alignment patterns
        var al = ALIGN[v], last = al.length - 1;
        al.forEach(function (ay, a) { al.forEach(function (ax, b) {
            if ((a === 0 && b === 0) || (a === 0 && b === last) || (a === last && b === 0)) return;
            for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++)
                setFn(ax + dx, ay + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }); });
        // Reserve format areas (real values come later) and the always-dark module
        for (i = 0; i < 9; i++) { fn[8][i] = true; fn[i][8] = true; }
        for (i = 0; i < 8; i++) { fn[8][size - 1 - i] = true; fn[size - 1 - i][8] = true; }
        setFn(8, size - 8, true);
        // Version information (versions 7+)
        if (v >= 7) {
            var rem = v;
            for (i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
            var vbits = (v << 12) | rem;
            for (i = 0; i < 18; i++) {
                var a2 = size - 11 + (i % 3), b2 = Math.floor(i / 3);
                setFn(a2, b2, bit(vbits, i)); setFn(b2, a2, bit(vbits, i));
            }
        }
        // Data bits in the zigzag order
        var idx = 0, dir = -1;
        r = size - 1;
        for (c = size - 1; c > 0; c -= 2) {
            if (c === 6) c--;
            for (;;) {
                for (var k = 0; k < 2; k++) {
                    var cc = c - k;
                    if (!fn[r][cc]) m[r][cc] = idx < dataBits.length ? dataBits[idx++] : 0;
                }
                r += dir;
                if (r < 0 || r >= size) { r -= dir; dir = -dir; break; }
            }
        }
        return { m: m, fn: fn, size: size };
    }

    var MASKS = [
        function (r, c) { return (r + c) % 2 === 0; },
        function (r, c) { return r % 2 === 0; },
        function (r, c) { return c % 3 === 0; },
        function (r, c) { return (r + c) % 3 === 0; },
        function (r, c) { return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0; },
        function (r, c) { return (r * c) % 2 + (r * c) % 3 === 0; },
        function (r, c) { return ((r * c) % 2 + (r * c) % 3) % 2 === 0; },
        function (r, c) { return ((r + c) % 2 + (r * c) % 3) % 2 === 0; }
    ];

    function withMask(base, mask) {
        var size = base.size, m = [], r, c, i;
        for (r = 0; r < size; r++) {
            m[r] = base.m[r].slice();
            for (c = 0; c < size; c++) if (!base.fn[r][c] && MASKS[mask](r, c)) m[r][c] ^= 1;
        }
        // Format information: error level M (bits 00) + mask number
        var data = (0 << 3) | mask, rem = data;
        for (i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
        var bits = ((data << 10) | rem) ^ 0x5412;
        function set(x, y, d) { m[y][x] = d; }
        for (i = 0; i <= 5; i++) set(8, i, bit(bits, i));
        set(8, 7, bit(bits, 6)); set(8, 8, bit(bits, 7)); set(7, 8, bit(bits, 8));
        for (i = 9; i < 15; i++) set(14 - i, 8, bit(bits, i));
        for (i = 0; i < 8; i++) set(size - 1 - i, 8, bit(bits, i));
        for (i = 8; i < 15; i++) set(8, size - 15 + i, bit(bits, i));
        set(8, size - 8, 1);
        return m;
    }

    function penalty(m) {
        var size = m.length, p = 0, r, c, k, dark = 0;
        var pats = ['10111010000', '00001011101'];
        function lines(get) {
            for (var a = 0; a < size; a++) {
                var run = 1, s = '';
                for (var b = 0; b < size; b++) {
                    var cur = get(a, b);
                    s += cur;
                    if (b > 0 && cur === get(a, b - 1)) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1;
                }
                pats.forEach(function (pt) { var at = -1; while ((at = s.indexOf(pt, at + 1)) !== -1) p += 40; });
            }
        }
        lines(function (a, b) { return m[a][b]; });
        lines(function (a, b) { return m[b][a]; });
        for (r = 0; r < size; r++) for (c = 0; c < size; c++) {
            if (m[r][c]) dark++;
            if (r < size - 1 && c < size - 1 && m[r][c] === m[r][c + 1] && m[r][c] === m[r + 1][c] && m[r][c] === m[r + 1][c + 1]) p += 3;
        }
        var total = size * size;
        k = Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1;
        return p + Math.max(0, k) * 10;
    }

    function encode(text) {
        var bytes = utf8Bytes(text), v, need;
        for (v = 1; v <= 10; v++) {
            need = 4 + (v < 10 ? 8 : 16) + bytes.length * 8;
            if (need <= dataCapacity(v) * 8) break;
        }
        if (v > 10) throw new Error('Text too long for this QR generator');
        var base = buildMatrix(v, makeCodewords(bytes, v)), best = null, bestScore = Infinity;
        for (var mask = 0; mask < 8; mask++) {
            var m = withMask(base, mask), sc = penalty(m);
            if (sc < bestScore) { bestScore = sc; best = m; }
        }
        return best;
    }

    function svg(text, margin) {
        var m = encode(text), size = m.length, q = margin === undefined ? 4 : margin, d = '', r, c;
        for (r = 0; r < size; r++) for (c = 0; c < size; c++) if (m[r][c]) d += 'M' + (c + q) + ' ' + (r + q) + 'h1v1h-1z';
        var full = size + 2 * q;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + full + ' ' + full + '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            + '<rect width="' + full + '" height="' + full + '" fill="#fff"/><path d="' + d + '" fill="currentColor"/></svg>';
    }

    global.SummeetQR = { svg: svg, matrix: encode };
})(window);
