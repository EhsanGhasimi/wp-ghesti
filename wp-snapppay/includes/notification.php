<?php
defined( 'ABSPATH' ) || exit;

final class WP_Snapppay_Notification {
 const OPTION = 'wp_snapppay_notification';

 public static function init() {
  add_shortcode( 'wp_snapppay_notice', array( __CLASS__, 'render' ) );
  add_shortcode( 'webinew_installments', array( __CLASS__, 'render' ) );
  add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation' ), 10, 3 );
  add_action( 'admin_enqueue_scripts', static function ( $hook ) {
   if ( 'woocommerce_page_wp-snapppay' === $hook ) {
    wp_enqueue_media();
    wp_enqueue_script( 'wp-snapppay-notice-admin', plugins_url( '../assets/notice-admin.js', __FILE__ ), array( 'jquery' ), '1.2.1', true );
   }
  } );
 }

 public static function settings() {
  return wp_parse_args( get_option( self::OPTION, array() ), array(
   'enabled' => 'yes', 'image' => 'https://raysez.ir/wp-content/uploads/2026/10/snapppay.webp', 'link' => '', 'alt' => 'اسنپ‌پی',
   'image2' => 'https://raysez.ir/wp-content/uploads/2026/10/digipay.webp', 'link2' => '', 'alt2' => 'دیجی‌پی',
   'content' => '<p><strong>هر قسط: {installment}</strong></p><p>پرداخت در ۴ قسط ماهانه، بدون چک و ضامن</p><p>ثبت سفارش اسنپ پی از طریق <a href="https://wa.me/989361810430">واتس اپ: <bdi>09361810430</bdi></a> قابل انجام هست.</p>',
  ) );
 }

