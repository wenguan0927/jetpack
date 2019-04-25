<?php

class Jetpack_Data {
	/**
	 * Gets locally stored token
	 *
	 * @param int|false $user_id false: Return the Blog Token. int: Return that user's User Token.
	 * @param string|false $token_key: If provided, check that the stored token matches the provided input.
	 *                                 false           : Use first token. Default.
	 *                                 ';stored;'      : Use first stored token. (Stored tokens are the normal kind.)
	 *                                 non-empty string: Use matching token
	 * @return object|false
	 */
	public static function get_access_token( $user_id = false, $token_key = false ) {
		if ( $user_id ) {
			if ( !$user_tokens = Jetpack_Options::get_option( 'user_tokens' ) ) {
				return false;
			}
			if ( $user_id === JETPACK_MASTER_USER ) {
				if ( !$user_id = Jetpack_Options::get_option( 'master_user' ) ) {
					return false;
				}
			}
			if ( !isset( $user_tokens[$user_id] ) || !$token = $user_tokens[$user_id] ) {
				return false;
			}
			$token_chunks = explode( '.', $token );
			if ( empty( $token_chunks[1] ) || empty( $token_chunks[2] ) ) {
				return false;
			}
			if ( $user_id != $token_chunks[2] ) {
				return false;
			}
			$tokens = array( "{$token_chunks[0]}.{$token_chunks[1]}" );
		} else {
			$tokens = defined( 'JETPACK__BLOG_TOKEN' ) && ';stored;' !== $token_key
				? array( JETPACK__BLOG_TOKEN )
				: array();

			$token = Jetpack_Options::get_option( 'blog_token' );
			if ( empty( $token ) && empty( $tokens ) ) {
				return false;
			}

			$tokens[] = $token;
		}

		if ( false === $token_key ) {
			// Use first token.
			$token = $tokens[0];
		} elseif ( ';stored;' === $token_key ) {
			// Use first stored token.
			$token = $tokens[0]; // $tokens only contains stored tokens because of earlier check.
		} else {
			// Use the token matching $token_key or false if none.
			// Ensure we check the full key.
			$token_check = rtrim( $token_key, '.' ) . '.';

			$valid_token = false;
			foreach ( $tokens as $token ) {
				if ( hash_equals( substr( $token, 0, strlen( $token_check ) ), $token_check ) ) {
					$valid_token = $token;
				}
			}

			if ( ! $valid_token ) {
				return false;
			}

			$token = $valid_token;
		}

		return (object) array(
			'secret' => $token,
			'external_user_id' => (int) $user_id,
		);
	}

	/**
	 * This function mirrors Jetpack_Data::is_usable_domain() in the WPCOM codebase.
	 *
	 * @param $domain
	 * @param array $extra
	 *
	 * @return bool|WP_Error
	 */
	public static function is_usable_domain( $domain, $extra = array() ) {

		// If it's empty, just fail out.
		if ( ! $domain ) {
			return new WP_Error( 'fail_domain_empty', sprintf( __( 'Domain `%1$s` just failed is_usable_domain check as it is empty.', 'jetpack' ), $domain ) );
		}

		/**
		 * Skips the usuable domain check when connecting a site.
		 *
		 * Allows site administrators with domains that fail gethostname-based checks to pass the request to WP.com
		 *
		 * @since 4.1.0
		 *
		 * @param bool If the check should be skipped. Default false.
		 */
		if ( apply_filters( 'jetpack_skip_usuable_domain_check', false ) ) {
			return true;
		}

		// None of the explicit localhosts.
		$forbidden_domains = array(
			'wordpress.com',
			'localhost',
			'localhost.localdomain',
			'127.0.0.1',
			'local.wordpress.test',         // VVV
			'local.wordpress-trunk.test',   // VVV
			'src.wordpress-develop.test',   // VVV
			'build.wordpress-develop.test', // VVV
		);
		if ( in_array( $domain, $forbidden_domains ) ) {
			return new WP_Error( 'fail_domain_forbidden', sprintf( __( 'Domain `%1$s` just failed is_usable_domain check as it is in the forbidden array.', 'jetpack' ), $domain ) );
		}

		// No .test or .local domains
		if ( preg_match( '#\.(test|local)$#i', $domain ) ) {
			return new WP_Error( 'fail_domain_tld', sprintf( __( 'Domain `%1$s` just failed is_usable_domain check as it uses an invalid top level domain.', 'jetpack' ), $domain ) );
		}

		// No WPCOM subdomains
		if ( preg_match( '#\.wordpress\.com$#i', $domain ) ) {
			return new WP_Error( 'fail_subdomain_wpcom', sprintf( __( 'Domain `%1$s` just failed is_usable_domain check as it is a subdomain of WordPress.com.', 'jetpack' ), $domain ) );
		}

		// If PHP was compiled without support for the Filter module (very edge case)
		if ( ! function_exists( 'filter_var' ) ) {
			// Just pass back true for now, and let wpcom sort it out.
			return true;
		}

		return true;
	}

	/**
	 * Returns true if the IP address passed in should not be in a reserved range, even if PHP says that it is.
	 * See: https://bugs.php.net/bug.php?id=66229 and https://github.com/php/php-src/commit/d1314893fd1325ca6aa0831101896e31135a2658
	 *
	 * This function mirrors Jetpack_Data::php_bug_66229_check() in the WPCOM codebase.
	 */
	public static function php_bug_66229_check( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$ip_arr = array_map( 'intval', explode( '.', $ip ) );

		if ( 128 == $ip_arr[0] && 0 == $ip_arr[1] ) {
			return true;
		}

		if ( 191 == $ip_arr[0] && 255 == $ip_arr[1] ) {
			return true;
		}

		return false;
	}
}
