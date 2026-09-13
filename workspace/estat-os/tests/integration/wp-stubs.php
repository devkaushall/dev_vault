<?php
/**
 * WordPress API surface backed by real in-memory storage.
 *
 * These are working implementations, not no-ops: posts really are stored and
 * retrieved, meta really round-trips, capabilities really gate, terms really
 * get created. That is what makes the integration tests meaningful.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'OBJECT', 'OBJECT' );
}
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'WEEK_IN_SECONDS' ) || define( 'WEEK_IN_SECONDS', 604800 );
defined( 'MONTH_IN_SECONDS' ) || define( 'MONTH_IN_SECONDS', 2592000 );
defined( 'YEAR_IN_SECONDS' ) || define( 'YEAR_IN_SECONDS', 31536000 );
defined( 'KB_IN_BYTES' ) || define( 'KB_IN_BYTES', 1024 );
defined( 'MB_IN_BYTES' ) || define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['estat_posts']      = array();
$GLOBALS['estat_postmeta']   = array();
$GLOBALS['estat_terms']      = array();
$GLOBALS['estat_termrel']    = array();
$GLOBALS['estat_users']      = array( 1 => (object) array( 'ID' => 1, 'display_name' => 'Owner', 'user_email' => 'owner@example.test' ) );
$GLOBALS['estat_caps']       = array();
// A real WordPress site always has the built-in roles. The administrator role
// in particular must exist, otherwise Roles::install() silently skips granting
// capabilities to it and permission bugs stay hidden from the test suite.
$GLOBALS['estat_roles']      = array(
	'administrator' => array(
		'name'         => 'Administrator',
		'capabilities' => array(
			'read'          => true,
			'manage_options' => true,
			'upload_files'  => true,
			'edit_posts'    => true,
			'publish_posts' => true,
			'delete_posts'  => true,
			'edit_others_posts' => true,
		),
	),
	'subscriber'    => array(
		'name'         => 'Subscriber',
		'capabilities' => array( 'read' => true ),
	),
);
$GLOBALS['estat_current_user'] = 1;
$GLOBALS['estat_post_types'] = array();
$GLOBALS['estat_taxonomies'] = array();
$GLOBALS['estat_next_post']  = 100;
$GLOBALS['estat_next_term']  = 500;
$GLOBALS['estat_shutdown']   = array();

/* ------------------------------------------------------------------ Options */

/**
 * Read an option.
 *
 * @param string $name    Name.
 * @param mixed  $default Fallback.
 * @return mixed
 */
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['estat_options'] ) ? $GLOBALS['estat_options'][ $name ] : $default;
	}
}

/**
 * Write an option.
 *
 * @param string $name     Name.
 * @param mixed  $value    Value.
 * @param mixed  $autoload Ignored.
 * @return bool
 */
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['estat_options'][ $name ] = $value;
		return true;
	}
}

/**
 * Create an option if absent.
 *
 * @param string $name     Name.
 * @param mixed  $value    Value.
 * @param string $dep      Deprecated.
 * @param mixed  $autoload Ignored.
 * @return bool
 */
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '', $dep = '', $autoload = null ) {
		if ( array_key_exists( $name, $GLOBALS['estat_options'] ) ) {
			return false;
		}
		$GLOBALS['estat_options'][ $name ] = $value;
		return true;
	}
}

/**
 * Delete an option.
 *
 * @param string $name Name.
 * @return bool
 */
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['estat_options'][ $name ] );
		return true;
	}
}

/* --------------------------------------------------------------- Transients */

/**
 * Read a transient.
 *
 * @param string $key Key.
 * @return mixed
 */
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['estat_transients'][ $key ] ) ) {
			return false;
		}
		list( $value, $expires ) = $GLOBALS['estat_transients'][ $key ];
		if ( $expires && $expires < time() ) {
			unset( $GLOBALS['estat_transients'][ $key ] );
			return false;
		}
		return $value;
	}
}

/**
 * Write a transient.
 *
 * @param string $key   Key.
 * @param mixed  $value Value.
 * @param int    $ttl   Lifetime.
 * @return bool
 */
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['estat_transients'][ $key ] = array( $value, $ttl ? time() + $ttl : 0 );
		return true;
	}
}

/**
 * Delete a transient.
 *
 * @param string $key Key.
 * @return bool
 */
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['estat_transients'][ $key ] );
		return true;
	}
}

/* ------------------------------------------------------------ Object cache */

/**
 * Read from cache.
 *
 * @param string $key   Key.
 * @param string $group Group.
 * @param bool   $force Ignored.
 * @param bool   $found Found flag.
 * @return mixed
 */
if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
		$found = isset( $GLOBALS['estat_cache'][ $group ][ $key ] );
		return $found ? $GLOBALS['estat_cache'][ $group ][ $key ] : false;
	}
}

/**
 * Write to cache.
 *
 * @param string $key   Key.
 * @param mixed  $value Value.
 * @param string $group Group.
 * @param int    $ttl   Lifetime.
 * @return bool
 */
if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
		$GLOBALS['estat_cache'][ $group ][ $key ] = $value;
		return true;
	}
}

/**
 * Delete from cache.
 *
 * @param string $key   Key.
 * @param string $group Group.
 * @return bool
 */
if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		unset( $GLOBALS['estat_cache'][ $group ][ $key ] );
		return true;
	}
}

/**
 * Flush the cache.
 *
 * @return bool
 */
if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() {
		$GLOBALS['estat_cache'] = array();
		return true;
	}
}

/* ----------------------------------------------------------------- Posts */

/**
 * Insert a post.
 *
 * @param array<string,mixed> $postarr Post data.
 * @param bool                $wp_error Return WP_Error on failure.
 * @return int|WP_Error
 */
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		if ( empty( $postarr['post_title'] ) && empty( $postarr['post_content'] ) ) {
			return $wp_error ? new WP_Error( 'empty_content', 'Content, title, and excerpt are empty.' ) : 0;
		}
		$id = $GLOBALS['estat_next_post']++;
		$GLOBALS['estat_posts'][ $id ] = new WP_Post( (object) array_merge(
			array(
				'ID'            => $id,
				'post_type'     => 'post',
				'post_title'    => '',
				'post_content'  => '',
				'post_excerpt'  => '',
				'post_status'   => 'draft',
				'post_name'     => sanitize_title( (string) ( $postarr['post_title'] ?? '' ) ),
				'post_author'   => $GLOBALS['estat_current_user'],
				'post_date'     => gmdate( 'Y-m-d H:i:s' ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'post_parent'   => 0,
				'menu_order'    => 0,
			),
			$postarr
		) );
		$GLOBALS['estat_posts'][ $id ]->ID = $id;

		do_action( 'save_post', $id, $GLOBALS['estat_posts'][ $id ], false );
		do_action( 'save_post_' . $GLOBALS['estat_posts'][ $id ]->post_type, $id, $GLOBALS['estat_posts'][ $id ], false );
		do_action( 'transition_post_status', $GLOBALS['estat_posts'][ $id ]->post_status, 'new', $GLOBALS['estat_posts'][ $id ] );
		return $id;
	}
}

/**
 * Update a post.
 *
 * @param array<string,mixed> $postarr  Post data.
 * @param bool                $wp_error Return WP_Error on failure.
 * @return int|WP_Error
 */
if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! isset( $GLOBALS['estat_posts'][ $id ] ) ) {
			return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
		}
		$old = clone $GLOBALS['estat_posts'][ $id ];
		foreach ( $postarr as $key => $value ) {
			$GLOBALS['estat_posts'][ $id ]->$key = $value;
		}
		do_action( 'save_post', $id, $GLOBALS['estat_posts'][ $id ], true );
		do_action( 'save_post_' . $GLOBALS['estat_posts'][ $id ]->post_type, $id, $GLOBALS['estat_posts'][ $id ], true );
		if ( $old->post_status !== $GLOBALS['estat_posts'][ $id ]->post_status ) {
			do_action( 'transition_post_status', $GLOBALS['estat_posts'][ $id ]->post_status, $old->post_status, $GLOBALS['estat_posts'][ $id ] );
		}
		return $id;
	}
}

/**
 * Fetch a post.
 *
 * @param int|null $id Post ID.
 * @return object|null
 */
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id = null ) {
		// Real WordPress accepts a post object and hands it straight back.
		if ( is_object( $id ) ) {
			return $id;
		}
		$id = (int) $id;
		return $GLOBALS['estat_posts'][ $id ] ?? null;
	}
}

