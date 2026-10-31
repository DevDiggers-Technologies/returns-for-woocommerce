<?php
/**
 * Upload helper
 *
 * Customer photos and videos are personal data: they go to a private folder with an unguessable name, are
 * renamed to random file names, blocked from direct access (.htaccess + index.php), and are only served through
 * the plugin after an ownership or capability check.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Upload;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Upload_Helper' ) ) {
	/**
	 * Upload helper class
	 */
	class DDWCWR_Upload_Helper {
		/**
		 * Private base directory.
		 *
		 * @return string
		 */
		public static function get_base_dir() {
			$uploads = wp_upload_dir( null, false );
			// Hosts can move the folder outside the web root with this filter (nginx ignores .htaccess).
			$dir = apply_filters( 'ddwcwr_private_upload_dir', trailingslashit( $uploads['basedir'] ) . 'ddwcwr-private-' . substr( hash_hmac( 'sha256', 'ddwcwr-uploads', wp_salt( 'auth' ) ), 0, 16 ) );

			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			if ( ! file_exists( $dir . '/.htaccess' ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small protection files written once.
				file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
			}

			return $dir;
		}

		/**
		 * MIME types allowed by the settings.
		 *
		 * @return array ext => mime.
		 */
		public static function get_allowed_mimes() {
			global $ddwcwr_configuration;

			$allowed = [];
			$all     = wp_get_mime_types();

			foreach ( (array) $ddwcwr_configuration['allowed_file_types'] as $ext ) {
				foreach ( $all as $exts => $mime ) {
					if ( in_array( $ext, explode( '|', $exts ), true ) ) {
						$allowed[ $ext ] = $mime;
					}
				}
			}

			// Never executable or markup types, whatever the settings say.
			unset( $allowed['php'], $allowed['svg'], $allowed['html'], $allowed['htm'], $allowed['js'] );

			return $allowed;
		}

		/**
		 * Selectable file types for the settings screen.
		 *
		 * @return array
		 */
		public static function get_file_type_options() {
			return [
				'jpg'  => 'JPG',
				'jpeg' => 'JPEG',
				'png'  => 'PNG',
				'webp' => 'WEBP',
				'gif'  => 'GIF',
				'heic' => 'HEIC',
				'mp4'  => 'MP4',
				'mov'  => 'MOV',
				'webm' => 'WEBM',
				'pdf'  => 'PDF',
			];
		}

		/**
		 * Store uploaded files from a multi-file input.
		 *
		 * @param string $field Input name.
		 * @param int    $max_files Maximum files (0 = setting).
		 * @return array|\WP_Error List of [ key, name, type, size ].
		 */
		public function handle_upload( $field = 'ddwcwr_files', $max_files = 0 ) {
			global $ddwcwr_configuration;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by every caller before files are read.
			if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
				return [];
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated per file below.
			$files = $this->normalize_files( $_FILES[ $field ] );
			$files = array_filter(
				$files,
				function ( $file ) {
					return UPLOAD_ERR_NO_FILE !== (int) $file['error'];
				}
			);

			if ( empty( $files ) ) {
				return [];
			}

			$max_files = $max_files ? $max_files : absint( $ddwcwr_configuration['max_files'] );
			$max_bytes = max( 1, (float) $ddwcwr_configuration['max_file_size'] ) * MB_IN_BYTES;
			$allowed   = self::get_allowed_mimes();

			if ( count( $files ) > $max_files ) {
				/* translators: %d: maximum number of files */
				return new \WP_Error( 'ddwcwr_upload', sprintf( _n( 'You can upload up to %d file.', 'You can upload up to %d files.', $max_files, 'wpreturns' ), $max_files ) );
			}

			$prepared = [];

			foreach ( $files as $file ) {
				$name = sanitize_file_name( $file['name'] );

				if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
					/* translators: %s: file name */
					return new \WP_Error( 'ddwcwr_upload', sprintf( __( '%s could not be uploaded. Please try again.', 'wpreturns' ), $name ) );
				}

				if ( (int) $file['size'] > $max_bytes ) {
					/* translators: 1: file name, 2: size limit in MB */
					return new \WP_Error( 'ddwcwr_upload', sprintf( __( '%1$s is larger than %2$s MB.', 'wpreturns' ), $name, $ddwcwr_configuration['max_file_size'] ) );
				}

				$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, $allowed );

				if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
					/* translators: 1: file name, 2: allowed extensions */
					return new \WP_Error( 'ddwcwr_upload', sprintf( __( '%1$s is not an allowed file type. Allowed: %2$s.', 'wpreturns' ), $name, implode( ', ', array_keys( $allowed ) ) ) );
				}

				$prepared[] = [ $file, $name, $check ];
			}

			$stored = [];
			$base   = self::get_base_dir();
			$sub    = gmdate( 'Y/m' );

			wp_mkdir_p( $base . '/' . $sub );

			foreach ( $prepared as list( $file, $name, $check ) ) {
				$key = $sub . '/' . wp_generate_password( 32, false, false ) . '.' . $check['ext'];

				// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Moving a validated upload into the private folder.
				if ( ! move_uploaded_file( $file['tmp_name'], $base . '/' . $key ) ) {
					return new \WP_Error( 'ddwcwr_upload', __( 'The file could not be saved. Please try again.', 'wpreturns' ) );
				}

				$stored[] = [
					'key'  => $key,
					'name' => $name,
					'type' => $check['type'],
					'size' => (int) $file['size'],
				];
			}

			return $stored;
		}

		/**
		 * Copy an admin-uploaded file (e.g. a label) from a media attachment path into the private folder.
		 *
		 * @param string $path Source path.
		 * @param string $name Original name.
		 * @return array|\WP_Error
		 */
		public function store_file( $path, $name ) {
			$check = wp_check_filetype_and_ext(
				$path,
				$name,
				self::get_allowed_mimes() + [
					'pdf' => 'application/pdf',
					'png' => 'image/png',
					'jpg' => 'image/jpeg',
					'zpl' => 'text/plain',
				]
			);

			if ( empty( $check['ext'] ) ) {
				return new \WP_Error( 'ddwcwr_upload', __( 'Labels must be a PDF or an image.', 'wpreturns' ) );
			}

			$sub = gmdate( 'Y/m' );
			$key = $sub . '/' . wp_generate_password( 32, false, false ) . '.' . $check['ext'];

			wp_mkdir_p( self::get_base_dir() . '/' . $sub );

			if ( ! copy( $path, self::get_base_dir() . '/' . $key ) ) {
				return new \WP_Error( 'ddwcwr_upload', __( 'The file could not be saved.', 'wpreturns' ) );
			}

			return [
				'key'  => $key,
				'name' => sanitize_file_name( $name ),
				'type' => $check['type'],
				'size' => (int) filesize( self::get_base_dir() . '/' . $key ),
			];
		}

		/**
		 * Absolute path of a stored file, or false when the key is not valid.
		 *
		 * @param string $key Key.
		 * @return string|false
		 */
		public function get_path( $key ) {
			if ( ! preg_match( '#^\d{4}/\d{2}/[A-Za-z0-9]{32}\.[a-z0-9]{2,5}$#', (string) $key ) ) {
				return false;
			}

			$path = self::get_base_dir() . '/' . $key;

			return file_exists( $path ) ? $path : false;
		}

		/**
		 * Delete stored files.
		 *
		 * @param array $files Attachments list.
		 * @return void
		 */
		public function delete_files( $files ) {
			foreach ( (array) $files as $file ) {
				$path = ! empty( $file['key'] ) ? $this->get_path( $file['key'] ) : false;

				if ( $path ) {
					wp_delete_file( $path );
				}
			}
		}

		/**
		 * Stream a stored file to the browser.
		 *
		 * @param string $key Key.
		 * @param string $name Download name.
		 * @return void
		 */
		public function serve( $key, $name = '' ) {
			$path = $this->get_path( $key );

			if ( ! $path ) {
				wp_die( esc_html__( 'File not found.', 'wpreturns' ), '', [ 'response' => 404 ] );
			}

			$type   = wp_check_filetype( $path );
			$inline = $type['type'] && ( 0 === strpos( $type['type'], 'image/' ) || 0 === strpos( $type['type'], 'video/' ) || 'application/pdf' === $type['type'] );

			nocache_headers();
			header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . sanitize_file_name( $name ? $name : basename( $path ) ) . '"' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a private file.
			readfile( $path );
			exit;
		}

		/**
		 * Normalize the $_FILES structure of a multiple input.
		 *
		 * @param array $raw Raw entry.
		 * @return array
		 */
		protected function normalize_files( $raw ) {
			$files = [];

			if ( ! is_array( $raw['name'] ) ) {
				return [ $raw ];
			}

			foreach ( array_keys( $raw['name'] ) as $index ) {
				$files[] = [
					'name'     => $raw['name'][ $index ],
					'type'     => $raw['type'][ $index ],
					'tmp_name' => $raw['tmp_name'][ $index ],
					'error'    => $raw['error'][ $index ],
					'size'     => $raw['size'][ $index ],
				];
			}

			return $files;
		}
	}
}
