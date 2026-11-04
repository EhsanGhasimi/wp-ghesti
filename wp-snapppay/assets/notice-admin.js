jQuery(function ($) {
  $('#notice-select-image, #notice-select-image2').on('click', function () {
    const target = this.id === 'notice-select-image2' ? '#notice-image2' : '#notice-image';
    const frame = wp.media({ title: 'انتخاب تصویر باکس اسنپ‌پی', library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      $(target).val(frame.state().get('selection').first().toJSON().url);
    });
    frame.open();
  });
});