/**
 * Delete a post.
 *
 * @param int  $id    Post ID.
 * @param bool $force Force delete.
 * @return object|false
 */
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		$id = (int) $id;
		if ( ! isset( $GLOBALS['estat_posts'][ $id ] ) ) {
			return false;
		}
		$post = $GLOBALS['estat_posts'][ $id ];
		do_action( 'before_delete_post', $id, $post );
		unset( $GLOBALS['estat_posts'][ $id ], $GLOBALS['estat_postmeta'][ $id ] );
		return $post;
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	/**
	 * Admin edit link for a post.
	 *
	 * @param int    $id      Post ID.
	 * @param string $context Context.
	 * @return string
	 */
	function get_edit_post_link( $id, $context = 'display' ) {
		return admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * Read a user meta value.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_user_meta( $user_id, $key = '', $single = false ) {
		$store = isset( $GLOBALS['estat_usermeta'] ) ? $GLOBALS['estat_usermeta'] : array();
		$value = isset( $store[ (int) $user_id ][ $key ] ) ? $store[ (int) $user_id ][ $key ] : '';
		if ( $single ) {
			return $value;
		}
		return '' === $value ? array() : array( $value );
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * Store a user meta value.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_user_meta( $user_id, $key, $value ) {
		if ( ! isset( $GLOBALS['estat_usermeta'] ) ) {
			$GLOBALS['estat_usermeta'] = array();
		}
		$GLOBALS['estat_usermeta'][ (int) $user_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * Find a page by its slug.
	 *
	 * @param string $path Slug.
	 * @return object|null
	 */
	function get_page_by_path( $path ) {
		$path = (string) $path;
		foreach ( (array) $GLOBALS['estat_posts'] as $post ) {
			if ( 'page' === $post->post_type && isset( $post->post_name ) && $path === (string) $post->post_name ) {
				return $post;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'wp_trash_post' ) ) {
	/**
	 * Move a post to the bin, remembering what it was before.
	 *
	 * @param int $id Post ID.
	 * @return object|false
	 */
	function wp_trash_post( $id ) {
		$id = (int) $id;
		if ( ! isset( $GLOBALS['estat_posts'][ $id ] ) ) {
			return false;
		}
		$post = $GLOBALS['estat_posts'][ $id ];
		update_post_meta( $id, '_wp_trash_meta_status', $post->post_status );
		$post->post_status = 'trash';
		return $post;
	}
}

if ( ! function_exists( 'wp_untrash_post' ) ) {
	/**
	 * Take a post back out of the bin.
	 *
	 * @param int $id Post ID.
	 * @return object|false
	 */
	function wp_untrash_post( $id ) {
		$id = (int) $id;
		if ( ! isset( $GLOBALS['estat_posts'][ $id ] ) ) {
			return false;
		}
		$post = $GLOBALS['estat_posts'][ $id ];
		if ( 'trash' !== $post->post_status ) {
			return false;
		}
		$previous = (string) get_post_meta( $id, '_wp_trash_meta_status', true );
		// Real WordPress restores to draft unless told otherwise.
		$post->post_status = '' !== $previous ? $previous : 'draft';
		delete_post_meta( $id, '_wp_trash_meta_status' );
		return $post;
	}
}

/**
 * Post status.
 *
 * @param int $id Post ID.
 * @return string|false
 */
if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $id = 0 ) {
		$post = get_post( $id );
		return $post ? $post->post_status : false;
	}
}

/**
 * Post type.
 *
 * @param int $id Post ID.
 * @return string|false
 */
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $id = 0 ) {
		$post = get_post( $id );
		return $post ? $post->post_type : false;
	}
}

/**
 * Post title.
 *
 * @param int $id Post ID.
 * @return string
 */
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $id = 0 ) {
		$post = get_post( $id );
		return $post ? (string) apply_filters( 'the_title', $post->post_title, $post->ID ) : '';
	}
}

/**
 * Permalink.
 *
 * @param int $id Post ID.
 * @return string
 */
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id = 0 ) {
		if ( is_object( $id ) ) {
			$id = $id->ID ?? 0;
		}
		return 'https://example.test/?p=' . (int) $id;
	}
}

/**
 * Query posts.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,object>
 */
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$type   = $args['post_type'] ?? 'post';
		$types  = (array) $type;
		$status = (array) ( $args['post_status'] ?? array( 'publish' ) );
		$limit  = (int) ( $args['posts_per_page'] ?? 5 );
		$fields = $args['fields'] ?? '';

		$out = array();
		foreach ( $GLOBALS['estat_posts'] as $post ) {
			if ( ! in_array( $post->post_type, $types, true ) ) {
				continue;
			}
			if ( ! in_array( 'any', $status, true ) && ! in_array( $post->post_status, $status, true ) ) {
				continue;
			}
			if ( isset( $args['meta_key'] ) ) {
				$value = get_post_meta( $post->ID, (string) $args['meta_key'], true );
				if ( isset( $args['meta_value'] ) && (string) $value !== (string) $args['meta_value'] ) {
					continue;
				}
				if ( ! isset( $args['meta_value'] ) && '' === (string) $value ) {
					continue;
				}
			}
			$out[] = 'ids' === $fields ? $post->ID : $post;
		}
		if ( $limit > 0 ) {
			$out = array_slice( $out, 0, $limit );
		}
		return $out;
	}
}

/**
 * Count posts by status.
 *
 * @param string $type Post type.
 * @return object
 */
if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type = 'post' ) {
		$counts = array( 'publish' => 0, 'draft' => 0, 'pending' => 0, 'private' => 0, 'trash' => 0 );
		foreach ( $GLOBALS['estat_posts'] as $post ) {
			if ( $post->post_type !== $type ) {
				continue;
			}
			$counts[ $post->post_status ] = ( $counts[ $post->post_status ] ?? 0 ) + 1;
		}
		return (object) $counts;
	}
}

/* ------------------------------------------------------------------ Meta */

/**
 * Read meta.
 *
 * @param int    $id     Post ID.
 * @param string $key    Meta key.
 * @param bool   $single Single value.
 * @return mixed
 */
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		$id = (int) $id;
		if ( '' === $key ) {
			return $GLOBALS['estat_postmeta'][ $id ] ?? array();
		}
		if ( ! isset( $GLOBALS['estat_postmeta'][ $id ][ $key ] ) ) {
			return $single ? '' : array();
		}
		$value = $GLOBALS['estat_postmeta'][ $id ][ $key ];
		return $single ? $value : array( $value );
	}
}

/**
 * Write meta.
 *
 * @param int    $id    Post ID.
 * @param string $key   Meta key.
 * @param mixed  $value Value.
 * @param mixed  $prev  Ignored.
 * @return bool
 */
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value, $prev = '' ) {
		$GLOBALS['estat_postmeta'][ (int) $id ][ $key ] = $value;
		return true;
	}
}

/**
 * Add meta.
 *
 * @param int    $id     Post ID.
 * @param string $key    Meta key.
 * @param mixed  $value  Value.
 * @param bool   $unique Ignored.
 * @return bool
 */
if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( $id, $key, $value, $unique = false ) {
		return update_post_meta( $id, $key, $value );
	}
}

/**
 * Delete meta.
 *
 * @param int    $id  Post ID.
 * @param string $key Meta key.
 * @param mixed  $val Ignored.
 * @return bool
 */
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $id, $key, $val = '' ) {
		unset( $GLOBALS['estat_postmeta'][ (int) $id ][ $key ] );
		return true;
	}
}

/**
 * Register meta.
 *
 * @param string              $type Object type.
 * @param string              $key  Meta key.
 * @param array<string,mixed> $args Arguments.
 * @return bool
 */
if ( ! function_exists( 'register_post_meta' ) ) {
	function register_post_meta( $type, $key, $args = array() ) {
		return true;
	}
}

/**
 * Register meta (generic).
 *
 * @param string              $type Object type.
 * @param string              $key  Meta key.
 * @param array<string,mixed> $args Arguments.
 * @return bool
 */
if ( ! function_exists( 'register_meta' ) ) {
	function register_meta( $type, $key, $args = array() ) {
		return true;
	}
}

/* ------------------------------------------------------------ Thumbnails */

/**
 * Set the featured image.
 *
 * @param int $post_id       Post ID.
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post_id, $attachment_id ) {
		return update_post_meta( $post_id, '_thumbnail_id', (int) $attachment_id );
	}
}

/**
 * Remove the featured image.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
if ( ! function_exists( 'delete_post_thumbnail' ) ) {
	function delete_post_thumbnail( $post_id ) {
		return delete_post_meta( $post_id, '_thumbnail_id' );
	}
}

/**
 * Featured image ID.
 *
 * @param int $post_id Post ID.
 * @return int
 */
