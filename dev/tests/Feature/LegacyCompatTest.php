<?php

use IanHobbs\Slider\SliderBlock;
use Kirby\Cms\App;
use Kirby\Cms\Block;

/**
 * 1.x compatibility surface
 *
 * 2.0.0 renamed the package, the block type, the content keys, the class and
 * the named thumb presets. What a site has already SAVED — its content files,
 * the images it uploaded, templates it maintains — cannot be renamed with it, so
 * each of those is read under both names. This file is the contract for those
 * fallbacks: it fails if one is dropped by accident rather than deliberately
 * when the 1.x line is retired.
 *
 * Option keys are deliberately NOT in that set: the 1.x option prefix is gone,
 * and a site still carrying it in config.php must move those keys to
 * `ianhobbs.slider-block.*` or fall back to the defaults.
 */

// ── Block type ───────────────────────────────────────────────────────────────

test('a block saved as type swiper still resolves to the SliderBlock model', function () {
    $block = Block::factory([
        'content' => sliderContent(),
        'id'      => 'legacy-block-id',
        'type'    => 'swiper',
    ]);

    expect($block)->toBeInstanceOf(SliderBlock::class);
});

test('the 1.x blueprint and snippet names are still registered', function () {
    $blueprints = App::instance()->extensions('blueprints');
    $snippets   = App::instance()->extensions('snippets');

    expect($blueprints)->toHaveKey('blocks/swiper')
                       ->toHaveKey('files/swiper-image');
    expect($snippets)->toHaveKey('blocks/swiper');

    // Aliases, not copies — both names must resolve to the same file, or the
    // two would drift apart on the next blueprint edit.
    expect($blueprints['blocks/swiper'])->toBe($blueprints['blocks/slider']);
    expect($snippets['blocks/swiper'])->toBe($snippets['blocks/slider']);
});

test('the 1.x class name still resolves', function () {
    expect(class_exists('IanHobbs\Swiper\SwiperBlock'))->toBeTrue();
    expect(makeBlock())->toBeInstanceOf('IanHobbs\Swiper\SwiperBlock');
});

// ── Content keys ─────────────────────────────────────────────────────────────

test('the 1.x height keys still drive the fixed height', function () {
    $block = Block::factory([
        'content' => array_merge(sliderContent(), [
            'height'             => '',
            'height_unit'        => '',
            'slider_height'      => '80',
            'slider_height_unit' => 'vh',
        ]),
        'id'      => 'legacy-height-id',
        'type'    => 'swiper',
    ]);

    expect($block->fixedHeight())->toBe('80vh');
    expect($block->blockStyle())->toContain('--slider-block-fixed-height:80vh');
});

test('the v2 height keys win when both are present', function () {
    $block = Block::factory([
        'content' => array_merge(sliderContent(), [
            'height'             => '420',
            'height_unit'        => 'px',
            'slider_height'      => '80',
            'slider_height_unit' => 'vh',
        ]),
        'id'      => 'both-height-id',
        'type'    => 'slider',
    ]);

    expect($block->fixedHeight())->toBe('420px');
});

test('sliderHeight is kept as a deprecated alias of fixedHeight', function () {
    expect(makeBlock(['height' => '600'])->sliderHeight())->toBe('600px');
    expect(makeBlock(['height' => '0'])->sliderHeight())->toBeNull();
});

// ── Named srcsets and thumb presets ──────────────────────────────────────────

test('a site defining only the 1.x srcset names still gets its cropped ladder', function () {
    $default = kirby();
    $default->clone(['options' => [
        'thumbs.srcsets.swiper-horiz' => ['800w' => ['width' => 800]],
    ]]);
    restore_error_handler();
    restore_exception_handler();

    // Fixed-ratio mode — the only path that reads a named srcset. `aspect_ratio`
    // is a 1.x content key the model still honours; native mode builds its own
    // ladder and never looks at site config.
    expect(makeBlock(['aspect_ratio' => '16/9'])->srcsetName())->toBe('swiper-horiz');

    $default->clone();
    restore_error_handler();
    restore_exception_handler();
});

test('the v2 srcset name wins when the site defines both', function () {
    $default = kirby();
    $default->clone(['options' => [
        'thumbs.srcsets.swiper-horiz' => ['800w' => ['width' => 800]],
        'thumbs.srcsets.slider-horiz' => ['900w' => ['width' => 900]],
    ]]);
    restore_error_handler();
    restore_exception_handler();

    expect(makeBlock(['aspect_ratio' => '16/9'])->srcsetName())->toBe('slider-horiz');

    $default->clone();
    restore_error_handler();
    restore_exception_handler();
});

test('a site defining only the 1.x lqip preset still gets its placeholder', function () {
    $default = kirby();
    $default->clone(['options' => [
        'thumbs.presets.swiper-lqip-horiz' => ['width' => 48, 'blur' => 4],
    ]]);
    restore_error_handler();
    restore_exception_handler();

    expect(makeBlock(['aspect_ratio' => '16/9'])->lqipPreset())->toBe('swiper-lqip-horiz');

    $default->clone();
    restore_error_handler();
    restore_exception_handler();
});

test('with nothing defined the v2 names are used', function () {
    expect(makeBlock(['aspect_ratio' => '16/9'])->srcsetName())->toBe('slider-horiz');
    expect(makeBlock(['aspect_ratio' => '16/9'])->lqipPreset())->toBe('slider-lqip-horiz');
});

// ── Frontend bundle ──────────────────────────────────────────────────────────

test('the built bundle still initialises 1.x markup', function () {
    $js = file_get_contents(__DIR__ . '/../../../assets/dist/slider-block.js');

    // A site running a forked copy of the 1.x snippet emits the old class and
    // data attribute; without these the carousels would silently never start.
    expect($js)->toContain('.swiper-block')
               ->toContain('swiperConfig')
               ->toContain('initSwiperBlocks');
});
