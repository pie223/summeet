<?php
/**
 * Horseshoe political-orientation slider (shared by register.php and profile.php).
 *
 * Expects $horseshoeValue (int, -100 = radical left .. 100 = alt-right) to be set
 * before including. The handle slides along an open ring: the two extremes sit
 * next to each other at the top (opening faces up, like a lucky horseshoe),
 * centrist at the bottom. The chosen value is
 * stored in the hidden <input name="political_orientation"> exactly like the old
 * straight slider did, so nothing else in the app changes. Behaviour lives in
 * assets/js/main.js, look in assets/css/style.css.
 */
$hv        = max(-100, min(100, (int) ($horseshoeValue ?? 0)));
$hsCx      = 150;   // ring centre x   (keep in sync with main.js)
$hsCy      = 120;   // ring centre y
$hsR       = 100;   // ring radius
$hsMax     = 150;   // degrees from the top to either end (300° ring, 60° gap)
$hsPoint   = function (float $deg, float $radius) use ($hsCx, $hsCy): array {
    $rad = deg2rad($deg);
    return [$hsCx + $radius * sin($rad), $hsCy + $radius * cos($rad)];   // 0deg = bottom
};
$hsFmt     = function (float $n): string { return number_format($n, 1, '.', ''); };
[$sx, $sy] = $hsPoint(-$hsMax, $hsR);
[$ex, $ey] = $hsPoint($hsMax, $hsR);
[$hx, $hy] = $hsPoint($hv * $hsMax / 100, $hsR);
$hsLabels  = [[-150, 'Radical left'], [-75, 'Left'], [0, 'Centrist'], [75, 'Right'], [150, 'Alt-right']];
?>
<div class="horseshoe" data-horseshoe>
    <svg class="horseshoe-svg" viewBox="0 0 300 260" role="slider" tabindex="0"
         aria-label="Political orientation" aria-valuemin="-100" aria-valuemax="100"
         aria-valuenow="<?= $hv ?>" aria-valuetext="<?= h(political_label($hv)) ?>">
        <path class="horseshoe-track"
              d="M<?= $hsFmt($sx) ?>,<?= $hsFmt($sy) ?> A<?= $hsR ?>,<?= $hsR ?> 0 1 0 <?= $hsFmt($ex) ?>,<?= $hsFmt($ey) ?>"/>
        <?php foreach ($hsLabels as [$deg, $text]):
            [$tx, $ty] = $hsPoint($deg, $hsR);
            [$lx, $ly] = $hsPoint($deg, $hsR + 24);
            $s = sin(deg2rad($deg));
            $anchor = $s < -0.3 ? 'end' : ($s > 0.3 ? 'start' : 'middle'); ?>
            <circle class="horseshoe-tick" cx="<?= $hsFmt($tx) ?>" cy="<?= $hsFmt($ty) ?>" r="3"/>
            <text class="horseshoe-label" x="<?= $hsFmt($lx) ?>" y="<?= $hsFmt($ly) ?>" dy="0.35em" text-anchor="<?= $anchor ?>"><?= h($text) ?></text>
        <?php endforeach; ?>
        <circle class="horseshoe-handle" cx="<?= $hsFmt($hx) ?>" cy="<?= $hsFmt($hy) ?>" r="9"/>
    </svg>
    <input type="hidden" name="political_orientation" id="political_orientation" value="<?= $hv ?>">
    <p class="slider-readout">Currently set to: <strong id="political_label"><?= h(political_label($hv)) ?></strong></p>
</div>