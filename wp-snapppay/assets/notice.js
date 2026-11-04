jQuery(function ($) {
  const boxes = $('.wp-snapppay-notice');
  boxes.each(function () {
    $(this).data('initial-amount', $(this).find('.wp-snapppay-notice__amount').first().html());
  });
  function matching(form) {
    const id = String($(form).data('product_id') || $(form).find('[name="product_id"]').val() || '');
    return boxes.filter(function () { return this.dataset.productId === id; });
  }
  $(document).on('found_variation', 'form.variations_form', function (event, variation) {
    const targets = matching(this);
    const valid = variation && variation.is_purchasable !== false && variation.is_in_stock !== false && Number(variation.display_price) > 0;
    targets.prop('hidden', !valid).toggle(valid);
    if (valid && variation.wp_snapppay_installment) {
      targets.find('.wp-snapppay-notice__amount').html(variation.wp_snapppay_installment);
    }
  });
  $(document).on('reset_data', 'form.variations_form', function () {
    matching(this).each(function () {
      $(this).prop('hidden', false).show().find('.wp-snapppay-notice__amount').html($(this).data('initial-amount'));
    });
  });
  $(document).on('hide_variation', 'form.variations_form', function () {
    const form = $(this);
    if (form.find('.variations select').toArray().every(function (el) { return !!el.value; })) {
      matching(this).prop('hidden', true).hide();
    }
  });
  // Recover a variation selected before a builder rendered this shortcode.
  $('form.variations_form').trigger('check_variations');
});
