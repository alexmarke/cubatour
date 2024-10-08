<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'cubatour' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '~$3P*XFSr I@I!>2s[-8Se)S:_Od`B6ZBGZJ? &Iu dF9uZJsyS>K5nQqN-[U3jH' );
define( 'SECURE_AUTH_KEY',  'd]7FIm!oz>iC/^H^c[<2#G^tfC]g;R8&*yfroUS%}A!K>WDVgy;8)x)95FQpkmHn' );
define( 'LOGGED_IN_KEY',    '?ald$N5/~P5FqhO&dYt/+u @][b&JH[SGgI#6h:t9CQ@<Ne0Nd!$:(6Gms3*lv>9' );
define( 'NONCE_KEY',        'Cm<,NzheY`2B~f,l29??oJ&Q*-:5Z,H>lp#&M/)jtg1Vq(QZ`*s<!R7K_!tNC?t&' );
define( 'AUTH_SALT',        '#|1Z2*vDzi-|$l>Qn`lke}jNEe}| 7k/h/Cwnge_^iXNtwc{&:)}q|at(6N3iuxd' );
define( 'SECURE_AUTH_SALT', 'vaG[*k6w-Xq>SX<!NT%(favxFYN6[ayER-^N%N>4<2)JUr,!]Kd47;s%ALd]{C2#' );
define( 'LOGGED_IN_SALT',   '(zmz~sJor)Q)/-i35dC2.PTat_Jjn(FjK{`,wHbx|q(zK_8BAxrTl.=#gsH,l%7g' );
define( 'NONCE_SALT',       '/NY4<n^`}CP,>|kp?o,zuB?( 6S7pov w+ck=7?d0?Z~^XvZF@66gk3(`jdkjpZB' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
