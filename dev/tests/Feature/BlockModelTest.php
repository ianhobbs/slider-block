<?php

/**
 * SwiperBlock model tests
 *
 * Verifies the computed values the snippet relies on — JS config, responsive
 * `sizes`, aspect CSS and thumb presets — in isolation, without rendering HTML.
 */

use IanHobbs\Swiper\SwiperBlock;

test('swiper blocks resolve to the SwiperBlock model', function () {
    expect(makeBlock())->toBeInstanceOf(SwiperBlock::class);
});

// ── jsConfig ────────────────────────────────────────────────────────────────

test('jsConfig returns valid JSON of Swiper options', function () {
    $data = json_decode(makeBlock([
        'effect'          => 'fade',
        'slides_per_view' => '3',
        'speed'           => '800',
        'loop'            => 'true',
    ])->jsConfig(), true);

    expect($data)->toBeArray();
    expect($data['effect'])->toBe('fade');
    expect($data['slidesPerView'])->toBe(3);          // cast to int
    expect($data['speed'])->toBe(800);
    expect($data['loop'])->toBeTrue();                 // toggle → bool
});

test('jsConfig reports whether the block has an explicit height', function () {
    // swiper-block.js turns autoHeight off when this is true. With a Fixed
    // Height the slides are height:100% of the wrapper, so letting autoHeight
    // size that wrapper from the active slide is circular — and with `observer`
    // on, each write triggers another update and the height oscillates. That
    // feedback loop was the mobile flicker.
    expect(json_decode(makeBlock()->jsConfig(), true)['fixedHeight'])->toBeFalse();
    expect(json_decode(makeBlock(['slider_height' => '0'])->jsConfig(), true)['fixedHeight'])->toBeFalse();

    foreach (['px', 'vh', 'svh'] as $unit) {
        $data = json_decode(makeBlock([
            'slider_height'      => '600',
            'slider_height_unit' => $unit,
        ])->jsConfig(), true);
        expect($data['fixedHeight'])->toBeTrue();
    }
});

test('jsConfig keeps slidesPerView "auto" as a string', function () {
    $data = json_decode(makeBlock(['slides_per_view' => 'auto'])->jsConfig(), true);
    expect($data['slidesPerView'])->toBe('auto');
});

test('jsConfig includes creativeEffect only for the creative effect', function () {
    $plain = json_decode(makeBlock(['effect' => 'slide'])->jsConfig(), true);
    $creative = json_decode(makeBlock(['effect' => 'creative'])->jsConfig(), true);

    expect($plain)->not->toHaveKey('creativeEffect');
    expect($creative)->toHaveKey('creativeEffect');
});

// ── imgSizes (responsive sizes hint) ──────────────────────────────────────────

test('imgSizes reflects slidesPerView and the gap', function () {
    expect(makeBlock(['slides_per_view' => '1'])->imgSizes())->toBe('100vw');
    expect(makeBlock(['slides_per_view' => '3'])->imgSizes())->toBe('calc(100vw / 3)');
    expect(makeBlock(['slides_per_view' => '3', 'space_between' => '16'])->imgSizes())
        ->toBe('calc((100vw - 32px) / 3)');
    expect(makeBlock(['slides_per_view' => 'auto'])->imgSizes())->toBe('calc(100vw / 3)');
});

test('a block with no layout field behaves as a full-width row of its own', function () {
    // Blocks fields, ad-hoc blocks, previews: no column to detect.
    expect(makeBlock()->columnSpan())->toBe(12);
    expect(makeBlock()->isRowDuplicate())->toBeFalse();
});

test('Column Width overrides the detected column and is clamped to twelfths', function () {
    expect(makeBlock(['column_width' => '6'])->columnSpan())->toBe(6);
    expect(makeBlock(['column_width' => '2'])->columnSpan())->toBe(2);

    // Out of range or nonsense falls back rather than producing absurd sizes.
    expect(makeBlock(['column_width' => '99'])->columnSpan())->toBe(12);
    expect(makeBlock(['column_width' => '0'])->columnSpan())->toBe(1);
    expect(makeBlock(['column_width' => 'half'])->columnSpan())->toBe(12);
});