if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post_id = null ) {
		return (int) get_post_meta( (int) $post_id, '_thumbnail_id', true );
	}
}

/**
 * Featured image URL.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Size.
 * @return string|false
 */
if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	function get_the_post_thumbnail_url( $post_id = null, $size = 'post-thumbnail' ) {
		$id = get_post_thumbnail_id( $post_id );
		return $id ? 'https://example.test/img/' . $id . '.jpg' : false;
	}
}

/**
 * Attachment URL.
 *
 * @param int $id Attachment ID.
 * @return string|false
 */
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) {
		return 'https://example.test/img/' . (int) $id . '.jpg';
	}
}

/**
 * Attachment image source.
 *
 * @param int    $id   Attachment ID.
 * @param string $size Size.
 * @return array{0:string,1:int,2:int}|false
 */
if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
		return array( 'https://example.test/img/' . (int) $id . '.jpg', 800, 600 );
	}
}

/**
 * Attachment image markup.
 *
 * @param int    $id    Attachment ID.
 * @param string $size  Size.
 * @param bool   $icon  Icon.
 * @param array  $attrs Attributes.
 * @return string
 */
if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attrs = array() ) {
		return '<img src="https://example.test/img/' . (int) $id . '.jpg" alt="" />';
	}
}

/* ------------------------------------------------------------------ Terms */

/**
 * Whether a term exists.
 *
 * @param string $term     Term name.
 * @param string $taxonomy Taxonomy.
 * @return array<string,mixed>|null
 */
if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( $term, $taxonomy = '' ) {
		foreach ( $GLOBALS['estat_terms'] as $id => $row ) {
			if ( $row['taxonomy'] === $taxonomy && ( $row['name'] === $term || $row['slug'] === $term || (string) $id === (string) $term ) ) {
				return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
			}
		}
		return null;
	}
}

/**
 * Create a term.
 *
 * @param string              $term     Name.
 * @param string              $taxonomy Taxonomy.
 * @param array<string,mixed> $args     Arguments.
 * @return array<string,mixed>|WP_Error
 */
if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		$existing = term_exists( $term, $taxonomy );
		if ( $existing ) {
			return new WP_Error( 'term_exists', 'Term already exists.', $existing['term_id'] );
		}
		$id = $GLOBALS['estat_next_term']++;
		$GLOBALS['estat_terms'][ $id ] = array(
			'term_id'  => $id,
			'name'     => $term,
			'slug'     => sanitize_title( $term ),
			'taxonomy' => $taxonomy,
			'count'    => 0,
		);
		return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
	}
}

/**
 * Assign terms to an object.
 *
 * @param int          $object_id Object ID.
 * @param array|string $terms     Terms.
 * @param string       $taxonomy  Taxonomy.
 * @param bool         $append    Append.
 * @return array<int,int>|WP_Error
 */
if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
		$terms = (array) $terms;
		$ids   = array();
		foreach ( $terms as $term ) {
			if ( is_numeric( $term ) ) {
				$ids[] = (int) $term;
				continue;
			}
			$existing = term_exists( $term, $taxonomy );
			if ( $existing ) {
				$ids[] = (int) $existing['term_id'];
				continue;
			}
			$new = wp_insert_term( (string) $term, $taxonomy );
			if ( ! is_wp_error( $new ) ) {
				$ids[] = (int) $new['term_id'];
			}
		}
		if ( ! $append ) {
			$GLOBALS['estat_termrel'][ $object_id ][ $taxonomy ] = $ids;
		} else {
			$GLOBALS['estat_termrel'][ $object_id ][ $taxonomy ] = array_unique(
				array_merge( $GLOBALS['estat_termrel'][ $object_id ][ $taxonomy ] ?? array(), $ids )
			);
		}
		foreach ( $GLOBALS['estat_terms'] as $tid => $row ) {
			$count = 0;
			foreach ( $GLOBALS['estat_termrel'] as $rel ) {
				if ( in_array( $tid, $rel[ $row['taxonomy'] ] ?? array(), true ) ) {
					$count++;
				}
			}
			$GLOBALS['estat_terms'][ $tid ]['count'] = $count;
		}
		do_action( 'set_object_terms', $object_id, $terms, $ids, $taxonomy, $append, array() );
		return $ids;
	}
}

/**
 * Read an object's terms.
 *
 * @param int|array           $object_ids Object IDs.
 * @param string|array        $taxonomies Taxonomies.
 * @param array<string,mixed> $args       Arguments.
 * @return array<int,mixed>|WP_Error
 */
if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $object_ids, $taxonomies, $args = array() ) {
		$object_ids = (array) $object_ids;
		$taxonomies = (array) $taxonomies;
		$fields     = $args['fields'] ?? 'all';
		$out        = array();

		foreach ( $object_ids as $object_id ) {
			foreach ( $taxonomies as $taxonomy ) {
				foreach ( $GLOBALS['estat_termrel'][ $object_id ][ $taxonomy ] ?? array() as $tid ) {
					if ( ! isset( $GLOBALS['estat_terms'][ $tid ] ) ) {
						continue;
					}
					$term = $GLOBALS['estat_terms'][ $tid ];
					if ( 'names' === $fields ) {
						$out[] = $term['name'];
					} elseif ( 'slugs' === $fields ) {
						$out[] = $term['slug'];
					} elseif ( 'ids' === $fields ) {
						$out[] = (int) $tid;
					} else {
						$out[] = (object) $term;
					}
				}
			}
		}
		return $out;
	}
}

/**
 * Query terms.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,mixed>|WP_Error
 */
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		$taxonomy = $args['taxonomy'] ?? '';
		$fields   = $args['fields'] ?? 'all';
		$hide     = ! empty( $args['hide_empty'] );
		$out      = array();
		foreach ( $GLOBALS['estat_terms'] as $tid => $term ) {
			if ( $taxonomy && $term['taxonomy'] !== $taxonomy ) {
				continue;
			}
			if ( $hide && 0 === (int) $term['count'] ) {
				continue;
			}
			$out[] = 'ids' === $fields ? (int) $tid : (object) $term;
		}
		if ( isset( $args['number'] ) && (int) $args['number'] > 0 ) {
			$out = array_slice( $out, 0, (int) $args['number'] );
		}
		return $out;
	}
}

/**
 * Fetch one term.
 *
 * @param int    $id       Term ID.
 * @param string $taxonomy Taxonomy.
 * @return object|null
 */
if ( ! function_exists( 'get_term' ) ) {
	function get_term( $id, $taxonomy = '' ) {
		return isset( $GLOBALS['estat_terms'][ (int) $id ] ) ? (object) $GLOBALS['estat_terms'][ (int) $id ] : null;
	}
}

/**
 * Delete a term.
 *
 * @param int    $id       Term ID.
 * @param string $taxonomy Taxonomy.
 * @return bool
 */
if ( ! function_exists( 'wp_delete_term' ) ) {
	function wp_delete_term( $id, $taxonomy ) {
		unset( $GLOBALS['estat_terms'][ (int) $id ] );
		return true;
	}
}

/**
 * Term link.
 *
 * @param mixed  $term     Term.
 * @param string $taxonomy Taxonomy.
 * @return string
 */
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term, $taxonomy = '' ) {
		$id = is_object( $term ) ? $term->term_id : (int) $term;
		return 'https://example.test/?term=' . $id;
	}
}

/* --------------------------------------------------- Types and taxonomies */

/**
 * Register a post type.
 *
 * @param string              $type Type.
 * @param array<string,mixed> $args Arguments.
 * @return object
 */
if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( $type, $args = array() ) {
		$GLOBALS['estat_post_types'][ $type ] = $args;
		return (object) $args;
	}
}

/**
 * Register a taxonomy.
 *
 * @param string              $taxonomy Taxonomy.
 * @param string|array        $types    Object types.
 * @param array<string,mixed> $args     Arguments.
 * @return object
 */
if ( ! function_exists( 'register_taxonomy' ) ) {
	function register_taxonomy( $taxonomy, $types, $args = array() ) {
		$GLOBALS['estat_taxonomies'][ $taxonomy ] = $args;
		return (object) $args;
	}
}

/**
 * Whether a post type exists.
 *
 * @param string $type Type.
 * @return bool
 */
if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( $type ) {
		return isset( $GLOBALS['estat_post_types'][ $type ] );
	}
}

/**
 * Whether a taxonomy exists.
 *
 * @param string $taxonomy Taxonomy.
 * @return bool
 */
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) {
		return isset( $GLOBALS['estat_taxonomies'][ $taxonomy ] );
	}
}

/* ---------------------------------------------------- Users, capabilities */

