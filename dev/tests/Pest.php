<?php

use Kirby\Data\Yaml;

/**
 * Pest configuration
 *
 * Sets the default test suite expectations and helper functions
 * available across all tests.
 */

uses()->beforeAll(function () {
    // Nothing extra — bootstrap.php boots Kirby
})->in('Feature');

/**
 * Create a minimal Kirby Block with slider content.
 * Pass only the fields you need; defaults cover the rest.
 *
 * Structure fields must be YAML-encoded strings — Kirby stores all
 * content as strings and calls toString() before parsing.
 */
function makeBlock(array $content = []): Kirby\Cms\Block
{
    // factory() resolves the registered block model (SliderBlock), so its
    // computed methods (jsConfig, imgSizes, …) are available in tests.
    return Kirby\Cms\Block::factory([
        'content' => sliderContent($content),
        'id'      => 'test-block-id',
        'type'    => 'slider',
    ]);
}

/**
 * The content array for a slider block: blueprint defaults with the given
 * overrides merged in. Shared by makeBlock() and the layout helpers below.
 */
function sliderContent(array $content = []): array
{
    // Encode any array values (structure fields) to YAML strings
    $encoded = [];
    foreach ($content as $key => $value) {
        $encoded[$key] = is_array($value) ? Yaml::encode($value) : $value;
    }

    return array_merge([
            'slides'                  => '',
            'height'                  => '0',
            'height_unit'             => 'px',
            'direction'               => 'horizontal',
            'slides_per_view'         => '1',
            'slides_per_group'        => '1',
            'space_between'           => '0',
            'centered_slides'         => 'false',
            'initial_slide'           => '0',
            'effect'                  => 'slide',
            'speed'                   => '600',
            'loop'                    => 'false',
            'autoplay'                => 'false',
            'autoplay_delay'          => '4000',
            'autoplay_pause_on_hover' => 'false',
            'free_mode'               => 'false',
            'free_mode_momentum'      => 'false',
            'show_navigation'         => 'true',
            'show_pagination'         => 'true',
            'pagination_type'         => 'bullets',
            'dynamic_bullets'         => 'false',
            'keyboard_control'        => 'false',
            'mousewheel'              => 'false',
            'grab_cursor'             => 'false',
            'simulate_touch'          => 'true',
            'threshold'               => '5',
            'long_swipes'             => 'true',
            'resistance'              => 'true',
    ], $encoded);
}

/**
 * Render the slider snippet for a block and return the HTML string.
 */
function renderBlock(Kirby\Cms\Block $block): string
{
    ob_start();
    snippet('blocks/slider', ['block' => $block]);
    return ob_get_clean() ?: '';
}

/**
 * Build a Layouts collection shaped like the host site's layout field — the
 * real context the block ships into (see fixtures/site/blueprints/fields/layout.yml).
 *
 * $columns is a list of ['width' => '1/3', 'blocks' => [ [block content overrides] ]];
 * every block is a slider block, since that's what these tests exercise.
 */
function makeLayout(array $columns, string $theme = 'plain-blocks-padded'): Kirby\Cms\Layouts
{
    $builtColumns = [];

    foreach ($columns as $i => $column) {
        $blocks = [];

        foreach (($column['blocks'] ?? []) as $j => $content) {
            $blocks[] = [
                // Distinct ids — uid() hashes them, so colliding ids would mean
                // two blocks sharing one DOM id and one Swiper instance.
                'id'      => "block-{$i}-{$j}",
                'type'    => 'slider',
                'content' => sliderContent($content),
            ];
        }

        $builtColumns[] = [
            'id'     => "column-{$i}",
            'width'  => $column['width'] ?? '1/1',
            'blocks' => $blocks,
        ];
    }

    return makeLayoutsField([[
        'id'      => 'layout-1',
        'attrs'   => ['theme' => $theme],
        'columns' => $builtColumns,
    ]]);
}

/**
 * One layout row holding a single block — the supported arrangement, and the
 * shape most layout tests need. Returns a row in makeLayoutRows() format.
 */