test('imgSizes narrows with the column and keeps the stacked size first', function () {
    // Part-width columns stack full-width on small screens, so two candidates.
    expect(makeBlock(['column_width' => '6'])->imgSizes())
        ->toBe('(max-width: 768px) 100vw, 50vw');

    expect(makeBlock(['column_width' => '4', 'slides_per_view' => '2'])->imgSizes())
        ->toBe('(max-width: 768px) calc(100vw / 2), calc(33.3333vw / 2)');

    expect(makeBlock(['column_width' => '8', 'slides_per_view' => '3', 'space_between' => '16'])->imgSizes())
        ->toBe('(max-width: 768px) calc((100vw - 32px) / 3), calc((66.6667vw - 32px) / 3)');

    // Full width stays a single candidate — nothing to stack.
    expect(makeBlock(['column_width' => '12'])->imgSizes())->toBe('100vw');
});

test('the stacking breakpoint is configurable', function () {
    $default = kirby();
    $default->clone(['options' => ['ianhobbs.kirby-slider-block.stackBreakpoint' => '60rem']]);
    restore_error_handler();
    restore_exception_handler();

    expect(makeBlock(['column_width' => '6'])->imgSizes())
        ->toBe('(max-width: 60rem) 100vw, 50vw');

    $default->clone();
    restore_error_handler();
    restore_exception_handler();
});

// ── native ratio ──────────────────────────────────────────────────────────────

test('blocks are native-ratio now that the aspect ratio field is gone', function () {
    expect(makeBlock()->isNative())->toBeTrue();
});

// ── aspectStyle ───────────────────────────────────────────────────────────────

test('aspectStyle is empty in native mode with no explicit height', function () {
    // Native mode sets no container ratio — each figure carries its own
    // aspect-ratio inline, from the image's real dimensions (see the snippet).
    expect(makeBlock()->aspectStyle())->toBe('');
});

// ── sliderHeight (explicit container height) ──────────────────────────────────

test('sliderHeight returns a CSS length, or null when auto (0)', function () {
    expect(makeBlock(['slider_height' => '0'])->sliderHeight())->toBeNull();
    expect(makeBlock(['slider_height' => '600'])->sliderHeight())->toBe('600px');
    expect(makeBlock(['slider_height' => '80', 'slider_height_unit' => 'vh'])->sliderHeight())->toBe('80vh');
    // Unknown unit falls back to px
    expect(makeBlock(['slider_height' => '50', 'slider_height_unit' => 'bogus'])->sliderHeight())->toBe('50px');
});

test('aspectStyle carries only the explicit container height', function () {
    // Auto (0) emits nothing at all
    expect(makeBlock(['slider_height' => '0'])->aspectStyle())->toBe('');

    expect(makeBlock(['slider_height' => '720'])->aspectStyle())
        ->toBe('--swiper-block-fixed-height:720px');

    // Chosen unit is carried through
    expect(makeBlock(['slider_height' => '80', 'slider_height_unit' => 'vh'])->aspectStyle())
        ->toBe('--swiper-block-fixed-height:80vh');
});

// ── Easing ───────────────────────────────────────────────────────────────────

test('easing maps to a CSS timing function and rejects anything else', function () {
    // Swiper has no JS easing option — this drives the custom property its own
    // stylesheet reads on .swiper-wrapper.
    expect(makeBlock(['easing' => 'smooth'])->easingStyle())
        ->toBe('--swiper-wrapper-transition-timing-function:cubic-bezier(0.22, 1, 0.36, 1)');
    expect(makeBlock(['easing' => 'linear'])->easingStyle())
        ->toBe('--swiper-wrapper-transition-timing-function:linear');

    // 'ease' IS Swiper's default, so say nothing rather than restate it.
    expect(makeBlock(['easing' => 'ease'])->easingStyle())->toBe('');

    // Unset defaults to smooth; junk falls back rather than reaching the
    // style attribute, where it could close the quote.
    expect(makeBlock()->easingStyle())->toContain('cubic-bezier');
    expect(makeBlock(['easing' => 'x;background:url(evil)'])->easingStyle())
        ->toBe('--swiper-wrapper-transition-timing-function:cubic-bezier(0.22, 1, 0.36, 1)');
});

test('blockStyle joins the height and easing declarations', function () {
    $both = makeBlock(['slider_height' => '420', 'easing' => 'linear'])->blockStyle();
    expect($both)->toBe('--swiper-block-fixed-height:420px;--swiper-wrapper-transition-timing-function:linear');

    // No height, Swiper-default easing: nothing to emit, and no stray semicolon.
    expect(makeBlock(['slider_height' => '0', 'easing' => 'ease'])->blockStyle())->toBe('');

    // Easing alone must not be prefixed by an empty aspect segment.
    expect(makeBlock(['slider_height' => '0', 'easing' => 'linear'])->blockStyle())
        ->toBe('--swiper-wrapper-transition-timing-function:linear');
});

