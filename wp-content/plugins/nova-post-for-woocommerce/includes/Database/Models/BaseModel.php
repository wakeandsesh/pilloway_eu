<?php
/**
 * Base model class for database operations.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Database\Models;

use wpdb;

/**
 * Base model class.
 */
class BaseModel {

    /**
     * The database table name without a prefix.
     *
     * @var string
     */
    protected string $table;

    /**
     * The database instance.
     *
     * @var wpdb
     */
    protected wpdb $db;

    /**
     * The current query.
     *
     * @var array
     */
    protected array $query = array();

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Set the where clause.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value to compare.
     * @param string $operator Comparison operator.
     * @return self
     */
    public function where( $column, $value, $operator = '=' ) {
        $this->query['where'][] = array(
            'column'   => $column,
            'value'    => $value,
            'operator' => $operator,
        );
        return $this;
    }

    /**
     * Execute the query and get the first result.
     *
     * @return object|null
     */
    public function first() {
        $results = $this->get();
        return ! empty( $results ) ? $results[0] : null;
    }

    /**
     * Execute the query and get all results.
     *
     * @return array
     */
    public function get() {
        $sql = 'SELECT * FROM ' . $this->get_table_name();

        if ( ! empty( $this->query['where'] ) ) {
            $where_clauses = array();
            $values        = array();

            foreach ( $this->query['where'] as $where ) {
                $where_clauses[] = $where['column'] . ' ' . $where['operator'] . ' %s';
                $values[]        = $where['value'];
            }

            $sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
            $sql  = $this->db->prepare( $sql, $values );
        }

        $this->reset_query();
        return $this->db->get_results( $sql );
    }

    /**
     * Insert a new record.
     *
     * @param array $data Data to insert.
     * @return int|false The number of rows inserted, or false on error.
     */
    public function insert( array $data ) {
        $result = $this->db->insert(
            $this->get_table_name(),
            $data,
            $this->get_format( $data )
        );

        return $result ? $this->db->insert_id : false;
    }

    /**
     * Update records.
     *
     * @param array $data Data to update.
     * @return int|false The number of rows updated, or false on error.
     */
    public function update( $data ) {
        $where        = array();
        $where_format = array();

        if ( ! empty( $this->query['where'] ) ) {
            foreach ( $this->query['where'] as $where_clause ) {
                $where[ $where_clause['column'] ] = $where_clause['value'];
                $where_format[]                   = $this->get_column_format( $where_clause['column'] );
            }
        }

        $result = $this->db->update(
            $this->get_table_name(),
            $data,
            $where,
            $this->get_format( $data ),
            $where_format
        );

        $this->reset_query();
        return $result;
    }

    /**
     * Delete records.
     *
     * @return int|false The number of rows deleted, or false on error.
     */
    public function delete() {
        $where        = array();
        $where_format = array();

        if ( ! empty( $this->query['where'] ) ) {
            foreach ( $this->query['where'] as $where_clause ) {
                $where[ $where_clause['column'] ] = $where_clause['value'];
                $where_format[]                   = $this->get_column_format( $where_clause['column'] );
            }
        }

        $result = $this->db->delete(
            $this->get_table_name(),
            $where,
            $where_format
        );

        $this->reset_query();
        return $result;
    }

    /**
     * Get the table name with a prefix.
     *
     * @return string
     */
    protected function get_table_name() {
        return $this->db->prefix . $this->table;
    }

    /**
     * Get the format for a column.
     *
     * @param string $column Column name.
     * @return string Format string.
     */
    protected function get_column_format( string $column ) {
        $formats = array(
            'id'           => '%d',
            'order_count'  => '%d',
            'lock_version' => '%d',
        );

        return $formats[ $column ] ?? '%s';
    }

    /**
     * Get formats for data.
     *
     * @param array $data Data array.
     * @return array Format array.
     */
    protected function get_format( array $data ) {
        $formats = array();
        foreach ( array_keys( $data ) as $column ) {
            $formats[] = $this->get_column_format( $column );
        }
        return $formats;
    }

    /**
     * Reset the query.
     */
    protected function reset_query() {
        $this->query = array();
    }
}
