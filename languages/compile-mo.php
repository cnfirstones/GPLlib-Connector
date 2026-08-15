<?php


define( 'GPLLIB_CONNECTOR_COMPILE_MO', true );

class GPLlib_Connector_Po_To_Mo_Compiler {

	
	private array $entries = [];
	private string $headers = '';

	public function compile( string $po_path, string $mo_path ): void {
		$this->entries = [];
		$this->headers = '';
		$this->parse( $po_path );
		$this->write( $mo_path );
	}

	private function parse( string $po_path ): void {
		$lines = file( $po_path, FILE_IGNORE_NEW_LINES );
		if ( $lines === false ) {
			throw new RuntimeException( "无法读取: $po_path" );
		}

		$state       = null; 
		$msgid       = '';
		$msgidPlural = null;
		$msgstrs     = [];
		$curIndex    = 0;

		$flush = function () use ( &$msgid, &$msgidPlural, &$msgstrs ) {
			if ( $msgid === '' && empty( $msgstrs ) ) {
				return;
			}
			if ( $msgid === '' ) {
				
				$this->headers = $msgstrs[0] ?? '';
			} else {
				$this->entries[ $msgid ] = [
					'msgid_plural' => $msgidPlural,
					'msgstr'       => $msgstrs,
				];
			}
			$msgid       = '';
			$msgidPlural = null;
			$msgstrs     = [];
		};

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( $line === '' || $line[0] === '#' ) {
				if ( $line === '' ) {
					$flush();
				}
				continue;
			}

			if ( preg_match( '/^msgid_plural\s+"(.*)"$/', $line, $m ) ) {
				$msgidPlural = $this->unescape( $m[1] );
				$state       = 'msgid_plural';
				continue;
			}

			if ( preg_match( '/^msgid\s+"(.*)"$/', $line, $m ) ) {
				$flush();
				$msgid = $this->unescape( $m[1] );
				$state = 'msgid';
				continue;
			}

			if ( preg_match( '/^msgstr\[(\d+)\]\s+"(.*)"$/', $line, $m ) ) {
				$curIndex            = (int) $m[1];
				$msgstrs[ $curIndex ] = $this->unescape( $m[2] );
				$state               = 'msgstr[n]';
				continue;
			}

			if ( preg_match( '/^msgstr\s+"(.*)"$/', $line, $m ) ) {
				$msgstrs[0] = $this->unescape( $m[1] );
				$state      = 'msgstr';
				continue;
			}

			
			if ( preg_match( '/^"(.*)"$/', $line, $m ) ) {
				$piece = $this->unescape( $m[1] );
				switch ( $state ) {
					case 'msgid':
						$msgid .= $piece;
						break;
					case 'msgid_plural':
						$msgidPlural .= $piece;
						break;
					case 'msgstr':
						$msgstrs[0] .= $piece;
						break;
					case 'msgstr[n]':
						$msgstrs[ $curIndex ] .= $piece;
						break;
				}
			}
		}
		$flush();
	}

	private function unescape( string $s ): string {
		return str_replace(
			[ '\\n', '\\t', '\\r', '\\"', '\\\\' ],
			[ "\n", "\t", "\r", '"', '\\' ],
			$s
		);
	}

	private function write( string $mo_path ): void {
		$keys    = [];
		$values  = [];

		
		$keys[]   = '';
		$values[] = $this->headers;

		foreach ( $this->entries as $msgid => $data ) {
			if ( $data['msgid_plural'] !== null ) {
				$key   = $msgid . "\0" . $data['msgid_plural'];
				$value = implode( "\0", $data['msgstr'] );
			} else {
				$key   = $msgid;
				$value = $data['msgstr'][0] ?? '';
			}
			$keys[]   = $key;
			$values[] = $value;
		}

		$count       = count( $keys );
		$originals   = '';
		$translations = '';
		$originalOffsets    = [];
		$translationOffsets = [];

		
		$headerSize = 28;
		$tableSize  = $count * 8; 
		$offset     = $headerSize + $tableSize * 2;

		foreach ( $keys as $k ) {
			$len = strlen( $k );
			$originalOffsets[] = [ $len, $offset ];
			$offset += $len + 1; 
		}
		foreach ( $values as $v ) {
			$len = strlen( $v );
			$translationOffsets[] = [ $len, $offset ];
			$offset += $len + 1;
		}

		$originalsBlob   = implode( "\0", $keys ) . "\0";
		$translationsBlob = implode( "\0", $values ) . "\0";

		$data  = pack( 'V', 0x950412de ); 
		$data .= pack( 'V', 0 );          
		$data .= pack( 'V', $count );     
		$data .= pack( 'V', $headerSize ); 
		$data .= pack( 'V', $headerSize + $tableSize ); 
		$data .= pack( 'V', 0 ); 
		$data .= pack( 'V', $headerSize + $tableSize * 2 ); 

		foreach ( $originalOffsets as [ $len, $off ] ) {
			$data .= pack( 'VV', $len, $off );
		}
		foreach ( $translationOffsets as [ $len, $off ] ) {
			$data .= pack( 'VV', $len, $off );
		}
		$data .= $originalsBlob;
		$data .= $translationsBlob;

		if ( file_put_contents( $mo_path, $data ) === false ) {
			throw new RuntimeException( "无法写入: $mo_path" );
		}
	}
}

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

$dir      = __DIR__;
$compiler = new GPLlib_Connector_Po_To_Mo_Compiler();
$argv1    = $argv[1] ?? null;

$files = $argv1
	? [ $dir . '/' . basename( $argv1 ) ]
	: glob( $dir . '/*.po' );

if ( empty( $files ) ) {
	fwrite( STDERR, "未找到 .po 文件\n" );
	exit( 1 );
}

foreach ( $files as $po_file ) {
	if ( ! is_file( $po_file ) ) {
		fwrite( STDERR, "跳过（不存在）: $po_file\n" );
		continue;
	}
	
	$mo_file = preg_replace( '/\.po$/', '.mo', $po_file );

	try {
		$compiler->compile( $po_file, $mo_file );
		echo 'OK: ' . basename( $po_file ) . ' → ' . basename( $mo_file ) . "\n";
	} catch ( \Throwable $e ) {
		fwrite( STDERR, 'FAIL: ' . basename( $po_file ) . ' - ' . $e->getMessage() . "\n" );
		exit( 1 );
	}
}