// ── Mobile height override ───────────────────────────────────────────────────

test('mobileHeight needs the px unit, the toggle and a non-zero number', function () {
    $on = ['slider_height' => '720', 'mobile_height_enable' => 'true', 'mobile_height' => '320'];

    expect(makeBlock($on)->mobileHeight())->toBe('320px');

    // Each precondition dropped in turn — the toggle alone is not enough, since
    // an editor can leave it on after clearing the number or changing the unit.
    expect(makeBlock([...$on, 'mobile_height_enable' => 'false'])->mobileHeight())->toBeNull();
    expect(makeBlock([...$on, 'mobile_height' => '0'])->mobileHeight())->toBeNull();
    expect(makeBlock([...$on, 'slider_height_unit' => 'vh'])->mobileHeight())->toBeNull();
    expect(makeBlock([...$on, 'slider_height_unit' => 'svh'])->mobileHeight())->toBeNull();

    // Nothing configured at all
    expect(makeBlock()->mobileHeight())->toBeNull();
});

test('aspectStyle emits the mobile height only alongside a fixed height', function () {
    expect(makeBlock([
        'slider_height'        => '720',
        'mobile_height_enable' => 'true',
        'mobile_height'        => '320',
    ])->aspectStyle())->toBe('--swiper-block-fixed-height:720px;--swiper-block-mobile-height:320px');

    // With no desktop height there is nothing to override, so the mobile value
    // stays out of the style attribute even when the toggle and number are set.
    expect(makeBlock([
        'slider_height'        => '0',
        'mobile_height_enable' => 'true',
        'mobile_height'        => '320',
    ])->aspectStyle())->toBe('');
});

// ── thumb presets ─────────────────────────────────────────────────────────────

test('native mode builds thumbs inline so no site config is required', function () {
    $block = makeBlock();

    // Width-only srcset — no crop, so the source ratio survives.
    $srcset = $block->fallbackSrcset(PHP_INT_MAX);
    expect($srcset)->toBeArray()->toHaveKeys(['640w', '900w', '1200w', '1600w', '1920w']);
    expect($srcset['1920w'])->not->toHaveKey('height');
    expect($srcset['1920w']['format'])->toBe('webp');

    // LQIP is width-only too, for the same reason.
    expect($block->lqipPreset())->toBeArray()->toHaveKey('blur');
    expect($block->lqipPreset())->not->toHaveKey('height');
});

test('capLadder relabels the first oversized step and drops the rest', function () {
    $ladder = [
        '640w'  => ['width' =>  640, 'format' => 'webp'],
        '900w'  => ['width' =>  900, 'format' => 'webp'],
        '1200w' => ['width' => 1200, 'format' => 'webp'],
        '1600w' => ['width' => 1600, 'format' => 'webp'],
    ];

    // Kirby writes the descriptor from the array key and never measures the
    // thumb, while the darkroom refuses to upscale — so past the master every
    // step would be the same file under a wider claim.
    $capped = SwiperBlock::capLadder($ladder, 1000);

    expect(array_keys($capped))->toBe(['640w', '900w', '1000w']);
    expect($capped['1000w']['width'])->toBe(1000);

    // The replaced step's other options survive the relabelling.
    expect($capped['1000w']['format'])->toBe('webp');

    // A master wider than the whole ladder leaves it untouched.
    expect(array_keys(SwiperBlock::capLadder($ladder, 4000)))
        ->toBe(['640w', '900w', '1200w', '1600w']);

    // An exact match is not a cap — it passes through on its own key.
    expect(array_keys(SwiperBlock::capLadder($ladder, 1200)))
        ->toBe(['640w', '900w', '1200w']);
});

test('the img src fallback is a middle rung, not the heaviest file', function () {
    // <img src> is what a client ignoring srcset downloads, so pointing it at
    // the top of the ladder served the heaviest file to the least capable
    // reader. The middle step is already on the ladder — no extra thumb.
    expect(makeBlock()->baseThumbOptions(PHP_INT_MAX))->toBe([
        'width' => 1200, 'format' => 'webp', 'quality' => 80,
    ]);

    // Capped first, so the rung is chosen from what the master can actually
    // deliver: 640/900/1000 for a 1000px master, middle is 900.
    expect(makeBlock()->baseThumbOptions(1000)['width'])->toBe(900);
});

