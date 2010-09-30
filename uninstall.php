<?php

delete_option( 'jpb_custom_post_permalinks_settings' );

delete_option( 'jpb_custom_post_permalinks_version' );

global $wp_rewrite;

$post_types = get_post_types( array( '_builtin' => false, 'publicly_queryable' => true ), 'object' );

foreach( $post_types as $pt => $t ){
	if( false == $t->rewrite && isset($wp_rewrite->extra_permastructs[$pt] ) )
		unset( $wp_rewrite->extra_permastructs[$pt] );
	elseif( false != $t->rewrite )
		$wp_rewrite->add_permastruct( $pt, "{$t->rewrite['slug']}/%$pt%", $t->rewrite['with_front'], $t->permalink_epmask );
}

$wp_rewrite->flush_rules( true );

?>