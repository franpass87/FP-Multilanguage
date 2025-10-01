<?php
/**
 * Queue handler for translation jobs.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Manage the wp_fpml_queue table and CRUD operations.
 *
 * @since 0.2.0
 */
class FPML_Queue {
        /**
         * Schema version for the queue table.
         *
         * @since 0.3.1
         */
        const SCHEMA_VERSION = '2';

        /**
         * Singleton instance.
         *
         * @var FPML_Queue|null
         */
        protected static $instance = null;

        /**
         * Cached table name.
         *
         * @var string
         */
        protected $table = '';

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Queue
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
                global $wpdb;

                $this->table = $wpdb->prefix . 'fpml_queue';

                $this->register_table_name();
        }

        /**
         * Ensure global $wpdb has the custom table registered for caching purposes.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function register_table_name() {
                global $wpdb;

                if ( ! in_array( 'fpml_queue', $wpdb->tables, true ) ) {
                        $wpdb->tables[] = 'fpml_queue';
                }

                $wpdb->fpml_queue = $this->table;
        }

        /**
         * Get the fully qualified table name.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function get_table() {
                return $this->table;
        }

        /**
         * Install database table using dbDelta.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function install() {
                global $wpdb;

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $charset_collate = $wpdb->get_charset_collate();
                $table           = $this->get_table();

                $sql = "CREATE TABLE {$table} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        object_type varchar(32) NOT NULL,
                        object_id bigint(20) unsigned NOT NULL,
                        field varchar(191) NOT NULL,
                        hash_source varchar(64) NOT NULL DEFAULT '',
                        state varchar(20) NOT NULL DEFAULT 'pending',
                        retries smallint(5) unsigned NOT NULL DEFAULT 0,
                        last_error text NULL,
                        created_at datetime NOT NULL,
                        updated_at datetime NOT NULL,
                        PRIMARY KEY  (id),
                        KEY object_lookup (object_type, object_id),
                        KEY state_lookup (state),
                        KEY state_updated_lookup (state, updated_at),
                        UNIQUE KEY object_field (object_type, object_id, field)
                ) {$charset_collate};";

                dbDelta( $sql );

                update_option( 'fpml_queue_schema_version', self::SCHEMA_VERSION, false );
        }

        /**
         * Ensure the database schema is up to date.
         *
         * @since 0.3.1
         *
         * @return void
         */
        public function maybe_upgrade() {
                $stored_version = get_option( 'fpml_queue_schema_version', '' );

                if ( version_compare( (string) $stored_version, self::SCHEMA_VERSION, '>=' ) ) {
                        return;
                }

                $this->install();
        }

        /**
         * Enqueue or update a job.
         *
         * @since 0.2.0
         *
         * @param string $object_type Object type.
         * @param int    $object_id   Object ID.
         * @param string $field       Field identifier.
         * @param string $hash_source Hash of the source payload.
         *
         * @return int Job ID.
         */
        public function enqueue( $object_type, $object_id, $field, $hash_source ) {
                global $wpdb;

                $object_type = sanitize_key( $object_type );
                $object_id   = absint( $object_id );
                $field       = sanitize_text_field( $field );
                $hash_source = sanitize_text_field( $hash_source );

                if ( empty( $object_type ) || empty( $object_id ) || empty( $field ) ) {
                        return 0;
                }

                $table = $this->get_table();
                $now   = current_time( 'mysql', true );

                $existing = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, hash_source, state FROM {$table} WHERE object_type = %s AND object_id = %d AND field = %s",
                                $object_type,
                                $object_id,
                                $field
                        )
                );

                if ( $existing ) {
                        $data    = array(
                                'hash_source' => $hash_source,
                                'updated_at'  => $now,
                        );
                        $formats = array( '%s', '%s' );

                        if ( $existing->hash_source !== $hash_source || 'done' !== $existing->state ) {
                                $data['state']      = 'pending';
                                $data['retries']    = 0;
                                $data['last_error'] = '';
                                $formats[]          = '%s';
                                $formats[]          = '%d';
                                $formats[]          = '%s';
                        }

                        $wpdb->update(
                                $table,
                                $data,
                                array( 'id' => (int) $existing->id ),
                                $formats,
                                array( '%d' )
                        );

                        return (int) $existing->id;
                }

                $inserted = $wpdb->insert(
                        $table,
                        array(
                                'object_type' => $object_type,
                                'object_id'   => $object_id,
                                'field'       => $field,
                                'hash_source' => $hash_source,
                                'state'       => 'pending',
                                'retries'     => 0,
                                'last_error'  => '',
                                'created_at'  => $now,
                                'updated_at'  => $now,
                        ),
                        array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
                );

                if ( false === $inserted ) {
                        return 0;
                }

                return (int) $wpdb->insert_id;
        }

        /**
         * Enqueue a term translation job.
         *
         * @since 0.3.0
         *
         * @param WP_Term $term  Source term.
         * @param string  $field Field identifier (name|description).
         *
         * @return int Job ID.
         */
        public function enqueue_term( $term, $field ) {
                if ( ! ( $term instanceof WP_Term ) ) {
                        return 0;
                }

                $taxonomy = sanitize_key( $term->taxonomy );
                $field    = sanitize_key( $field );

                if ( '' === $taxonomy || '' === $field ) {
                        return 0;
                }

                switch ( $field ) {
                        case 'name':
                                $value = $term->name;
                                break;
                        case 'description':
                                $value = $term->description;
                                break;
                        default:
                                $value = '';
                }

                if ( is_array( $value ) || is_object( $value ) ) {
                        $value = wp_json_encode( $value );
                }

                $value = (string) $value;
                $hash  = md5( $value );

                $field_identifier = $taxonomy . ':' . $field;

                return $this->enqueue( 'term', $term->term_id, $field_identifier, $hash );
        }

        /**
         * Enqueue a menu item label translation job.
         *
         * @since 0.3.0
         *
         * @param WP_Post $item Menu item post.
         *
         * @return int Job ID.
         */
        public function enqueue_menu_item_label( $item ) {
                if ( ! ( $item instanceof WP_Post ) ) {
                        return 0;
                }

                $label = get_post_meta( $item->ID, '_menu_item_title', true );

                if ( '' === $label ) {
                        $label = (string) $item->post_title;
                }

                $label = (string) $label;

                if ( '' === trim( $label ) ) {
                        return 0;
                }

                return $this->enqueue( 'menu', $item->ID, 'title', md5( $label ) );
        }

        /**
         * Delete jobs older than the provided retention window.
         *
         * @since 0.3.1
         *
         * @param array $states Queue states to target.
         * @param int   $days   Retention window in days.
         * @param string $column Date column used for comparison.
         *
         * @return int|WP_Error Deleted rows or WP_Error on failure.
         */
        public function cleanup_old_jobs( $states, $days, $column = 'updated_at' ) {
                global $wpdb;

                $days   = (int) $days;
                $column = in_array( $column, array( 'created_at', 'updated_at' ), true ) ? $column : 'updated_at';
                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );

                if ( $days <= 0 || empty( $states ) ) {
                        return 0;
                }

                $table        = $this->get_table();
                $now          = current_time( 'timestamp', true );
                $cutoff       = gmdate( 'Y-m-d H:i:s', $now - ( $days * DAY_IN_SECONDS ) );
                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

                /**
                 * Filters the batch size used when deleting queue jobs during cleanup.
                 *
                 * @since 0.3.1
                 *
                 * @param int    $batch_size Suggested batch size.
                 * @param array  $states     States targeted for cleanup.
                 * @param int    $days       Retention window in days.
                 * @param string $column     Date column used for comparison.
                 */
                $batch_size = (int) apply_filters( 'fpml_queue_cleanup_batch_size', 500, $states, $days, $column );

                $batch_size = max( 1, $batch_size );
                $total      = 0;

                do {
                        $prepare_args = array_merge(
                                array( "DELETE FROM {$table} WHERE state IN ({$placeholders}) AND {$column} < %s LIMIT %d" ),
                                $states,
                                array( $cutoff, $batch_size )
                        );

                        $sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );

                        $deleted = $wpdb->query( $sql );

                        if ( false === $deleted ) {
                                $error = $wpdb->last_error ? $wpdb->last_error : __( 'Errore database sconosciuto.', 'fp-multilanguage' );

                                if ( class_exists( 'FPML_Logger' ) ) {
                                        FPML_Logger::instance()->log(
                                                'error',
                                                __( 'Pulizia coda non riuscita a causa di un errore del database.', 'fp-multilanguage' ),
                                                array(
                                                        'states'  => implode( ',', $states ),
                                                        'days'    => $days,
                                                        'column'  => $column,
                                                        'message' => $error,
                                                )
                                        );
                                } else {
                                        error_log( sprintf( 'FPML queue cleanup failed: %s', $error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                                }

                                return new WP_Error(
                                        'fpml_queue_cleanup_failed',
                                        __( 'Impossibile completare la pulizia della coda.', 'fp-multilanguage' ),
                                        array(
                                                'states' => $states,
                                                'days'   => $days,
                                                'column' => $column,
                                                'error'  => $error,
                                        )
                                );
                        }

                        $total += (int) $deleted;
                } while ( $deleted >= $batch_size );

                /**
                 * Fires after the queue cleanup finishes.
                 *
                 * @since 0.3.1
                 *
                 * @param array  $states States targeted by the cleanup.
                 * @param int    $days   Retention window in days.
                 * @param int    $total  Total deleted rows.
                 * @param string $column Date column used for comparison.
                 */
                do_action( 'fpml_queue_after_cleanup', $states, $days, $total, $column );

                return $total;
        }

        /**
         * Count jobs matching the cleanup criteria.
         *
         * @since 0.3.1
         *
         * @param array $states Queue states to target.
         * @param int   $days   Retention window in days.
         * @param string $column Date column used for comparison.
         *
         * @return int|WP_Error Number of matching jobs or WP_Error on failure.
         */
        public function count_old_jobs( $states, $days, $column = 'updated_at' ) {
                global $wpdb;

                $days   = (int) $days;
                $column = in_array( $column, array( 'created_at', 'updated_at' ), true ) ? $column : 'updated_at';
                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );

                if ( $days <= 0 || empty( $states ) ) {
                        return 0;
                }

                $table        = $this->get_table();
                $now          = current_time( 'timestamp', true );
                $cutoff       = gmdate( 'Y-m-d H:i:s', $now - ( $days * DAY_IN_SECONDS ) );
                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

                $prepare_args = array_merge(
                        array( "SELECT COUNT(*) FROM {$table} WHERE state IN ({$placeholders}) AND {$column} < %s" ),
                        $states,
                        array( $cutoff )
                );

                $sql   = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );
                $count = $wpdb->get_var( $sql );

                if ( null === $count && $wpdb->last_error ) {
                        return new WP_Error(
                                'fpml_queue_count_failed',
                                __( 'Impossibile contare i job per la pulizia della coda.', 'fp-multilanguage' ),
                                array(
                                        'states' => $states,
                                        'days'   => $days,
                                        'column' => $column,
                                        'error'  => $wpdb->last_error,
                                )
                        );
                }

                return (int) $count;
        }

        /**
         * Retrieve the oldest job in a given set of states.
         *
         * @since 0.3.1
         *
         * @param array  $states Queue states to inspect.
         * @param string $column Date column used for ordering.
         *
         * @return object|null
         */
        public function get_oldest_job_for_states( $states, $column = 'created_at' ) {
                global $wpdb;

                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );
                $column = in_array( $column, array( 'created_at', 'updated_at' ), true ) ? $column : 'created_at';

                if ( empty( $states ) ) {
                        return null;
                }

                $table        = $this->get_table();
                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

                $prepare_args = array_merge(
                        array( "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY {$column} ASC LIMIT 1" ),
                        $states
                );

                $sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );

                $job = $wpdb->get_row( $sql );

                return $job ? $job : null;
        }

        /**
         * Claim a batch of jobs for processing.
         *
         * @since 0.2.0
         *
         * @param int $limit Limit.
         *
         * @return array
         */
        public function claim_batch( $limit = 5 ) {
                global $wpdb;

                $limit = max( 1, absint( $limit ) );
                $table = $this->get_table();

                $states       = array( 'pending', 'outdated' );
                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
                $sql          = "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY created_at ASC LIMIT %d";
                $prepared     = array_merge( $states, array( $limit ) );

                $sql   = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $prepared ) );
                $items = $wpdb->get_results( $sql );

                if ( empty( $items ) ) {
                        return array();
                }

                $now = current_time( 'mysql', true );

                foreach ( $items as $item ) {
                        $wpdb->update(
                                $table,
                                array(
                                        'state'      => 'translating',
                                        'updated_at' => $now,
                                ),
                                array( 'id' => (int) $item->id ),
                                array( '%s', '%s' ),
                                array( '%d' )
                        );

                        $item->state      = 'translating';
                        $item->updated_at = $now;
                }

                return $items;
        }

        /**
         * Update job state.
         *
         * @since 0.2.0
         *
         * @param int    $job_id Job ID.
         * @param string $state  New state.
         * @param string $error  Optional error message.
         *
         * @return bool
         */
        public function update_state( $job_id, $state, $error = '' ) {
                global $wpdb;

                $job_id = absint( $job_id );
                $state  = sanitize_key( $state );
                $error  = wp_kses_post( $error );

                if ( ! $job_id || empty( $state ) ) {
                        return false;
                }

                $table = $this->get_table();
                $now   = current_time( 'mysql', true );

                $current = $wpdb->get_row(
                        $wpdb->prepare( "SELECT retries FROM {$table} WHERE id = %d", $job_id )
                );

                $retries = $current ? (int) $current->retries : 0;

                if ( in_array( $state, array( 'pending', 'done' ), true ) ) {
                        $retries = 0;
                } elseif ( 'error' === $state ) {
                        $retries++; // count failures.
                }

                $data = array(
                        'state'      => $state,
                        'updated_at' => $now,
                        'retries'    => $retries,
                );

                if ( ! empty( $error ) ) {
                        $data['last_error'] = $error;
                } elseif ( 'pending' === $state ) {
                        $data['last_error'] = '';
                }

                $formats = array( '%s', '%s', '%d' );

                if ( array_key_exists( 'last_error', $data ) ) {
                        $formats[] = '%s';
                }

                return (bool) $wpdb->update(
                        $table,
                        $data,
                        array( 'id' => $job_id ),
                        $formats,
                        array( '%d' )
                );
        }

        /**
         * Reset retries counter manually.
         *
         * @since 0.2.0
         *
         * @param int $job_id Job ID.
         *
         * @return bool
         */
        public function reset_retries( $job_id ) {
                global $wpdb;

                $job_id = absint( $job_id );

                if ( ! $job_id ) {
                        return false;
                }

                return (bool) $wpdb->update(
                        $this->get_table(),
                        array( 'retries' => 0 ),
                        array( 'id' => $job_id ),
                        array( '%d' ),
                        array( '%d' )
                );
        }

        /**
         * Retrieve jobs by state.
         *
         * @since 0.2.0
         *
         * @param array $states States list.
         * @param int   $limit  Limit.
         *
         * @return array
         */
        public function get_by_state( $states, $limit = 50 ) {
                global $wpdb;

                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );
                $limit  = max( 1, absint( $limit ) );

                if ( empty( $states ) ) {
                        return array();
                }

                $table        = $this->get_table();
                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
                $sql          = "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY updated_at DESC LIMIT %d";
                $prepared     = array_merge( $states, array( $limit ) );

                $sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $prepared ) );

                return $wpdb->get_results( $sql );
        }

        /**
         * Delete a job from the queue.
         *
         * @since 0.2.0
         *
         * @param int $job_id Job ID.
         *
         * @return bool
         */
        public function delete( $job_id ) {
                global $wpdb;

                $job_id = absint( $job_id );

                if ( ! $job_id ) {
                        return false;
                }

                return (bool) $wpdb->delete( $this->get_table(), array( 'id' => $job_id ), array( '%d' ) );
        }

        /**
         * Bulk mark jobs for a specific object as outdated.
         *
         * @since 0.2.0
         *
         * @param string $object_type Object type.
         * @param int    $object_id   Object ID.
         *
         * @return void
         */
        public function mark_outdated( $object_type, $object_id ) {
                global $wpdb;

                $object_type = sanitize_key( $object_type );
                $object_id   = absint( $object_id );

                if ( empty( $object_type ) || ! $object_id ) {
                        return;
                }

                $wpdb->update(
                        $this->get_table(),
                        array(
                                'state'      => 'outdated',
                                'updated_at' => current_time( 'mysql', true ),
                        ),
                        array(
                                'object_type' => $object_type,
                                'object_id'   => $object_id,
                        ),
                        array( '%s', '%s' ),
                        array( '%s', '%d' )
                );
        }

        /**
         * Purge completed jobs older than a threshold.
         *
         * @since 0.2.0
         *
         * @param int $days Days threshold.
         *
         * @return int Deleted rows.
         */
        public function purge_completed( $days = 30 ) {
                global $wpdb;

                $days = max( 1, absint( $days ) );

                $threshold = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

                $sql = 'DELETE FROM ' . $this->get_table() . " WHERE state IN ('done','skipped') AND updated_at < %s";

                return (int) $wpdb->query( $wpdb->prepare( $sql, $threshold ) );
        }

        /**
         * Retrieve counts grouped by state.
         *
         * @since 0.2.0
         *
         * @return array<string,int>
         */
        public function get_state_counts() {
                global $wpdb;

                $results = $wpdb->get_results( 'SELECT state, COUNT(*) AS total FROM ' . $this->get_table() . ' GROUP BY state' );

                $counts = array();

                foreach ( (array) $results as $row ) {
                        if ( isset( $row->state ) ) {
                                $counts[ $row->state ] = isset( $row->total ) ? (int) $row->total : 0;
                        }
                }

                return $counts;
        }

        /**
         * Count completed jobs for a specific object type and optional field.
         *
         * @since 0.3.0
         *
         * @param string      $object_type Object type slug.
         * @param string|null $field       Optional field identifier.
         *
         * @return int
         */
        public function count_completed_jobs( $object_type, $field = null ) {
                global $wpdb;

                $object_type = sanitize_key( $object_type );

                if ( '' === $object_type ) {
                        return 0;
                }

                $table = $this->get_table();

                if ( null === $field || '' === $field ) {
                        $sql = $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$table} WHERE state = %s AND object_type = %s",
                                'done',
                                $object_type
                        );
                } else {
                        $field = sanitize_text_field( $field );

                        $sql = $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$table} WHERE state = %s AND object_type = %s AND field = %s",
                                'done',
                                $object_type,
                                $field
                        );
                }

                $count = $wpdb->get_var( $sql );

                return $count ? (int) $count : 0;
        }

        /**
         * Reset specific states back to pending.
         *
         * @since 0.2.0
         *
         * @param array $states States to reset.
         *
         * @return int Updated rows.
         */
        public function reset_states( $states = array( 'translating' ) ) {
                global $wpdb;

                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );

                if ( empty( $states ) ) {
                        return 0;
                }

                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
                $sql          = 'UPDATE ' . $this->get_table() . " SET state = 'pending', retries = 0, last_error = '', updated_at = %s WHERE state IN ({$placeholders})";

                $params = array_merge( array( current_time( 'mysql', true ) ), $states );

                $prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );

                return (int) $wpdb->query( $prepared );
        }

        /**
         * Fetch jobs for a given set of states.
         *
         * @since 0.2.0
         *
         * @param array $states States to include.
         * @param int   $limit  Batch size.
         * @param int   $offset Offset for pagination.
         *
         * @return array
         */
        public function get_jobs_for_states( $states, $limit = 50, $offset = 0 ) {
                global $wpdb;

                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );
                $limit  = max( 1, absint( $limit ) );
                $offset = max( 0, absint( $offset ) );

                if ( empty( $states ) ) {
                        return array();
                }

                $placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
                $sql          = 'SELECT * FROM ' . $this->get_table() . " WHERE state IN ({$placeholders}) ORDER BY id ASC LIMIT %d OFFSET %d";
                $params       = array_merge( $states, array( $limit, $offset ) );

                $sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );

                return $wpdb->get_results( $sql );
        }
}