/**
 * Current user ID.
 *
 * @return int
 */
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) $GLOBALS['estat_current_user'];
	}
}

/**
 * Current user object.
 *
 * @return object
 */
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		$id = get_current_user_id();
		return $GLOBALS['estat_users'][ $id ] ?? (object) array( 'ID' => 0, 'display_name' => '' );
	}
}

/**
 * Capability check against the test capability table.
 *
 * @param string $cap Capability.
 * @param mixed  ...$args Extra arguments.
 * @return bool
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap, ...$args ) {
		$id = get_current_user_id();
		if ( isset( $GLOBALS['estat_caps'][ $id ]['__all__'] ) ) {
			return true;
		}
		return ! empty( $GLOBALS['estat_caps'][ $id ][ $cap ] );
	}
}

/**
 * Grant a capability in the harness.
 *
 * @param int    $user_id User ID.
 * @param string $cap     Capability.
 * @return void
 */
if ( ! function_exists( 'estat_test_grant' ) ) {
	function estat_test_grant( int $user_id, string $cap ): void {
		$GLOBALS['estat_caps'][ $user_id ][ $cap ] = true;
	}
}

/**
 * Revoke every capability in the harness.
 *
 * @param int $user_id User ID.
 * @return void
 */
if ( ! function_exists( 'estat_test_revoke_all' ) ) {
	function estat_test_revoke_all( int $user_id ): void {
		$GLOBALS['estat_caps'][ $user_id ] = array();
	}
}

/**
 * Fetch users.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,object>
 */
if ( ! function_exists( 'get_users' ) ) {
	function get_users( $args = array() ) {
		return array_values( $GLOBALS['estat_users'] );
	}
}

/**
 * Fetch a user.
 *
 * @param string $field Field.
 * @param mixed  $value Value.
 * @return object|false
 */
if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		if ( 'id' === strtolower( (string) $field ) ) {
			return $GLOBALS['estat_users'][ (int) $value ] ?? false;
		}
		foreach ( $GLOBALS['estat_users'] as $user ) {
			if ( ( $user->user_email ?? '' ) === $value ) {
				return $user;
			}
		}
		return false;
	}
}

/**
 * Add a role.
 *
 * @param string              $role         Role slug.
 * @param string              $display      Display name.
 * @param array<string,bool>  $capabilities Capabilities.
 * @return object|null
 */
if ( ! function_exists( 'add_role' ) ) {
	function add_role( $role, $display, $capabilities = array() ) {
		$GLOBALS['estat_roles'][ $role ] = array( 'name' => $display, 'capabilities' => $capabilities );
		return (object) $GLOBALS['estat_roles'][ $role ];
	}
}

/**
 * Remove a role.
 *
 * @param string $role Role slug.
 * @return void
 */
if ( ! function_exists( 'remove_role' ) ) {
	function remove_role( $role ) {
		unset( $GLOBALS['estat_roles'][ $role ] );
	}
}

/**
 * Fetch a role object.
 *
 * @param string $role Role slug.
 * @return object|null
 */
if ( ! function_exists( 'get_role' ) ) {
	function get_role( $role ) {
		if ( ! isset( $GLOBALS['estat_roles'][ $role ] ) ) {
			return null;
		}
		return new Estat_Test_Role( $role );
	}
}

/**
 * A minimal role object supporting add_cap/remove_cap.
 */
class Estat_Test_Role {

	/**
	 * Role slug.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Capabilities.
	 *
	 * @var array<string,bool>
	 */
	public array $capabilities;

	/**
	 * Constructor.
	 *
	 * @param string $role Role slug.
	 */
	public function __construct( string $role ) {
		$this->name         = $role;
		$this->capabilities = $GLOBALS['estat_roles'][ $role ]['capabilities'] ?? array();
	}

	/**
	 * Add a capability.
	 *
	 * @param string $cap   Capability.
	 * @param bool   $grant Grant.
	 * @return void
	 */
	public function add_cap( $cap, $grant = true ) {
		$this->capabilities[ $cap ] = $grant;
		$GLOBALS['estat_roles'][ $this->name ]['capabilities'][ $cap ] = $grant;
	}

	/**
	 * Remove a capability.
	 *
	 * @param string $cap Capability.
	 * @return void
	 */
	public function remove_cap( $cap ) {
		unset( $this->capabilities[ $cap ], $GLOBALS['estat_roles'][ $this->name ]['capabilities'][ $cap ] );
	}

	/**
	 * Whether the role grants a capability, matching WP_Role::has_cap().
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	public function has_cap( $cap ) {
		$caps = $GLOBALS['estat_roles'][ $this->name ]['capabilities'] ?? array();
		return ! empty( $caps[ $cap ] );
	}
}

/* ------------------------------------------------------------------ Nonces */

/**
 * Create a nonce.
 *
 * @param string $action Action.
 * @return string
 */
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return substr( md5( 'nonce' . $action . get_current_user_id() ), 0, 10 );
	}
}

/**
 * Verify a nonce.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return int|false
 */
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false;
	}
}

/**
 * Nonce field markup.
 *
 * @param string $action  Action.
 * @param string $name    Field name.
 * @param bool   $referer Referer field.
 * @param bool   $display Echo.
 * @return string
 */
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
		if ( $display ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $html;
	}
}

/**
 * Nonce URL.
 *
 * @param string $url    URL.
 * @param string $action Action.
 * @param string $name   Argument name.
 * @return string
 */
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
		return add_query_arg( $name, wp_create_nonce( $action ), $url );
	}
}

/**
 * REST nonce check.
 *
 * @param string $action Action.
 * @param string $key    Request key.
 * @return int|false
 */
if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $key = false ) {
		return 1;
	}
}

/* -------------------------------------------------------------------- URLs */

/**
 * Admin URL.
 *
 * @param string $path   Path.
 * @param string $scheme Scheme.
 * @return string
 */
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

/**
 * Home URL.
 *
 * @param string $path Path.
 * @return string
 */
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( $path, '/' );
	}
}

/**
 * Site URL.
 *
 * @param string $path Path.
 * @return string
 */
if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ) {
		return home_url( $path );
	}
}

/**
 * REST URL.
 *
 * @param string $path Path.
 * @return string
 */
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( $path, '/' );
	}
}

/**
 * Plugin directory URL.
 *
 * @param string $file File.
 * @return string
 */

/**
 * Plugin directory path.
 *
 * @param string $file File.
 * @return string
 */

/**
 * Plugin basename.
 *
 * @param string $file File.
 * @return string
 */

/**
 * Upload directory.
 *
 * @return array<string,mixed>
 */
if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	function wp_get_upload_dir() {
		return array(
			'basedir' => sys_get_temp_dir() . '/estat-uploads',
			'baseurl' => 'https://example.test/wp-content/uploads',
			'error'   => false,
		);
	}
}

/**
 * Upload directory alias.
 *
 * @return array<string,mixed>
 */
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$dir = wp_get_upload_dir();

		// Core also fills 'path' and 'url' with the month folder. The stub
		// returned only 'basedir', so any code using 'path' silently wrote to
		// the filesystem root here while working correctly in production.
		if ( empty( $dir['path'] ) && ! empty( $dir['basedir'] ) ) {
			$dir['path'] = $dir['basedir'];
		}
		if ( empty( $dir['url'] ) && ! empty( $dir['baseurl'] ) ) {
			$dir['url'] = $dir['baseurl'];
		}

		return $dir;
	}
}

/**
 * Create a directory.
 *
 * @param string $dir Directory.
 * @return bool
 */
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		return is_dir( $dir ) || mkdir( $dir, 0777, true );
	}
}

/**
 * Delete a file.
 *
 * @param string $file File.
 * @return void
 */
if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
}

/**
 * Unique filename.
 *
 * @param string $dir  Directory.
 * @param string $name Name.
 * @return string
 */
if ( ! function_exists( 'wp_unique_filename' ) ) {
	function wp_unique_filename( $dir, $name ) {
		return $name;
	}
}

/**
 * Filetype check.
 *
 * @param string $file  Filename.
 * @param array  $mimes Allowed mimes.
 * @return array<string,mixed>
 */
if ( ! function_exists( 'wp_check_filetype' ) ) {
	function wp_check_filetype( $filename, $mimes = null ) {
		$map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
			'svg'  => 'image/svg+xml',
			'pdf'  => 'application/pdf',
			'csv'  => 'text/csv',
		);

		$ext = strtolower( (string) pathinfo( (string) $filename, PATHINFO_EXTENSION ) );

		return array(
			'ext'  => isset( $map[ $ext ] ) ? $ext : false,
			'type' => $map[ $ext ] ?? false,
		);
	}
}