function sliderRow(string $heading, string $width = '1/1', array $content = []): array
{
    return [[
        'width'  => $width,
        'blocks' => [$content + ['slides' => [[
            'heading'          => $heading,
            'image'            => [],
            'subtext'          => '',
            'link'             => '',
            'link_text'        => '',
            'content_position' => 'center',
        ]]]],
    ]];
}

/**
 * Turn raw layout data into Layouts *via a content field*, the way a real page
 * does. Going through the field matters: it's what gives each block a parent
 * field, which is how the model finds its own layout column again (see
 * SliderBlock::layoutContext()). Layouts::factory() alone leaves that null.
 */
function makeLayoutsField(array $layouts): Kirby\Cms\Layouts
{
    $field = new Kirby\Content\Field(page('home'), 'layout', json_encode($layouts));

    return $field->toLayouts();
}

/**
 * Several layout rows in one field — the common "blocks down a page" case,
 * and the only way to exercise the one-block-per-row rule across rows.
 * Each entry is a list of columns, in makeLayout()'s column format.
 */
function makeLayoutRows(array $rows, string $theme = 'plain-blocks-padded'): Kirby\Cms\Layouts
{
    $built = [];

    foreach ($rows as $r => $columns) {
        $builtColumns = [];

        foreach ($columns as $i => $column) {
            $blocks = [];

            foreach (($column['blocks'] ?? []) as $j => $content) {
                $blocks[] = [
                    'id'      => "block-{$r}-{$i}-{$j}",
                    'type'    => 'slider',
                    'content' => sliderContent($content),
                ];
            }

            $builtColumns[] = [
                'id'     => "column-{$r}-{$i}",
                'width'  => $column['width'] ?? '1/1',
                'blocks' => $blocks,
            ];
        }

        $built[] = [
            'id'      => "layout-{$r}",
            'attrs'   => ['theme' => $theme],
            'columns' => $builtColumns,
        ];
    }

    return makeLayoutsField($built);
}

/**
 * The first slider block in a Layouts collection, as the model — for asserting
 * on computed values (columnSpan, imgSizes) that never reach the markup when a
 * fixture slide has no image.
 */
function firstSlider(Kirby\Cms\Layouts $layouts): IanHobbs\Slider\SliderBlock
{
    foreach ($layouts as $layout) {
        foreach ($layout->columns() as $column) {
            foreach ($column->blocks() as $block) {
                if ($block->type() === 'slider') {
                    return $block;
                }
            }
        }
    }

    throw new RuntimeException('No slider block in the given layouts');
}

/**
 * Render layouts the way a host site's layout template does: a themed wrapper
 * per row, a width-classed wrapper per column, then the column's blocks.
 */
function renderLayout(Kirby\Cms\Layouts $layouts): string
{
    $html = '';

    foreach ($layouts as $layout) {
        $html .= '<div class="' . $layout->attrs()->theme()->or('plain-blocks')->value() . '">';

        foreach ($layout->columns() as $column) {
            $html .= '<div class="column column--span-' . $column->span() . '">';
            $html .= $column->blocks()->toHtml();
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    return $html;
}

/**
 * A one-block row whose slides all use the fixture image (slide.jpg, 1000x600).
 *
 * The image tests need a real master: every ladder is capped against the file's
 * true width, and the `<picture>` markup only renders when a slide resolves to
 * a file. Returns a row in makeLayoutRows() format so the block keeps a parent
 * field, which is what lets `image()->toFiles()` resolve against page('home').
 */
function sliderImageRow(int $slides = 1, array $content = [], string $width = '1/1'): array
{
    $built = [];

    for ($i = 1; $i <= $slides; $i++) {
        $built[] = [
            'heading'          => 'Slide ' . $i,
            'image'            => ['slide.jpg'],
            'subtext'          => '',
            'link'             => '',
            'link_text'        => '',
            'content_position' => 'center',
        ];
    }

    return [[
        'width'  => $width,
        'blocks' => [$content + ['slides' => $built]],
    ]];
}

/** The rendered HTML of a one-block row built by sliderImageRow(). */
function renderImageRow(int $slides = 1, array $content = []): string
{
    return renderBlock(firstSlider(makeLayoutRows([sliderImageRow($slides, $content)])));
}
