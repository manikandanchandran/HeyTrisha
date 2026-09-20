<?php
/**
 * Fuzzy product/category SQL for when exact-match queries return no rows.
 *
 * @package HeyTrisha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds broad LIKE-based product search SQL from the user question and schema.
 */
class HeyTrisha_Fuzzy_Search {

	/** WooCommerce product rows live in posts — exclude only trash/auto-draft. */
	const PRODUCT_STATUS_WHERE = "p.post_type = 'product' AND p.post_status NOT IN ('trash','auto-draft')";

	/**
	 * @param string               $user_query User question.
	 * @param array<string, mixed> $schema     table => columns.
	 * @return string|null Read-only SELECT or null.
	 */
	public static function build_product_search_sql( $user_query, $schema ) {
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return null;
		}

		$posts_table = self::find_table( $schema, 'posts' );
		if ( ! $posts_table ) {
			return null;
		}

		$keywords = self::extract_keywords( $user_query );
		if ( empty( $keywords ) ) {
			return self::build_list_products_sql( $schema );
		}

		$terms_table         = self::find_table( $schema, 'terms' );
		$term_taxonomy_table = self::find_table( $schema, 'term_taxonomy' );
		$term_rel_table      = self::find_table( $schema, 'term_relationships' );

		$posts_esc  = self::quote_table( $posts_table );
		$conditions = array();

		foreach ( $keywords as $kw ) {
			$escaped = self::escape_like( $kw );
			$part    = "(LOWER(p.post_title) LIKE '%{$escaped}%'";
			if ( self::has_column( $schema, $posts_table, 'post_content' ) ) {
				$part .= " OR LOWER(p.post_content) LIKE '%{$escaped}%'";
			}
			if ( self::has_column( $schema, $posts_table, 'post_excerpt' ) ) {
				$part .= " OR LOWER(p.post_excerpt) LIKE '%{$escaped}%'";
			}
			if ( $terms_table && $term_taxonomy_table && $term_rel_table ) {
				$part .= " OR LOWER(t.name) LIKE '%{$escaped}%' OR LOWER(t.slug) LIKE '%{$escaped}%'";
			}
			$part        .= ')';
			$conditions[] = $part;
		}

		$joins = '';
		if ( $terms_table && $term_taxonomy_table && $term_rel_table ) {
			$tt    = self::quote_table( $term_taxonomy_table );
			$tr    = self::quote_table( $term_rel_table );
			$t     = self::quote_table( $terms_table );
			$joins = " LEFT JOIN {$tr} tr ON p.ID = tr.object_id"
				. " LEFT JOIN {$tt} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id"
				. " LEFT JOIN {$t} t ON tt.term_id = t.term_id";
		}

		$where_kw = implode( ' OR ', $conditions );