/* ------------------------------------------------------------------- Time */

/**
 * Current time.
 *
 * @param string $type Type.
 * @param int    $gmt  GMT flag.
 * @return string|int
 */
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		if ( 'timestamp' === $type || 'U' === $type ) {
			return time();
		}
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
		return gmdate( (string) $type );
	}
}

/**
 * Convert local to GMT.
 *
 * @param string $date   Date.
 * @param string $format Format.
 * @return string
 */

/**
 * Convert GMT to local.
 *
 * @param string $date   Date.
 * @param string $format Format.
 * @return string
 */

/**
 * Localised date.
 *
 * @param string   $format Format.
 * @param int|null $time   Timestamp.
 * @param bool     $gmt    GMT flag.
 * @return string
 */
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $time = null, $gmt = false ) {
		return gmdate( $format, null === $time ? time() : (int) $time );
	}
}

/**
 * Localised number.
 *
 * @param float $number   Number.
 * @param int   $decimals Decimals.
 * @return string
 */
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

/**
 * Human readable time difference.
 *
 * @param int $from From timestamp.
 * @param int $to   To timestamp.
 * @return string
 */

/* ---------------------------------------------------------------- i18n */

/**
 * Translate.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return (string) apply_filters( 'gettext', $text, $text, $domain );
	}
}

/**
 * Translate and echo.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return void
 */
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo __( $text, $domain ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Translate with context.
 *
 * @param string $text    Text.
 * @param string $context Context.
 * @param string $domain  Domain.
 * @return string
 */
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return __( $text, $domain );
	}
}

/**
 * Plural translation.
 *
 * @param string $single Singular.
 * @param string $plural Plural.
 * @param int    $number Count.
 * @param string $domain Domain.
 * @return string
 */
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? __( $single, $domain ) : __( $plural, $domain );
	}
}

/**
 * Escaped translation.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( __( $text, $domain ) );
	}
}

/**
 * Escaped translation echo.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return void
 */
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( __( $text, $domain ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Escaped attribute translation.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( __( $text, $domain ) );
	}
}

/**
 * Escaped attribute translation echo.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return void
 */
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( __( $text, $domain ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Escaped translation with context.
 *
 * @param string $text    Text.
 * @param string $context Context.
 * @param string $domain  Domain.
 * @return string
 */
if ( ! function_exists( 'esc_html_x' ) ) {
	function esc_html_x( $text, $context, $domain = 'default' ) {
		return esc_html( __( $text, $domain ) );
	}
}

/**
 * Load a text domain.
 *
 * @param string $domain Domain.
 * @param string $path   Path.
 * @return bool
 */
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $dep = false, $path = '' ) {
		return true;
	}
}

/**
 * Determine the locale.
 *
 * @return string
 */
if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale() {
		return 'en_US';
	}
}

/**
 * Current locale.
 *
 * @return string
 */
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'en_US';
	}
}

/**
 * User locale.
 *
 * @param mixed $user User.
 * @return string
 */
if ( ! function_exists( 'get_user_locale' ) ) {
	function get_user_locale( $user = 0 ) {
		return 'en_US';
	}
}

/* ------------------------------------------------------------------- Cron */

/**
 * Schedule a recurring event.
 *
 * @param int    $timestamp  Timestamp.
 * @param string $recurrence Recurrence.
 * @param string $hook       Hook.
 * @param array  $args       Arguments.
 * @return bool
 */
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		$GLOBALS['estat_cron'][ $hook ] = array( 'timestamp' => $timestamp, 'recurrence' => $recurrence );
		return true;
	}
}

/**
 * Next scheduled run.
 *
 * @param string $hook Hook.
 * @param array  $args Arguments.
 * @return int|false
 */
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return $GLOBALS['estat_cron'][ $hook ]['timestamp'] ?? false;
	}
}

/**
 * Clear a scheduled hook.
 *
 * @param string $hook Hook.
 * @param array  $args Arguments.
 * @return int
 */
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		unset( $GLOBALS['estat_cron'][ $hook ] );
		return 1;
	}
}

/**
 * Schedule a one-off event.
 *
 * @param int    $timestamp Timestamp.
 * @param string $hook      Hook.
 * @param array  $args      Arguments.
 * @return bool
 */
if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['estat_cron'][ $hook . ':' . md5( serialize( $args ) ) ] = array(
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
		);
		return true;
	}
}

/* ------------------------------------------------------------------- Mail */

/**
 * Capture outgoing mail.
 *
 * @param string|array $to      Recipient.
 * @param string       $subject Subject.
 * @param string       $message Message.
 * @param string|array $headers Headers.
 * @return bool
 */
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$GLOBALS['estat_mail'][] = compact( 'to', 'subject', 'message', 'headers' );
		return true;
	}
}

/* ------------------------------------------------------------------- HTTP */

/**
 * Capture outgoing HTTP POSTs.
 *
 * @param string              $url  URL.
 * @param array<string,mixed> $args Arguments.
 * @return array<string,mixed>
 */
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['estat_http'][] = array( 'url' => $url, 'args' => $args );
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => '{"ok":true}' );
	}
}

/**
 * Capture outgoing HTTP GETs.
 *
 * @param string              $url  URL.
 * @param array<string,mixed> $args Arguments.
 * @return array<string,mixed>
 */
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['estat_http'][] = array( 'url' => $url, 'args' => $args );
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => '{}' );
	}
}

/**
 * Response code.
 *
 * @param mixed $response Response.
 * @return int
 */
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? (int) $response['response']['code'] : 0;
	}
}

/**
 * Response body.
 *
 * @param mixed $response Response.
 * @return string
 */
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) ? (string) $response['body'] : '';
	}
}

/* ------------------------------------------------------------- Misc utils */

/**
 * WP_Error check.
 *
 * @param mixed $thing Thing.
 * @return bool
 */
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

/**
 * Admin context flag.
 *
 * @return bool
 */
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return (bool) ( $GLOBALS['estat_is_admin'] ?? false );
	}
}

/**
 * Multisite flag.
 *
 * @return bool
 */
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}

/**
 * SSL flag.
 *
 * @return bool
 */
if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		return true;
	}
}

/**
 * Doing cron flag.
 *
 * @return bool
 */
if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() {
		return false;
	}
}

/**
 * Doing AJAX flag.
 *
 * @return bool
 */
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return false;
	}
}

/**
 * Capture redirects instead of performing them.
 *
 * @param string $location Location.
 * @param int    $status   Status.
 * @return bool
 */
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['estat_redirects'][] = array( 'location' => $location, 'status' => $status );
		throw new Estat_Redirect_Exception( $location );
	}
}

/**
 * Redirect alias.
 *
 * @param string $location Location.
 * @param int    $status   Status.
 * @return bool
 */
if ( ! function_exists( 'wp_redirect' ) ) {
	function wp_redirect( $location, $status = 302 ) {
		return wp_safe_redirect( $location, $status );
	}
}

/**
 * Thrown instead of exiting on redirect, so tests can assert on it.
 */
class Estat_Redirect_Exception extends RuntimeException {}

/**
 * Thrown instead of exiting on wp_die, so tests can assert on it.
 */
class Estat_Die_Exception extends RuntimeException {}

/**
 * Capture wp_die instead of exiting.
 *
 * @param string $message Message.
 * @param string $title   Title.
 * @param array  $args    Arguments.
 * @return void
 */
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		$GLOBALS['estat_died'][] = array( 'message' => $message, 'title' => $title, 'args' => $args );
		throw new Estat_Die_Exception( is_string( $message ) ? $message : 'died' );
	}
}

/**
 * Referer.
 *
 * @return string|false
 */
if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() {
		return 'https://example.test/wp-admin/admin.php?page=estat-today';
	}
}

/**
 * Add a query argument.
 *
 * @param mixed ...$args Arguments.
 * @return string
 */
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$pairs = $args[0];
			$url   = $args[1] ?? '';
		} else {
			$pairs = array( $args[0] => $args[1] );
			$url   = $args[2] ?? '';
		}
		$parts = wp_parse_url( $url );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		foreach ( $pairs as $key => $value ) {
			$query[ $key ] = $value;
		}
		$base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.test' ) . ( $parts['path'] ?? '' );
		return $base . ( $query ? '?' . http_build_query( $query ) : '' );
	}
}

/**
 * Remove a query argument.
 *
 * @param string|array $key Key.
 * @param string       $url URL.
 * @return string
 */
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url = '' ) {
		return $url;
	}
}

/**
 * Parse a URL.
 *
 * @param string $url       URL.
 * @param int    $component Component.
 * @return mixed
 */
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore
	}
}

