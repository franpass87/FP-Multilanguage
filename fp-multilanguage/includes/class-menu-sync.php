<?php
/**
 * Menu synchronization utilities.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Keep English menus in sync with Italian sources.
 *
 * @since 0.2.0
 */
class FPML_Menu_Sync {
        /**
         * Singleton instance.
         *
         * @var FPML_Menu_Sync|null
         */
        protected static $instance = null;

        /**
         * Queue handler.
         *
         * @var FPML_Queue
         */
        protected $queue;

        /**
         * Logger helper.
         *
         * @var FPML_Logger
         */
        protected $logger;

        /**
         * Whether the synchronizer is currently processing.
         *
         * @var bool
         */
        protected $is_syncing = false;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Menu_Sync
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->queue  = FPML_Queue::instance();
                $this->logger = FPML_Logger::instance();

                add_action( 'wp_update_nav_menu', array( $this, 'handle_menu_update' ), 10, 2 );
        }

        /**
         * Sync menu structure whenever the Italian menu is saved.
         *
         * @since 0.2.0
         *
         * @param int   $menu_id   Menu term ID.
         * @param array $menu_data Menu data payload.
         *
         * @return void
         */
        public function handle_menu_update( $menu_id, $menu_data ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( $this->is_syncing ) {
                        return;
                }

                $menu = wp_get_nav_menu_object( $menu_id );

                if ( ! $menu || is_wp_error( $menu ) ) {
                        return;
                }

                if ( get_term_meta( $menu_id, '_fpml_is_translation', true ) ) {
                        return;
                }

                $this->is_syncing = true;

                try {
                        $target_menu = $this->ensure_target_menu( $menu );

                        if ( ! $target_menu ) {
                                return;
                        }

                        $this->sync_menu_items( $menu, $target_menu );
                } finally {
                        $this->is_syncing = false;
                }
        }

        /**
         * Ensure the English counterpart menu exists.
         *
         * @since 0.2.0
         *
         * @param WP_Term $menu Source menu term.
         *
         * @return WP_Term|false
         */
        protected function ensure_target_menu( $menu ) {
                $target_id = (int) get_term_meta( $menu->term_id, '_fpml_pair_id', true );

                if ( $target_id ) {
                        $target = wp_get_nav_menu_object( $target_id );

                        if ( $target && ! is_wp_error( $target ) ) {
                                return $target;
                        }
                }

                $name = $menu->name . ' EN';
                $slug = sanitize_title( $menu->slug . '-en' );

                $result = wp_create_nav_menu(
                        $name,
                        array(
                                'slug' => $slug,
                        )
                );

                if ( is_wp_error( $result ) ) {
                        if ( 'menu_exists' === $result->get_error_code() ) {
                                $existing = wp_get_nav_menu_object( $slug );

                                if ( $existing && ! is_wp_error( $existing ) ) {
                                        $target_id = (int) $existing->term_id;

                                        update_term_meta( $menu->term_id, '_fpml_pair_id', $target_id );
                                        update_term_meta( $target_id, '_fpml_pair_source_id', $menu->term_id );
                                        update_term_meta( $target_id, '_fpml_is_translation', 1 );

                                        return $existing;
                                }
                        }

                        $this->logger->log(
                                'error',
                                sprintf( 'Impossibile creare il menu inglese per %s: %s', $menu->name, $result->get_error_message() ),
                                array(
                                        'menu_id' => $menu->term_id,
                                )
                        );

                        return false;
                }

                $target_id = (int) $result;

                update_term_meta( $menu->term_id, '_fpml_pair_id', $target_id );
                update_term_meta( $target_id, '_fpml_pair_source_id', $menu->term_id );
                update_term_meta( $target_id, '_fpml_is_translation', 1 );

                $target = wp_get_nav_menu_object( $target_id );

                if ( ! $target || is_wp_error( $target ) ) {
                        return false;
                }

                return $target;
        }

        /**
         * Synchronize menu items between Italian and English menus.
         *
         * @since 0.2.0
         *
         * @param WP_Term $source_menu Source menu term.
         * @param WP_Term $target_menu Target menu term.
         *
         * @return void
         */
        protected function sync_menu_items( $source_menu, $target_menu ) {
                $source_items = wp_get_nav_menu_items( $source_menu->term_id, array( 'post_status' => 'any' ) );
                $target_items = wp_get_nav_menu_items( $target_menu->term_id, array( 'post_status' => 'any' ) );

                $target_map = array();

                foreach ( (array) $target_items as $item ) {
                        $source_id = (int) get_post_meta( $item->ID, '_fpml_pair_source_id', true );

                        if ( $source_id ) {
                                $target_map[ $source_id ] = (int) $item->ID;
                        }
                }

                usort( $source_items, function ( $a, $b ) {
                        $order_a = isset( $a->menu_order ) ? (int) $a->menu_order : 0;
                        $order_b = isset( $b->menu_order ) ? (int) $b->menu_order : 0;

                        if ( $order_a === $order_b ) {
                                return 0;
                        }

                        return ( $order_a < $order_b ) ? -1 : 1;
                } );

                $processed = array();
                $relation  = array();

                foreach ( (array) $source_items as $item ) {
                        $parent_id        = isset( $item->menu_item_parent ) ? (int) $item->menu_item_parent : 0;
                        $target_parent_id = $parent_id && isset( $relation[ $parent_id ] ) ? $relation[ $parent_id ] : 0;
                        $existing_id      = isset( $target_map[ $item->ID ] ) ? $target_map[ $item->ID ] : 0;

                        $args = $this->build_menu_item_args( $item, $target_parent_id );

                        $new_id = wp_update_nav_menu_item( $target_menu->term_id, $existing_id, $args );

                        if ( is_wp_error( $new_id ) || ! $new_id ) {
                                $this->logger->log(
                                        'error',
                                        sprintf( 'Impossibile sincronizzare la voce di menu #%d: %s', $item->ID, is_wp_error( $new_id ) ? $new_id->get_error_message() : 'errore sconosciuto' ),
                                        array(
                                                'menu_id'    => $source_menu->term_id,
                                                'item_id'    => $item->ID,
                                                'target_id'  => $existing_id,
                                        )
                                );

                                continue;
                        }

                        $relation[ $item->ID ] = (int) $new_id;
                        $processed[]           = (int) $new_id;

                        update_post_meta( $item->ID, '_fpml_pair_id', $new_id );
                        update_post_meta( $new_id, '_fpml_pair_source_id', $item->ID );
                        update_post_meta( $new_id, '_fpml_is_translation', 1 );

                        if ( $this->has_custom_menu_label( $item ) ) {
                                $this->update_menu_status_flag( $new_id, 'title', 'needs_update' );

                                $this->queue->enqueue_menu_item_label( $item );
                        } else {
                                $resolved_title = $this->resolve_target_menu_title( $item );

                                if ( '' !== $resolved_title ) {
                                        wp_update_post(
                                                array(
                                                        'ID'         => $new_id,
                                                        'post_title' => $resolved_title,
                                                )
                                        );
                                        update_post_meta( $new_id, '_menu_item_title', $resolved_title );
                                }

                                $this->update_menu_status_flag( $new_id, 'title', 'synced' );
                        }
                }

                foreach ( (array) $target_items as $item ) {
                        if ( in_array( (int) $item->ID, $processed, true ) ) {
                                continue;
                        }

                        $source_id = (int) get_post_meta( $item->ID, '_fpml_pair_source_id', true );

                        if ( $source_id && isset( $relation[ $source_id ] ) ) {
                                continue;
                        }

                        wp_delete_post( $item->ID, true );
                }
        }

        /**
         * Build arguments for wp_update_nav_menu_item.
         *
         * @since 0.2.0
         *
         * @param WP_Post $item            Menu item.
         * @param int     $target_parent_id Target parent ID.
         *
         * @return array
         */
        protected function build_menu_item_args( $item, $target_parent_id ) {
                $classes = array();

                if ( isset( $item->classes ) && is_array( $item->classes ) ) {
                        $classes = array_filter( array_map( 'sanitize_html_class', $item->classes ) );
                }

                $args = array(
                        'menu-item-title'      => $item->title,
                        'menu-item-status'     => $item->post_status,
                        'menu-item-position'   => isset( $item->menu_order ) ? (int) $item->menu_order : 0,
                        'menu-item-parent-id'  => $target_parent_id,
                        'menu-item-attr-title' => isset( $item->attr_title ) ? $item->attr_title : '',
                        'menu-item-target'     => isset( $item->target ) ? $item->target : '',
                        'menu-item-classes'    => implode( ' ', $classes ),
                        'menu-item-xfn'        => isset( $item->xfn ) ? $item->xfn : '',
                        'menu-item-description'=> isset( $item->description ) ? $item->description : '',
                );

                if ( 'custom' === $item->type ) {
                        $args['menu-item-type']        = 'custom';
                        $args['menu-item-url']         = isset( $item->url ) ? $item->url : '';
                        $args['menu-item-object']      = 'custom';
                        $args['menu-item-object-id']   = 0;
                } elseif ( 'taxonomy' === $item->type ) {
                        $target_term = (int) get_term_meta( $item->object_id, '_fpml_pair_id', true );
                        $args['menu-item-type']        = 'taxonomy';
                        $args['menu-item-object']      = $item->object;
                        $args['menu-item-object-id']   = $target_term ? $target_term : $item->object_id;
                        $args['menu-item-url']         = '';
                } elseif ( 'post_type' === $item->type ) {
                        $target_post = (int) get_post_meta( $item->object_id, '_fpml_pair_id', true );
                        $args['menu-item-type']        = 'post_type';
                        $args['menu-item-object']      = $item->object;
                        $args['menu-item-object-id']   = $target_post ? $target_post : $item->object_id;
                        $args['menu-item-url']         = '';
                } else {
                        $args['menu-item-type']      = $item->type;
                        $args['menu-item-object']    = $item->object;
                        $args['menu-item-object-id'] = $item->object_id;
                        $args['menu-item-url']       = isset( $item->url ) ? $item->url : '';
                }

                return $args;
        }

        /**
         * Determine whether a menu item has a custom label requiring translation.
         *
         * @since 0.3.0
         *
         * @param WP_Post $item Menu item.
         *
         * @return bool
         */
        protected function has_custom_menu_label( $item ) {
                if ( ! $item instanceof WP_Post ) {
                        return false;
                }

                if ( 'custom' === $item->type ) {
                        return true;
                }

                $manual = get_post_meta( $item->ID, '_menu_item_title', true );

                return '' !== trim( (string) $manual );
        }

        /**
         * Resolve the expected English label for a menu item tied to a resource.
         *
         * @since 0.3.0
         *
         * @param WP_Post $item Menu item post.
         *
         * @return string
         */
        protected function resolve_target_menu_title( $item ) {
                if ( ! $item instanceof WP_Post ) {
                        return '';
                }

                switch ( $item->type ) {
                        case 'taxonomy':
                                $target_term_id = (int) get_term_meta( $item->object_id, '_fpml_pair_id', true );

                                if ( ! $target_term_id ) {
                                        $target_term_id = FPML_Language::instance()->get_term_translation_id( $item->object_id );
                                }

                                if ( $target_term_id ) {
                                        $term = get_term( $target_term_id, $item->object );

                                        if ( $term && ! is_wp_error( $term ) ) {
                                                return (string) $term->name;
                                        }
                                }

                                return (string) $item->title;

                        case 'post_type':
                                $target_post_id = (int) get_post_meta( $item->object_id, '_fpml_pair_id', true );

                                if ( $target_post_id ) {
                                        $target_post = get_post( $target_post_id );

                                        if ( $target_post instanceof WP_Post ) {
                                                return (string) $target_post->post_title;
                                        }
                                }

                                return (string) $item->title;
                }

                return (string) $item->title;
        }

        /**
         * Update translation status meta for menu items.
         *
         * @since 0.2.0
         *
         * @param int    $item_id Menu item ID.
         * @param string $field   Field identifier.
         * @param string $status  Status slug.
         *
         * @return void
         */
        protected function update_menu_status_flag( $item_id, $field, $status ) {
                $meta_key = '_fpml_status_' . sanitize_key( str_replace( ':', '_', $field ) );

                update_post_meta( $item_id, $meta_key, sanitize_key( $status ) );
        }

        /**
         * Force synchronization of every registered menu.
         *
         * @since 0.2.0
         *
         * @return int Number of menus processed.
         */
        public function resync_all() {
                $menus = wp_get_nav_menus( array( 'hide_empty' => false ) );

                if ( empty( $menus ) ) {
                        return 0;
                }

                $processed = 0;

                foreach ( $menus as $menu ) {
                        if ( ! $menu instanceof WP_Term ) {
                                continue;
                        }

                        if ( get_term_meta( $menu->term_id, '_fpml_is_translation', true ) ) {
                                continue;
                        }

                        $this->is_syncing = true;

                        try {
                                $target_menu = $this->ensure_target_menu( $menu );

                                if ( ! $target_menu ) {
                                        continue;
                                }

                                $this->sync_menu_items( $menu, $target_menu );
                                $processed++;
                        } finally {
                                $this->is_syncing = false;
                        }
                }

                return $processed;
        }
}
