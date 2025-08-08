<?php
/**
 * WP-CLI Command to link translated properties.
 *
 * This code should be added to your theme's functions.php file.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {

    /**
     * يربط العقارات المترجمة (عربي/إنجليزي) باستخدام جدول السجل.
     *
     * ## أمثلة
     *
     * # تشغيل أمر الربط
     * wp cob link-properties
     *
     * @when after_wp_load
     */
    function cob_link_properties_cli_command() {

        // 1. التحقق من وجود إضافة Polylang
        if ( ! function_exists( 'pll_save_post_translations' ) ) {
            WP_CLI::error( 'إضافة Polylang غير نشطة. هذا الأمر يتطلب وجودها لتنفيذ الربط.' );
            return;
        }

        global $wpdb;
        $log_table_name = $wpdb->prefix . 'cob_property_import_log';
        $post_type = 'properties'; // نوع المنشور المحدد في السكريبت الأصلي

        // 2. استعلام SQL فعال لجلب أزواج العقارات
        WP_CLI::log( "جارٍ الاستعلام من قاعدة البيانات عن أزواج العقارات لربطها..." );
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT source_id, GROUP_CONCAT(lang, ':', post_id) as posts
             FROM {$log_table_name}
             WHERE post_type = %s AND post_id != 0
             GROUP BY source_id
             HAVING COUNT(id) > 1",
            $post_type
        ) );

        if ( empty( $results ) ) {
            WP_CLI::success( 'لم يتم العثور على أزواج لربطها. قد تكون جميعها مرتبطة بالفعل.' );
            return;
        }

        $total_pairs = count( $results );
        WP_CLI::log( "تم العثور على {$total_pairs} زوجًا من العقارات المحتملة للمعالجة." );

        // 3. إعداد شريط التقدم
        $progress = \WP_CLI\Utils\make_progress_bar( 'ربط العقارات', $total_pairs );
        $linked_count = 0;
        $already_linked_count = 0;
        $error_count = 0;

        // 4. المرور على النتائج وتنفيذ الربط
        foreach ( $results as $row ) {
            $translations = [];
            $post_pairs = explode( ',', $row->posts );

            foreach ( $post_pairs as $pair ) {
                list( $lang, $post_id ) = explode( ':', $pair );
                if ( ! empty( $lang ) && ! empty( $post_id ) ) {
                    $translations[ trim( $lang ) ] = (int) $post_id;
                }
            }

            // تحقق من وجود المنشورين العربي والإنجليزي
            if ( isset( $translations['en'], $translations['ar'] ) ) {
                // تحقق إذا كانت مرتبطة بالفعل لتجنب العمليات غير الضرورية
                $existing_translations = pll_get_post_translations( $translations['en'] );
                if ( isset( $existing_translations['ar'] ) && $existing_translations['ar'] === $translations['ar'] ) {
                    $already_linked_count++;
                } else {
                    // قم بربطهما
                    pll_save_post_translations( $translations );
                    $linked_count++;
                }
            } else {
                $error_count++;
                WP_CLI::warning( "تجاوز source_id '{$row->source_id}':缺少 إدخال عربي أو إنجليزي. الموجود: " . implode( ', ', array_keys( $translations ) ) );
            }

            $progress->tick();
        }

        $progress->finish();

        // 5. التقرير النهائي
        WP_CLI::success( "اكتملت المعالجة بنجاح!" );
        WP_CLI::log( "---------------------------------" );
        WP_CLI::log( "تم ربط: {$linked_count} زوجًا بنجاح." );
        WP_CLI::log( "كانت مرتبطة بالفعل: {$already_linked_count} زوجًا." );
        if ( $error_count > 0 ) {
            WP_CLI::warning( "تم تجاوزها بسبب نقص البيانات: {$error_count} زوجًا." );
        }
        WP_CLI::log( "---------------------------------" );
    }

    // تسجيل الأمر في WP-CLI
    WP_CLI::add_command( 'cob link-properties', 'cob_link_properties_cli_command' );

}