/**
 * Recursively strip slashes.
 *
 * @param mixed $value Value.
 * @return mixed
 */

/**
 * Recursively add slashes.
 *
 * @param mixed $value Value.
 * @return mixed
 */

/**
 * Merge defaults.
 *
 * @param array|object $args     Arguments.
 * @param array        $defaults Defaults.
 * @return array<string,mixed>
 */
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, (array) $args );
	}
}

/**
 * JSON encode.
 *
 * @param mixed $data    Data.
 * @param int   $options Options.
 * @param int   $depth   Depth.
 * @return string|false
 */
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth ); // phpcs:ignore
	}
}

/**
 * Random string.
 *
 * @param int  $length  Length.
 * @param bool $special Special characters.
 * @return string
 */
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		return substr( bin2hex( random_bytes( (int) ceil( $length / 2 ) ) ), 0, $length );
	}
}

/**
 * UUID v4.
 *
 * @return string
 */
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}
}

/**
 * Random integer.
 *
 * @param int $min Minimum.
 * @param int $max Maximum.
 * @return int
 */
if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return random_int( $min, $max ?: PHP_INT_MAX );
	}
}

/**
 * Disable caching headers.
 *
 * @return void
 */
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {}
}

/**
 * Register an activation hook.
 *
 * @param string   $file     File.
 * @param callable $callback Callback.
 * @return void
 */

/**
 * Register a deactivation hook.
 *
 * @param string   $file     File.
 * @param callable $callback Callback.
 * @return void
 */

/**
 * Flush rewrite rules.
 *
 * @param bool $hard Hard flush.
 * @return void
 */
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ) {}
}

/**
 * Enqueue a style.
 *
 * @param mixed ...$args Arguments.
 * @return void
 */
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) {}
}

/**
 * Enqueue a script.
 *
 * @param mixed ...$args Arguments.
 * @return void
 */
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) {}
}

/**
 * Localize a script.
 *
 * @param string $handle Handle.
 * @param string $name   Object name.
 * @param array  $data   Data.
 * @return bool
 */
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['estat_localized'][ $name ] = $data;
		return true;
	}
}

/**
 * Enqueue the media library.
 *
 * @param array $args Arguments.
 * @return void
 */
if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media( $args = array() ) {}
}

/**
 * Register a REST route.
 *
 * @param string $namespace Namespace.
 * @param string $route     Route.
 * @param array  $args      Arguments.
 * @param bool   $override  Override.
 * @return bool
 */
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
		// Core accepts either one endpoint definition or a list of them (the
		// list form is how GET and POST share a path). Store them the same way
		// so tests see every endpoint, not just the last one.
		$is_list = isset( $args[0] ) && is_array( $args[0] );
		$entries = $is_list ? $args : array( $args );

		$GLOBALS['estat_rest_routes'][ $namespace . $route ]    = $is_list ? $args[0] : $args;
		$GLOBALS['estat_rest_endpoints'][ $namespace . $route ] = $entries;

		return true;
	}
}

/**
 * Sanitize a REST field.
 *
 * @param mixed $value Value.
 * @return mixed
 */
if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
	function rest_sanitize_boolean( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}

/**
 * Add a menu page.
 *
 * @param mixed ...$args Arguments.
 * @return string
 */
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$args ) {
		$GLOBALS['estat_menus'][]     = $args;
		$GLOBALS['estat_admin_menu'][] = array(
			'page_title' => $args[0] ?? '',
			'menu_title' => $args[1] ?? '',
			'capability' => $args[2] ?? '',
			'slug'       => $args[3] ?? '',
		);
		return 'toplevel_page_' . ( $args[3] ?? '' );
	}
}

/**
 * Add a submenu page.
 *
 * @param mixed ...$args Arguments.
 * @return string
 */
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ) {
		$GLOBALS['estat_submenus'][]  = $args;
		$GLOBALS['estat_admin_menu'][] = array(
			'parent'     => $args[0] ?? '',
			'page_title' => $args[1] ?? '',
			'menu_title' => $args[2] ?? '',
			'capability' => $args[3] ?? '',
			'slug'       => $args[4] ?? '',
		);
		return 'estat_page_' . ( $args[4] ?? '' );
	}
}

/**
 * Selected attribute.
 *
 * @param mixed $a       Value.
 * @param mixed $b       Comparison.
 * @param bool  $display Echo.
 * @return string
 */
if ( ! function_exists( 'selected' ) ) {
	function selected( $a, $b = true, $display = true ) {
		$html = (string) $a === (string) $b ? " selected='selected'" : '';
		if ( $display ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $html;
	}
}

/**
 * Checked attribute.
 *
 * @param mixed $a       Value.
 * @param mixed $b       Comparison.
 * @param bool  $display Echo.
 * @return string
 */
if ( ! function_exists( 'checked' ) ) {
	function checked( $a, $b = true, $display = true ) {
		$html = (string) $a === (string) $b ? " checked='checked'" : '';
		if ( $display ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $html;
	}
}

/**
 * Disabled attribute.
 *
 * @param mixed $a       Value.
 * @param mixed $b       Comparison.
 * @param bool  $display Echo.
 * @return string
 */
if ( ! function_exists( 'disabled' ) ) {
	function disabled( $a, $b = true, $display = true ) {
		return '';
	}
}

/**
 * Submit button.
 *
 * @param mixed ...$args Arguments.
 * @return void
 */
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( ...$args ) {
		echo '<button type="submit" class="button button-primary">Save</button>';
	}
}

/**
 * Screen reader text helper.
 *
 * @param string $text Text.
 * @return string
 */
if ( ! function_exists( 'esc_textarea_safe' ) ) {
	function esc_textarea_safe( $text ) {
		return esc_textarea( $text );
	}
}

/**
 * Post type archive link.
 *
 * @param string $type Type.
 * @return string
 */
if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( $type ) {
		return 'https://example.test/' . $type . '/';
	}
}

/**
 * Excerpt.
 *
 * @param int $post Post.
 * @return string
 */
if ( ! function_exists( 'get_the_excerpt' ) ) {
	function get_the_excerpt( $post = null ) {
		$p = get_post( is_object( $post ) ? $post->ID : (int) $post );
		return $p ? (string) $p->post_excerpt : '';
	}
}

/**
 * Blog info.
 *
 * @param string $show Field.
 * @return string
 */
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		if ( 'admin_email' === $show ) {
			return 'admin@example.test';
		}
		if ( 'name' === $show ) {
			return 'Example Office';
		}
		if ( 'charset' === $show ) {
			return 'UTF-8';
		}
		return '';
	}
}

/**
 * Shutdown registration.
 *
 * @param callable $callback Callback.
 * @return void
 */
if ( ! function_exists( 'add_shutdown_function' ) ) {
	function add_shutdown_function( $callback ) {
		$GLOBALS['estat_shutdown'][] = $callback;
	}
}

/**
 * Sanitize an HTML class.
 *
 * @param string $class    Class.
 * @param string $fallback Fallback.
 * @return string
 */

/**
 * Sanitize a file name.
 *
 * @param string $filename Filename.
 * @return string
 */

/**
 * Absolute integer.
 *
 * @param mixed $value Value.
 * @return int
 */
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

