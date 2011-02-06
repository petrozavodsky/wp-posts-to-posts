<?php

// Automated testing suite for the Posts 2 Posts plugin

class P2P_Test {

	function init() {
		if ( !function_exists('p2p_register_connection_type') )
			return;

		add_action('init', array(__CLASS__, '_init'));
#		add_action('admin_init', array(__CLASS__, 'setup'));
		add_action('load-index.php', array(__CLASS__, 'test'));
#		add_action('load-index.php', array(__CLASS__, 'debug'));
	}

	function _init() {
		register_post_type('actor', array('label' => 'Actors', 'public' => true));
		register_post_type('movie', array('label' => 'Movies', 'public' => true));

#		p2p_register_connection_type('actor', 'actor', true);
		p2p_register_connection_type('actor', 'movie', true);
	}

	function setup() {
		global $wpdb;

		$wpdb->query("DELETE FROM $wpdb->posts WHERE post_type IN ('actor', 'movie')");
		$wpdb->query("TRUNCATE $wpdb->p2p");
		$wpdb->query("TRUNCATE $wpdb->p2pmeta");

		$movie_ids = $actor_ids = array();

		for ( $i=0; $i<20; $i++ ) {
			$actor_ids[] = wp_insert_post(array(
				'post_type' => 'actor',
				'post_title' => "Actor $i",
				'post_status' => 'publish'
			));

			$movie_ids[] = wp_insert_post(array(
				'post_type' => 'movie',
				'post_title' => "Movie $i",
				'post_status' => 'publish'
			));
		}
	}

	function test() {
		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_WARNING, 0);
		assert_options(ASSERT_QUIET_EVAL, 1);

		$failed = false;

		assert_options(ASSERT_CALLBACK, function ($file, $line, $code) use ( &$failed ) {
			$failed = true;
		
			echo "<hr>Assertion Failed (line $line):<br />
				<code>$code</code><br /><hr />";

			add_action('admin_notices', array(__CLASS__, 'debug'));
		});

		$actor_ids = get_posts( array(
			'fields' => 'ids',
			'post_type' => 'actor',
			'post_status' => 'any',
			'orderby' => 'post_title',
			'order' => 'asc',
			'nopaging' => true
		) );

		$movie_ids = get_posts( array(
			'fields' => 'ids',
			'post_type' => 'movie',
			'post_status' => 'any',
			'orderby' => 'post_title',
			'order' => 'asc',
			'nopaging' => true
		) );

		p2p_connect( array_slice( $actor_ids, 0, 5 ), array_slice( $movie_ids, 0, 3 ) );
		p2p_connect( $movie_ids[0], $actor_ids[10] );

		assert( 'array_slice( $movie_ids, 0, 3 ) == array_values( p2p_get_connected( $actor_ids[0] ) )' );
		assert( 'array( $actor_ids[0], $actor_ids[10] ) == array_values( p2p_get_connected( $movie_ids[0] ) )' );

		assert( "true == p2p_is_connected( $actor_ids[0], $movie_ids[0] )" );
		assert( "false == p2p_is_connected( $actor_ids[0], $movie_ids[10] )" );

#		$query = new WP_Query( array(
#			'connected' => 17071,
#			'connected_meta' => array(
#				array(
#					'key' => 'foo',
#					'value' => 'bar'
#				)
#			)
#		) );

#		debug($query->posts);

		if ( $failed )
			self::debug();
	}

	function debug() {
		global $wpdb;

		$rows = $wpdb->get_results("SELECT * FROM $wpdb->p2p");

		foreach ( $rows as $row ) {
			echo html_link( get_edit_post_link( $row->p2p_from ), $row->p2p_from ) . ' -> ';
			echo html_link( get_edit_post_link( $row->p2p_to ), $row->p2p_to );
			echo '<br>';
		}

		die;
	}
}

add_action( 'plugins_loaded', array('P2P_Test', 'init'), 11 );
