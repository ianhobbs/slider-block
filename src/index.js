/**
 * Kirby Panel — Slider Block component registration
 *
 * Compiled by kirbyup into index.js / index.css in the plugin root,
 * which Kirby auto-loads when the plugin is active.
 */
import SliderBlock from './SliderBlock.vue';

window.panel.plugin('ianhobbs/slider-block', {

  blocks: {
    slider: SliderBlock,

    // 1.x block type. Content saved before 2.0.0 still says `type: swiper`, so
    // the Panel needs a preview component registered under that name too or
    // those blocks fall back to the default renderer. Remove with the 1.x line.
    swiper: SliderBlock,
  },
});