test('avif is offered as a source and webp rides on the img itself', function () {
    $block = makeBlock();

    expect($block->formats())->toBe(['avif', 'webp']);
    expect($block->fallbackFormat())->toBe('webp');

    // The fallback format is deliberately absent from the sources: it is
    // already on the <img>, and a <source> for it would shadow that.
    $sources = $block->srcsets(PHP_INT_MAX);
    expect(array_keys($sources))->toBe(['image/avif']);
    expect($sources['image/avif']['1920w']['format'])->toBe('avif');
});

test('a fixed-ratio block reads the named per-orientation srcset', function () {
    // The site defines no swiper-horiz/-vert here, so there is nothing to
    // resolve — the model says so rather than inventing a ladder.
    $block = makeBlock(['aspect_ratio' => '16/9']);

    expect($block->fallbackSrcset(PHP_INT_MAX))->toBeNull();
    expect($block->baseThumbOptions(PHP_INT_MAX))->toBeNull();

    // And no <source> either: a per-format ladder is opt-in by defining
    // swiper-horiz-avif, so an unconfigured site gets a plain <img>.
    expect($block->srcsets(PHP_INT_MAX))->toBe([]);
});

// ── Caption typography & colour ───────────────────────────────────────────────

test('caption size classes default and reject values outside the scale', function () {
    expect(makeBlock()->headingSizeClass())->toBe('text-sm');
    expect(makeBlock()->subtextSizeClass())->toBe('text-lg');

    expect(makeBlock(['heading_size' => 'text-xs'])->headingSizeClass())->toBe('text-xs');
    expect(makeBlock(['subtext_size' => 'text-sm'])->subtextSizeClass())->toBe('text-sm');

    // Anything off the list falls back — the value lands in a class attribute.
    expect(makeBlock(['heading_size' => 'text-9xl'])->headingSizeClass())->toBe('text-sm');
    expect(makeBlock(['heading_size' => 'foo" onload="x'])->headingSizeClass())->toBe('text-sm');
    expect(makeBlock(['subtext_size' => 'text-9xl'])->subtextSizeClass())->toBe('text-lg');
});

test('caption font class defaults to font-sans and rejects anything off the list', function () {
    expect(makeBlock()->captionFontClass())->toBe('font-sans');

    foreach (SwiperBlock::CAPTION_FONTS as $font) {
        expect(makeBlock(['caption_font' => $font])->captionFontClass())->toBe($font);
    }

    expect(makeBlock(['caption_font' => 'font-comic'])->captionFontClass())->toBe('font-sans');
    expect(makeBlock(['caption_font' => 'x" onload="y'])->captionFontClass())->toBe('font-sans');
});

test('verticalPosition accepts the three placements and defaults to middle', function () {
    expect(SwiperBlock::verticalPosition('top'))->toBe('top');
    expect(SwiperBlock::verticalPosition('bottom'))->toBe('bottom');
    expect(SwiperBlock::verticalPosition(null))->toBe('middle');
    expect(SwiperBlock::verticalPosition(''))->toBe('middle');
    expect(SwiperBlock::verticalPosition('sideways'))->toBe('middle');
});

test('cssColor passes hex and functional colours, drops anything else', function () {
    expect(SwiperBlock::cssColor('#fff'))->toBe('#fff');
    expect(SwiperBlock::cssColor('#ffcc00'))->toBe('#ffcc00');
    expect(SwiperBlock::cssColor('#ffcc0080'))->toBe('#ffcc0080');
    expect(SwiperBlock::cssColor(' rgba(0, 0, 0, 0.5) '))->toBe('rgba(0, 0, 0, 0.5)');
    expect(SwiperBlock::cssColor('hsl(210 40% 50%)'))->toBe('hsl(210 40% 50%)');

    expect(SwiperBlock::cssColor(null))->toBeNull();
    expect(SwiperBlock::cssColor(''))->toBeNull();
    expect(SwiperBlock::cssColor('red'))->toBeNull();          // keyword — not stored by the field
    expect(SwiperBlock::cssColor('#12345'))->toBeNull();       // malformed hex
    expect(SwiperBlock::cssColor('url(javascript:alert(1))'))->toBeNull();
    expect(SwiperBlock::cssColor('#fff;background:url(x)'))->toBeNull();
});

// ── uid ───────────────────────────────────────────────────────────────────────

test('uid is a stable sb-prefixed id', function () {
    expect(makeBlock()->uid())->toMatch('/^sb-[a-f0-9]{8}$/');
});