		return 'SELECT DISTINCT p.ID, p.post_title, p.post_excerpt, p.post_status'
			. " FROM {$posts_esc} p{$joins}"
			. ' WHERE ' . self::PRODUCT_STATUS_WHERE
			. " AND ({$where_kw})"
			. ' ORDER BY p.post_title ASC LIMIT 25';
	}

	/**
	 * List store products from wp_posts (source of truth for WooCommerce catalog).
	 *
	 * @param array<string, mixed> $schema table => columns.
	 * @return string|null
	 */
	public static function build_list_products_sql( $schema ) {
		$posts_table = self::find_table( $schema, 'posts' );
		if ( ! $posts_table ) {
			return null;
		}

		$posts_esc = self::quote_table( $posts_table );

		return 'SELECT p.ID, p.post_title, p.post_excerpt, p.post_status'
			. " FROM {$posts_esc} p"
			. ' WHERE ' . self::PRODUCT_STATUS_WHERE
			. ' ORDER BY p.post_title ASC LIMIT 50';
	}

	/**
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $suffix Table suffix.
	 * @return string|null
	 */
	public static function find_table( $schema, $suffix ) {
		foreach ( array_keys( $schema ) as $table ) {
			if ( preg_match( '/_' . preg_quote( $suffix, '/' ) . '$/i', $table ) ) {
				return $table;
			}
		}
		return null;
	}

	/**
	 * @return list<string>
	 */
	public static function extract_keywords( $query ) {
		$stop = array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
			'can', 'you', 'give', 'show', 'list', 'get', 'how', 'many', 'what', 'which', 'are', 'is',
			'product', 'products', 'category', 'categories', 'item', 'items', 'store', 'shop',
			'woocommerce', 'have', 'any', 'all', 'our', 'your', 'there', 'do', 'does',
		);

		$query    = strtolower( trim( (string) $query ) );
		$words    = preg_split( '/\s+/', $query );
		$keywords = array();

		foreach ( $words as $word ) {
			$word = trim( $word, '.,!?;:()[]{}\"\'' );
			if ( strlen( $word ) < 3 || in_array( $word, $stop, true ) || preg_match( '/^\d+$/', $word ) ) {
				continue;
			}
			$keywords[] = $word;
			if ( strlen( $word ) > 4 && preg_match( '/(ies|es|s)$/', $word ) ) {
				$stem = preg_replace( '/(ies)$/', 'y', $word );
				$stem = preg_replace( '/(es|s)$/', '', $stem );
				if ( strlen( $stem ) >= 3 && $stem !== $word ) {
					$keywords[] = $stem;
				}
			}
		}

		return array_values( array_unique( $keywords ) );
	}

	/**
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $table  Table name.
	 * @param string               $column Column name.
	 */
	private static function has_column( $schema, $table, $column ) {
		if ( empty( $schema[ $table ] ) || ! is_array( $schema[ $table ] ) ) {
			return false;
		}
		foreach ( $schema[ $table ] as $col ) {
			if ( strcasecmp( (string) $col, $column ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $table Table name.
	 */
	private static function quote_table( $table ) {
		return '`' . str_replace( '`', '``', $table ) . '`';
	}

	/**
	 * @param string $value Keyword.
	 */
	private static function escape_like( $value ) {
		return addcslashes( strtolower( $value ), "\\%_'" );
	}

	/**
	 * Rewrite exact text equality in API-generated SQL to partial LIKE matching.
	 *
	 * @param string $sql        SQL from API.
	 * @param string $user_query User question.
	 * @return string
	 */
	public static function broaden_sql( $sql, $user_query = '' ) {
		$sql = trim( (string) $sql );
		if ( $sql === '' || ! preg_match( '/\bSELECT\b/i', $sql ) ) {
			return $sql;
		}

		$text_columns = array(
			'post_title', 'post_name', 'post_content', 'post_excerpt',
			'name', 'slug', 'description', 'product_name', 'sku',
		);
		$keywords = self::extract_keywords( $user_query );

		foreach ( $text_columns as $column ) {
			$col_pattern = preg_quote( $column, '/' );

			$sql = preg_replace_callback(
				'/\bLOWER\s*\(\s*(?:`?[\w]+`?\.)?`?' . $col_pattern . '`?\s*\)\s*=\s*LOWER\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
				static function ( $m ) use ( $keywords ) {
					return self::like_clause_from_match( $m[0], $m[1], $keywords, true );
				},
				$sql
			);

			$sql = preg_replace_callback(
				'/\b(?:`?[\w]+`?\.)?`?' . $col_pattern . '`?\s*=\s*[\'"]([^\'"]+)[\'"]/i',
				static function ( $m ) use ( $keywords ) {
					if ( preg_match( '/^\d+$/', $m[1] ) ) {
						return $m[0];
					}
					return self::like_clause_from_match( $m[0], $m[1], $keywords, false );
				},
				$sql
			);
		}

		// Relax publish-only product filters so draft/pending products are included.
		if ( preg_match( "/post_type\s*=\s*['\"]product['\"]/i", $sql ) ) {
			$sql = preg_replace(
				"/\bpost_status\s*=\s*['\"]publish['\"]/i",
				"post_status NOT IN ('trash','auto-draft')",
				$sql
			);
		}

		return $sql;
	}

	/**
	 * @param string        $original   Full match.
	 * @param string        $value      Compared value.
	 * @param array<int,string> $keywords Search terms.
	 * @param bool          $was_lower  Column was wrapped in LOWER().
	 * @return string
	 */
	private static function like_clause_from_match( $original, $value, $keywords, $was_lower ) {
		$terms = ! empty( $keywords ) ? $keywords : self::extract_keywords( $value );
		if ( empty( $terms ) ) {
			$terms = array( strtolower( $value ) );
		}

		if ( ! preg_match( '/\b((?:`?[\w]+`?\.)?`?[\w]+`?)\s*=/i', $original, $col_match ) ) {
			return $original;
		}

		$col_expr  = $col_match[1];
		$col_lower = $was_lower ? "LOWER({$col_expr})" : $col_expr;
		$parts     = array();

		foreach ( array_slice( array_unique( $terms ), 0, 5 ) as $term ) {
			$escaped   = self::escape_like( $term );
			$parts[]   = "{$col_lower} LIKE '%{$escaped}%'";
		}

		return '(' . implode( ' OR ', $parts ) . ')';
	}
}