/**
 * Sanitize a key.
 *
 * @param string $key Key.
 * @return string
 */

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * A dbDelta substitute that genuinely parses the plugin's CREATE TABLE
	 * statements and materialises the columns in the in-memory store.
	 *
	 * It validates the SQL shape (table name, column list, PRIMARY KEY) and
	 * records the column set, so a malformed schema still fails loudly.
	 *
	 * @param string|array $queries SQL.
	 * @param bool         $execute Execute.
	 * @return array<string,string>
	 */
	function dbDelta( $queries = '', $execute = true ) {
		global $wpdb;
		$queries = (array) $queries;
		$out     = array();

		foreach ( $queries as $sql ) {
			if ( ! preg_match( '/CREATE TABLE\s+`?(\w+)`?\s*\((.*)\)\s*[^)]*;?\s*$/is', (string) $sql, $m ) ) {
				throw new RuntimeException( 'dbDelta could not parse: ' . substr( (string) $sql, 0, 120 ) );
			}
			$table = $m[1];
			$body  = $m[2];

			$columns = array();
			foreach ( preg_split( '/,\s*\n/', $body ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( preg_match( '/^(PRIMARY KEY|KEY|UNIQUE KEY|INDEX)/i', $line ) ) {
					continue;
				}
				if ( preg_match( '/^`?(\w+)`?\s+/', $line, $cm ) ) {
					$columns[] = $cm[1];
				}
			}
			if ( ! $columns ) {
				throw new RuntimeException( 'dbDelta found no columns in ' . $table );
			}
			if ( ! preg_match( '/PRIMARY KEY/i', $body ) ) {
				throw new RuntimeException( 'dbDelta: table ' . $table . ' has no PRIMARY KEY' );
			}

			if ( ! $wpdb->has_table( $table ) ) {
				$wpdb->create_table( $table );
				$out[ $table ] = "Created table {$table}";
			}
			$GLOBALS['estat_table_columns'][ $table ] = $columns;
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * Revision check.
	 *
	 * @param int|object $post Post.
	 * @return false|int
	 */
	function wp_is_post_revision( $post ) {
		return false;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	/**
	 * Autosave check.
	 *
	 * @param int|object $post Post.
	 * @return false|int
	 */
	function wp_is_post_autosave( $post ) {
		return false;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Placeholder; the real one is defined earlier in this file.
	 *
	 * @param string $nonce  Nonce.
	 * @param string $action Action.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 1;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	/**
	 * Registered post types.
	 *
	 * @param array  $args     Arguments.
	 * @param string $output   Output.
	 * @param string $operator Operator.
	 * @return array<string,string>
	 */
	function get_post_types( $args = array(), $output = 'names', $operator = 'and' ) {
		$names = array_keys( $GLOBALS['estat_post_types'] );
		return array_combine( $names, $names ) ?: array();
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	/**
	 * Registered taxonomies.
	 *
	 * @param array  $args   Arguments.
	 * @param string $output Output.
	 * @return array<string,string>
	 */
	function get_taxonomies( $args = array(), $output = 'names' ) {
		$names = array_keys( $GLOBALS['estat_taxonomies'] );
		return array_combine( $names, $names ) ?: array();
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * Site timezone.
	 *
	 * @return DateTimeZone
	 */
	function wp_timezone() {
		return new DateTimeZone( 'UTC' );
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	/**
	 * Site timezone string.
	 *
	 * @return string
	 */
	function wp_timezone_string() {
		return 'UTC';
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * Template conditional.
	 *
	 * @param string|string[] $post_types Post types.
	 * @return bool
	 */
	function is_singular( $post_types = '' ) {
		return ! empty( $GLOBALS['estat_is_singular'] );
	}
}

if ( ! function_exists( 'is_archive' ) ) {
	/**
	 * Template conditional.
	 *
	 * @return bool
	 */
	function is_archive() {
		return ! empty( $GLOBALS['estat_is_archive'] );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Admin context check.
	 *
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['estat_is_admin'] );
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	/**
	 * Template conditional.
	 *
	 * @return bool
	 */
	function is_front_page() {
		return false;
	}
}

if ( ! function_exists( 'is_search' ) ) {
	/**
	 * Template conditional.
	 *
	 * @return bool
	 */
	function is_search() {
		return false;
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	/**
	 * Scheme check.
	 *
	 * @return bool
	 */
	function is_ssl() {
		return true;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Deterministic salt for the harness.
	 *
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		return 'estat-harness-salt-' . $scheme;
	}
}

if ( ! function_exists( 'wp_hash' ) ) {
	/**
	 * Keyed hash.
	 *
	 * @param string $data   Data.
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_hash( $data, $scheme = 'auth' ) {
		return hash_hmac( 'md5', (string) $data, wp_salt( $scheme ) );
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * The method constants the plugin uses when registering routes.
	 */
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'POST, PUT, PATCH';
		const DELETABLE = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

if ( ! function_exists( '__return_true' ) ) {
	/**
	 * Always true.
	 *
	 * @return bool
	 */
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/**
	 * Always false.
	 *
	 * @return bool
	 */
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( '__return_empty_array' ) ) {
	/**
	 * Always an empty array.
	 *
	 * @return array
	 */
	function __return_empty_array() {
		return array();
	}
}

if ( ! function_exists( '__return_null' ) ) {
	/**
	 * Always null.
	 *
	 * @return null
	 */
	function __return_null() {
		return null;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * Whether a user is signed in.
	 *
	 * @return bool
	 */
	function is_user_logged_in() {
		return (int) ( $GLOBALS['estat_current_user'] ?? 0 ) > 0;
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * A small WP_Query that reads the in-memory post store, supporting the
	 * arguments the admin screens actually use.
	 */
	class WP_Query {
		/**
		 * Matched posts.
		 *
		 * @var array<int,object>
		 */
		public $posts = array();

		/**
		 * Total matches before paging.
		 *
		 * @var int
		 */
		public $found_posts = 0;

		/**
		 * Total pages.
		 *
		 * @var int
		 */
		public $max_num_pages = 0;

		/**
		 * Internal pointer.
		 *
		 * @var int
		 */
		private $pointer = 0;

		/**
		 * Run the query.
		 *
		 * @param array<string,mixed> $args Arguments.
		 */
		public function __construct( $args = array() ) {
			$type   = $args['post_type'] ?? 'post';
			$types  = (array) $type;
			$status = (array) ( $args['post_status'] ?? array( 'publish', 'draft', 'pending', 'private' ) );
			if ( in_array( 'any', $status, true ) ) {
				$status = array( 'publish', 'draft', 'pending', 'private', 'future' );
			}

			$found = array();
			foreach ( $GLOBALS['estat_posts'] as $post ) {
				if ( ! in_array( $post->post_type, $types, true ) ) {
					continue;
				}
				if ( ! in_array( $post->post_status, $status, true ) ) {
					continue;
				}
				if ( ! empty( $args['s'] ) && false === stripos( (string) $post->post_title, (string) $args['s'] ) ) {
					continue;
				}
				if ( ! empty( $args['tax_query'] ) && ! self::matches_tax_query( (int) $post->ID, (array) $args['tax_query'] ) ) {
					continue;
				}
				if ( ! empty( $args['post_mime_type'] ) && ! in_array( (string) ( $post->post_mime_type ?? '' ), (array) $args['post_mime_type'], true ) ) {
					continue;
				}
				if ( ! empty( $args['meta_query'] ) && ! self::matches_meta_query( (int) $post->ID, (array) $args['meta_query'] ) ) {
					continue;
				}
				$found[] = $post;
			}

			$this->found_posts = count( $found );

			$per_page = (int) ( $args['posts_per_page'] ?? 10 );
			if ( $per_page > 0 ) {
				$page                = max( 1, (int) ( $args['paged'] ?? 1 ) );
				$this->max_num_pages = (int) ceil( $this->found_posts / $per_page );
				$found               = array_slice( $found, ( $page - 1 ) * $per_page, $per_page );
			} else {
				$this->max_num_pages = 1;
			}

			$this->posts = array_values( $found );
		}

		/**
		 * Match a post against a simple meta_query (=, !=, EXISTS, NOT EXISTS).
		 *
		 * @param int                $post_id    Post ID.
		 * @param array<mixed,mixed> $meta_query Query clauses.
		 * @return bool
		 */
		private static function matches_meta_query( $post_id, $meta_query ) {
			foreach ( $meta_query as $key => $clause ) {
				if ( 'relation' === $key || ! is_array( $clause ) || empty( $clause['key'] ) ) {
					continue;
				}

				$compare = strtoupper( (string) ( $clause['compare'] ?? '=' ) );
				$stored  = $GLOBALS['estat_postmeta'][ $post_id ][ $clause['key'] ] ?? null;
				$exists  = null !== $stored && '' !== $stored && array() !== $stored;

				if ( 'NOT EXISTS' === $compare ) {
					if ( $exists ) {
						return false;
					}
					continue;
				}

				if ( 'EXISTS' === $compare ) {
					if ( ! $exists ) {
						return false;
					}
					continue;
				}

				$have = is_array( $stored ) ? reset( $stored ) : $stored;
				$want = $clause['value'] ?? '';

				if ( '!=' === $compare ) {
					if ( (string) $have === (string) $want ) {
						return false;
					}
					continue;
				}

				if ( (string) $have !== (string) $want ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Match a post against a simple tax_query (slug/term_id, AND only).
		 *
		 * @param int                 $post_id   Post ID.
		 * @param array<mixed,mixed>  $tax_query Query clauses.
		 * @return bool
		 */
		private static function matches_tax_query( $post_id, $tax_query ) {
			foreach ( $tax_query as $key => $clause ) {
				if ( 'relation' === $key || ! is_array( $clause ) || empty( $clause['taxonomy'] ) ) {
					continue;
				}

				$field = $clause['field'] ?? 'term_id';
				$want  = array_map( 'strval', (array) ( $clause['terms'] ?? array() ) );
				$have  = array();

				foreach ( $GLOBALS['estat_termrel'][ $post_id ][ $clause['taxonomy'] ] ?? array() as $tid ) {
					if ( ! isset( $GLOBALS['estat_terms'][ $tid ] ) ) {
						continue;
					}
					$term   = $GLOBALS['estat_terms'][ $tid ];
					$have[] = ( 'slug' === $field ) ? (string) $term['slug']
						: ( ( 'name' === $field ) ? (string) $term['name'] : (string) $tid );
				}

				if ( ! array_intersect( $want, $have ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Whether more posts remain.
		 *
		 * @return bool
		 */
		public function have_posts() {
			return $this->pointer < count( $this->posts );
		}

		/**
		 * Advance to the next post.
		 *
		 * @return void
		 */
		public function the_post() {
			$GLOBALS['post'] = $this->posts[ $this->pointer ];
			++$this->pointer;
		}

		/**
		 * Reset the pointer.
		 *
		 * @return void
		 */
		public function rewind_posts() {
			$this->pointer = 0;
		}
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	/**
	 * Current post ID inside a loop.
	 *
	 * @return int
	 */
	function get_the_ID() {
		return isset( $GLOBALS['post'] ) ? (int) $GLOBALS['post']->ID : 0;
	}
}

if ( ! function_exists( 'the_title' ) ) {
	/**
	 * Echo the current post title.
	 *
	 * @return void
	 */
	function the_title() {
		echo esc_html( get_the_title() );
	}
}

if ( ! function_exists( 'the_permalink' ) ) {
	/**
	 * Echo the current post link.
	 *
	 * @return void
	 */
	function the_permalink() {
		echo esc_url( get_permalink( get_the_ID() ) );
	}
}

if ( ! function_exists( 'the_ID' ) ) {
	/**
	 * Echo the current post ID.
	 *
	 * @return void
	 */
	function the_ID() {
		echo (int) get_the_ID();
	}
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
	/**
	 * Restore the global post after a custom loop.
	 *
	 * @return void
	 */
	function wp_reset_postdata() {
		$GLOBALS['post'] = null;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	/**
	 * Fetch a user object.
	 *
	 * @param int $user_id User ID.
	 * @return object|false
	 */
	function get_userdata( $user_id ) {
		return wp_get_current_user();
	}
}

if ( ! function_exists( 'paginate_links' ) ) {
	/**
	 * Build pagination markup.
	 *
	 * @param array $args Arguments.
	 * @return string
	 */
	function paginate_links( $args = array() ) {
		$total = (int) ( $args['total'] ?? 1 );
		if ( $total < 2 ) {
			return '';
		}

		$links = array();
		for ( $i = 1; $i <= min( $total, 5 ); $i++ ) {
			$links[] = '<a class="page-numbers" href="#">' . $i . '</a>';
		}

		// Core honours 'type' => 'array'. The stub always returned a string,
		// so any caller asking for an array silently drew nothing.
		if ( 'array' === ( $args['type'] ?? 'plain' ) ) {
			return $links;
		}

		return implode( '', $links );
	}
}

/**
 * Find a term by one of its fields.
 *
 * @param string     $field    Field name: slug, name or id.
 * @param string|int $value    Value to match.
 * @param string     $taxonomy Taxonomy.
 * @return object|false
 */
if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $taxonomy = '' ) {
		foreach ( $GLOBALS['estat_terms'] as $tid => $term ) {
			if ( $taxonomy && $term['taxonomy'] !== $taxonomy ) {
				continue;
			}
			if ( 'id' === $field || 'term_id' === $field ) {
				if ( (int) $tid === (int) $value ) {
					return (object) $term;
				}
				continue;
			}
			if ( isset( $term[ $field ] ) && (string) $term[ $field ] === (string) $value ) {
				return (object) $term;
			}
		}
		return false;
	}
}

/**
 * Render a rich text editor. The harness prints a plain textarea instead.
 *
 * @param string              $content   Initial content.
 * @param string              $editor_id Element ID.
 * @param array<string,mixed> $settings  Editor settings.
 * @return void
 */
if ( ! function_exists( 'wp_editor' ) ) {
	function wp_editor( $content, $editor_id, $settings = array() ) {
		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$d">%4$s</textarea>',
			esc_attr( (string) $editor_id ),
			esc_attr( (string) ( $settings['textarea_name'] ?? $editor_id ) ),
			(int) ( $settings['textarea_rows'] ?? 10 ),
			esc_textarea( (string) $content )
		);
	}
}

/**
 * Attachment image URL for a given size.
 *
 * @param int    $id   Attachment ID.
 * @param string $size Size name.
 * @return string|false
 */
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}
		return 'https://example.test/uploads/' . (int) $id . '-' . (string) $size . '.jpg';
	}
}

/**
 * MIME type of an attachment.
 *
 * @param int $id Attachment ID.
 * @return string|false
 */
if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( $id = 0 ) {
		$post = get_post( (int) $id );
		return $post ? (string) ( $post->post_mime_type ?? '' ) : false;
	}
}

/**
 * Path of the file behind an attachment.
 *
 * @param int $id Attachment ID.
 * @return string|false
 */
if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( $id ) {
		$path = get_post_meta( (int) $id, '_wp_attached_file', true );
		return $path ? (string) $path : false;
	}
}

/**
 * Human readable byte size.
 *
 * @param int|string $bytes    Size in bytes.
 * @param int        $decimals Decimal places.
 * @return string|false
 */
if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) {
		$bytes = (int) $bytes;
		if ( $bytes <= 0 ) {
			return false;
		}
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$power = (int) floor( log( $bytes, 1024 ) );
		$power = min( $power, count( $units ) - 1 );
		return round( $bytes / pow( 1024, $power ), (int) $decimals ) . ' ' . $units[ $power ];
	}
}

/**
 * Delete an attachment.
 *
 * @param int  $id    Attachment ID.
 * @param bool $force Skip the trash.
 * @return object|false
 */
if ( ! function_exists( 'wp_delete_attachment' ) ) {
	function wp_delete_attachment( $id, $force = false ) {
		$id   = (int) $id;
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}

		// Core removes the file from disk when $force is true. The stub only
		// dropped the record, so code that cleans up after itself looked as
		// though it had leaked files when it had not.
		if ( $force ) {
			$file = get_post_meta( $id, '_wp_attached_file', true );

			if ( is_string( $file ) && '' !== $file && is_file( $file ) ) {
				unlink( $file );
			}
		}

		unset( $GLOBALS['estat_posts'][ $id ], $GLOBALS['estat_postmeta'][ $id ] );
		return $post;
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	/**
	 * Pluck one field out of every row.
	 *
	 * @param array           $list      Rows.
	 * @param string          $field     Field to pull out.
	 * @param string|int|null $index_key Field to key the result by.
	 * @return array
	 */
	function wp_list_pluck( $list, $field, $index_key = null ) {
		$out = array();
		foreach ( (array) $list as $row ) {
			$value = is_object( $row ) ? ( $row->$field ?? null ) : ( $row[ $field ] ?? null );
			if ( null === $index_key ) {
				$out[] = $value;
				continue;
			}
			$key         = is_object( $row ) ? ( $row->$index_key ?? null ) : ( $row[ $index_key ] ?? null );
			$out[ $key ] = $value;
		}
		return $out;
	}
}


/**
 * Create an attachment post.
 *
 * The harness had no wp_insert_attachment at all, so any code path that
 * imports a file into the media library silently did nothing here while
 * working correctly in production. That is the worst kind of gap: it makes a
 * broken feature look fine and a working feature look broken.
 *
 * @param array  $args     Attachment fields.
 * @param string $file     Absolute path to the file.
 * @param int    $parent   Post to attach it to.
 * @return int
 */
if ( ! function_exists( 'wp_insert_attachment' ) ) {
	function wp_insert_attachment( $args = array(), $file = '', $parent = 0 ) {
		$args = is_array( $args ) ? $args : array();

		$id = wp_insert_post(
			array_merge(
				$args,
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'post_parent' => (int) $parent,
				)
			)
		);

		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}

		if ( '' !== $file ) {
			update_post_meta( (int) $id, '_wp_attached_file', $file );
		}

		return (int) $id;
	}
}

/**
 * Attachment metadata, which core generates from the image itself.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $file          File path.
 * @return array
 */
if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
	function wp_generate_attachment_metadata( $attachment_id, $file ) {
		return array(
			'file'  => (string) $file,
			'sizes' => array(),
		);
	}
}

if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
	function wp_update_attachment_metadata( $attachment_id, $data ) {
		return update_post_meta( (int) $attachment_id, '_wp_attachment_metadata', $data );
	}
}