 public static function save() {
  // The enclosing settings page has already checked capability and nonce.
  if ( isset( $_POST['wp_snapppay_save'] ) ) {
   check_admin_referer( 'wp_snapppay_save' );
   if ( current_user_can( 'manage_woocommerce' ) ) {
    $saved = array( 'enabled' => isset( $_POST['notice_enabled'] ) ? 'yes' : 'no' );
    foreach ( array( 'image', 'link', 'alt', 'image2', 'link2', 'alt2', 'content' ) as $key ) {
     $value = isset( $_POST[ 'notice_' . $key ] ) && is_string( $_POST[ 'notice_' . $key ] ) ? wp_unslash( $_POST[ 'notice_' . $key ] ) : '';
     $saved[ $key ] = in_array( $key, array( 'image', 'link', 'image2', 'link2' ), true ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : ( 'content' === $key ? wp_kses_post( $value ) : sanitize_text_field( $value ) );
    }
    update_option( self::OPTION, $saved );
   }
  }
 }

 public static function editor() {
  $s = self::settings();
  ?>
  <h2>باکس اطلاع‌رسانی اسنپ‌پی</h2>
  <p>شورت‌کد <code>[wp_snapppay_notice]</code> را در نوشته، برگه یا ابزارک شورت‌کد المنتور قرار دهید. نمایش باکس مستقل از فعال‌سازی هزینه اقساطی است.</p>
  <p><label><input type="checkbox" name="notice_enabled" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>> نمایش باکس شورت‌کد</label></p>
  <p><label for="notice-image">آدرس تصویر یا لوگو</label><br><input class="large-text" dir="ltr" type="url" id="notice-image" name="notice_image" value="<?php echo esc_attr( $s['image'] ); ?>"> <button type="button" class="button" id="notice-select-image">انتخاب از کتابخانه رسانه</button></p>
  <p><label for="notice-link">لینک تصویر (اختیاری)</label><br><input class="large-text" dir="ltr" type="url" id="notice-link" name="notice_link" value="<?php echo esc_attr( $s['link'] ); ?>"></p>
  <p><label for="notice-alt">متن جایگزین تصویر</label><br><input class="regular-text" id="notice-alt" name="notice_alt" value="<?php echo esc_attr( $s['alt'] ); ?>"></p>
  <p><label for="notice-image2">آدرس تصویر دوم (اختیاری)</label><br><input class="large-text" dir="ltr" type="url" id="notice-image2" name="notice_image2" value="<?php echo esc_attr( $s['image2'] ); ?>"> <button type="button" class="button" id="notice-select-image2">انتخاب از کتابخانه رسانه</button></p>
  <p><label for="notice-link2">لینک تصویر دوم</label><br><input class="large-text" dir="ltr" type="url" id="notice-link2" name="notice_link2" value="<?php echo esc_attr( $s['link2'] ); ?>"></p>
  <p><label for="notice-alt2">متن جایگزین تصویر دوم</label><br><input class="regular-text" id="notice-alt2" name="notice_alt2" value="<?php echo esc_attr( $s['alt2'] ); ?>"></p>
  <p>شورت‌کد قبلی <code>[webinew_installments]</code> نیز پشتیبانی می‌شود؛ کد PHP قبلی را غیرفعال کنید تا تداخل نداشته باشد. باکس فقط برای محصول قابل خرید و موجود نمایش داده می‌شود. مبلغ هر قسط با درصد افزایش همین افزونه و تقسیم بر ۴ محاسبه می‌شود. اگر افزایش هزینه غیرفعال باشد، درصد صفر است.</p>
  <p>برای درج مبلغ خودکار، <code>{installment}</code> را در متن قرار دهید. اگر این عبارت در محتوای ذخیره‌شده قبلی نباشد، ردیف «هر قسط» خودکار اضافه می‌شود. قیمت متغیر ابتدا حداقل قیمت و پس از انتخاب، قیمت همان تنوع است. مبلغ نمایشی برآورد یک عدد کالا، بدون ارسال و کوپن سبد است. افزایش فقط برای اسنپ‌پی است و مبلغ دیجی‌پی را نشان نمی‌دهد.</p>
  <p><strong>تمام متن‌های داخل باکس، از جمله ردیف واتس‌اپ</strong> — متن، شماره و لینک واتس‌اپ را از همین ویرایشگر تغییر دهید. تب «متن/کد» برای HTML است.</p>
  <?php
  wp_editor( $s['content'], 'snapppay_notice_content', array( 'textarea_name' => 'notice_content', 'textarea_rows' => 9, 'media_buttons' => true, 'tinymce' => true, 'quicktags' => true ) );
 }

 public static function amount( $product ) {
  $settings = WP_Snapppay::settings();
  $percent = 'yes' === $settings['enabled'] ? max( 0, (float) $settings['percent'] ) : 0;
  $fee = round( (float) wc_get_price_excluding_tax( $product ) * $percent / 100, wc_get_price_decimals() );
  return wc_price( ( (float) wc_get_price_to_display( $product ) + $fee ) / 4 );
 }

 public static function variation( $data, $parent, $variation ) {
  $data['wp_snapppay_installment'] = self::amount( $variation );
  return $data;
 }

 public static function render() {
  $s = self::settings();
  if ( 'yes' !== $s['enabled'] ) {
   return '';
  }
  global $product;
  $current = $product instanceof WC_Product ? $product : wc_get_product( get_queried_object_id() );
  if ( ! $current instanceof WC_Product || ! $current->is_purchasable() || ! $current->is_in_stock() ) {
   return '';
  }
  $priced = $current;
  if ( $current->is_type( 'variable' ) ) {
   $prices = $current->get_variation_prices( true );
   foreach ( $prices['price'] as $id => $price ) {
    $candidate = wc_get_product( $id );
    if ( $candidate && $candidate->is_purchasable() && $candidate->is_in_stock() ) {
     $priced = $candidate;
     break;
    }
   }
   if ( $priced === $current ) { return ''; }
  }
  if ( (float) $priced->get_price() <= 0 ) { return ''; }
  $amount = '<span class="wp-snapppay-notice__amount">' . self::amount( $priced ) . '</span>';
  $content = $s['content'];
  if ( false === strpos( $content, '{installment}' ) ) { $content = '<p><strong>هر قسط: {installment}</strong></p>' . $content; }
  $content = str_replace( '{installment}', $amount, wp_kses_post( wpautop( $content ) ) );
  wp_enqueue_script( 'wp-snapppay-notice', plugins_url( '../assets/notice.js', __FILE__ ), array( 'jquery', 'wc-add-to-cart-variation' ), '1.2.1', true );
  // Inline scoped styles also work when builders render shortcodes after wp_head.
  $html = '<style>.wp-snapppay-notice{box-sizing:border-box;direction:rtl;display:flex;align-items:center;gap:14px;width:100%;padding:14px 16px;border:1px solid #b8dfff;border-top:3px solid #008cff;border-radius:17px;background:#fff;color:#334155;box-shadow:0 4px 12px #008cff0d;text-align:right;margin:16px 0;font:inherit}.wp-snapppay-notice__image{display:block;width:48px;height:48px;object-fit:contain;flex-shrink:0}.wp-snapppay-notice__logo{flex-shrink:0}.wp-snapppay-notice__content{min-width:0;overflow-wrap:anywhere;flex:1;font-size:13px;line-height:1.9}.wp-snapppay-notice__content p{margin:0 0 5px}.wp-snapppay-notice__content p:last-child{margin-bottom:0}.wp-snapppay-notice__content strong,.wp-snapppay-notice__content a{color:#007de3}.wp-snapppay-notice__content img{max-width:100%;height:auto}@media(max-width:480px){.wp-snapppay-notice{gap:10px;padding:12px}.wp-snapppay-notice__image{width:40px;height:40px}}</style>';
  $html .= '<aside class="wp-snapppay-notice" data-product-id="' . esc_attr( $current->get_id() ) . '" aria-label="اطلاع‌رسانی خرید اقساطی اسنپ‌پی">';
  foreach ( array( '', '2' ) as $suffix ) {
   if ( ! $s[ 'image' . $suffix ] ) { continue; }
   $img = '<img class="wp-snapppay-notice__image" src="' . esc_url( $s[ 'image' . $suffix ] ) . '" alt="' . esc_attr( $s[ 'alt' . $suffix ] ) . '" loading="lazy" width="48" height="48">';
   $html .= $s[ 'link' . $suffix ] ? '<a class="wp-snapppay-notice__logo" href="' . esc_url( $s[ 'link' . $suffix ] ) . '">' . $img . '</a>' : $img;
  }
  return $html . '<div class="wp-snapppay-notice__content">' . $content . '</div></aside>';
 }
}

