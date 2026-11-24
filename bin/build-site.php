<?php
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

class WMDS_Site_Builder {

	const ZIP = 'wp-mobile-de-sync.zip';

	/** @var string */
	private $root;

	/** @var string */
	private $out;

	/** @var string */
	private $site_url;

	/** @var string */
	private $repo;

	/** @var MarkdownConverter */
	private $converter;

	/** @var array<string,int> */
	private $ids = array();

	/** @var string */
	private $id_prefix = '';

	/** @var array<int,array{level:int,id:string,text:string}> */
	private $toc = array();

	/** @var array<string,string> */
	private $pages = array();

	/** @var array<int,array{page:string,fragment:string,source:string}> */
	private $links = array();

	/** @var array<int,string> */
	private $errors = array();

	/** @var array<string,string> */
	private $meta = array();

	/** @var array<int,array{slug:string,source:string,title:string,summary:string}> */
	private $docs = array();

	/** @var array<int,array{version:string,date:string,body:string}> */
	private $releases = array();

	/** @var array{src:string,alt:string,width:int,height:int}|null */
	private $image;

	/**
	 * @param string $root
	 * @param string $out
	 */
	public function __construct( $root, $out ) {
		$this->root = rtrim( $root, '/' );
		$this->out  = rtrim( $out, '/' );

		$url            = getenv( 'SITE_URL' );
		$this->site_url = rtrim( $url ? $url : 'https://dmnktoe.github.io/wp-mobile-de-sync', '/' ) . '/';

		$repo       = getenv( 'GITHUB_REPOSITORY' );
		$this->repo = $repo ? $repo : 'dmnktoe/wp-mobile-de-sync';

		$environment = new Environment(
			array(
				'html_input'         => 'allow',
				'allow_unsafe_links' => false,
			)
		);
		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new GithubFlavoredMarkdownExtension() );
		$environment->addEventListener( DocumentParsedEvent::class, array( $this, 'on_parsed' ) );

		$this->converter = new MarkdownConverter( $environment );
	}

	/**
	 * @return int
	 */
	public function run() {
		$this->meta     = $this->read_meta();
		$this->releases = $this->read_changelog();
		$this->docs     = $this->read_docs();

		if ( ! $this->releases || $this->releases[0]['version'] !== $this->meta['version'] ) {
			$this->errors[] = sprintf(
				'The plugin header states %s, but the newest CHANGELOG.md entry is %s.',
				$this->meta['version'],
				$this->releases ? $this->releases[0]['version'] : 'missing'
			);
		}

		$readme      = $this->read( 'README.md' );
		$intro       = substr( $readme, 0, (int) strpos( $readme, "\n## " ) );
		$images      = $this->images( $intro, 'README.md' );
		$this->image = $images ? $images[0] : null;

		$this->build_home( $readme );

		foreach ( $this->docs as $doc ) {
			$this->build_doc( $doc );
		}

		$this->build_changelog();
		$this->build_not_found();
		$this->check_links();

		if ( $this->errors ) {
			fwrite( STDERR, implode( "\n", array_unique( $this->errors ) ) . "\n" );
			return 1;
		}

		$this->write_files();

		printf( "Built %d pages for %s into %s\n", count( $this->pages ), $this->meta['version'], $this->out );
		return 0;
	}

	/**
	 * @return array<string,string>
	 */
	private function read_meta() {
		$plugin = $this->read( 'wp-mobile-de-sync.php' );
		$readme = $this->read( 'readme.txt' );

		$header = function ( $text, $name ) {
			if ( preg_match( '/^[ \t\/*#]*' . preg_quote( $name, '/' ) . ':\s*(.+)$/mi', $text, $match ) ) {
				return trim( $match[1] );
			}
			return '';
		};

		return array(
			'name'         => $header( $plugin, 'Plugin Name' ),
			'version'      => $header( $plugin, 'Version' ),
			'description'  => $header( $plugin, 'Description' ),
			'author'       => $header( $plugin, 'Author' ),
			'author_url'   => $header( $plugin, 'Author URI' ),
			'license'      => $header( $plugin, 'License' ),
			'license_url'  => $header( $readme, 'License URI' ),
			'requires_wp'  => $header( $plugin, 'Requires at least' ),
			'tested_wp'    => $header( $readme, 'Tested up to' ),
			'requires_php' => $header( $plugin, 'Requires PHP' ),
		);
	}

	/**
	 * @return array<int,array{version:string,date:string,body:string}>
	 */
	private function read_changelog() {
		$parts    = preg_split( '/^## /m', $this->read( 'CHANGELOG.md' ) );
		$releases = array();

		array_shift( $parts );

		foreach ( $parts as $part ) {
			list( $heading, $body ) = array_pad( explode( "\n", $part, 2 ), 2, '' );

			if ( ! preg_match( '/^\[?([0-9][0-9A-Za-z.\-]*)\]?\s*(?:[–—-]\s*(\d{4}-\d{2}-\d{2}))?/u', trim( $heading ), $match ) ) {
				$this->errors[] = 'CHANGELOG.md: cannot read the heading "## ' . trim( $heading ) . '".';
				continue;
			}

			$releases[] = array(
				'version' => $match[1],
				'date'    => isset( $match[2] ) ? $match[2] : '',
				'body'    => trim( $body ),
			);
		}

		return $releases;
	}

	/**
	 * @return array<int,array{slug:string,source:string,title:string,summary:string}>
	 */
	private function read_docs() {
		$listed = array();
		$docs   = array();

		if ( preg_match_all( '/^\|\s*\[([^\]]+)\]\((docs\/[\w-]+\.md)\)\s*\|\s*(.+?)\s*\|\s*$/m', $this->read( 'README.md' ), $rows, PREG_SET_ORDER ) ) {
			foreach ( $rows as $row ) {
				$listed[ $row[2] ] = array( $row[1], $row[3] );
			}
		}

		$files = glob( $this->root . '/docs/*.md' );
		sort( $files );

		foreach ( $files as $file ) {
			$source = 'docs/' . basename( $file );
			if ( ! isset( $listed[ $source ] ) ) {
				$listed[ $source ] = array( '', '' );
			}
		}

		foreach ( $listed as $source => $row ) {
			if ( ! is_file( $this->root . '/' . $source ) ) {
				$this->errors[] = "README.md lists {$source}, which does not exist.";
				continue;
			}

			$title = $row[0];
			if ( '' === $title && preg_match( '/^# (.+)$/m', $this->read( $source ), $match ) ) {
				$title = trim( $match[1] );
			}

			$docs[] = array(
				'slug'    => basename( $source, '.md' ),
				'source'  => $source,
				'title'   => '' !== $title ? $title : basename( $source, '.md' ),
				'summary' => $row[1],
			);
		}

		return $docs;
	}

	/**
	 * @param string $readme
	 */
	private function build_home( $readme ) {
		$parts       = preg_split( '/^## (.+)$/m', $readme, -1, PREG_SPLIT_DELIM_CAPTURE );
		$intro       = array_shift( $parts );
		$sections    = array();
		$highlights  = array();
		$screenshots = array();

		$tagline = preg_match( '/^\*\*(.+)\*\*\s*$/m', $intro, $match ) ? $match[1] : $this->meta['description'];

		$close = strrpos( $intro, '</div>' );
		$lead  = false === $close ? '' : substr( $intro, $close + 6 );
		$lead  = trim( preg_replace( '/^\s*<br>/', '', $lead ) );

		$total = count( $parts );

		for ( $i = 0; $i < $total; $i += 2 ) {
			$title = trim( $parts[ $i ] );
			$body  = trim( $parts[ $i + 1 ] );

			if ( 'License' === $title ) {
				continue;
			}

			if ( 'Highlights' === $title ) {
				$screenshots = $this->images( $body, 'README.md' );
				$highlights  = $this->split_highlights( $body );
				continue;
			}

			$sections[] = array(
				'id'    => $this->slug( $title ),
				'title' => $title,
				'kind'  => 'Documentation' === $title ? 'docs' : 'prose',
				'html'  => 'Documentation' === $title ? '' : $this->markdown( $body, 'README.md', 'index.html' ),
			);
		}

		$body = $this->template(
			'home',
			array(
				'tagline'     => $tagline,
				'lead'        => $this->markdown( $lead, 'README.md', 'index.html' ),
				'hero_image'  => $this->image,
				'highlights'  => $highlights,
				'screenshots' => $screenshots,
				'releases'    => $this->recent_releases( 3 ),
				'sections'    => $sections,
			)
		);

		$this->page(
			'index.html',
			array(
				'title'       => $this->meta['name'] . ' — ' . $this->strip( $tagline ),
				'description' => $this->strip( $this->meta['description'] ),
				'active'      => 'home',
				'body'        => $body,
				'jsonld'      => $this->software_jsonld(),
			)
		);
	}

	/**
	 * @param string $markdown
	 * @return array<int,array{title:string,html:string}>
	 */
	private function split_highlights( $markdown ) {
		$markdown = preg_replace( '/^<p[^>]*>.*<\/p>\s*$/m', '', $markdown );
		$parts    = preg_split( '/^\*\*(.+)\*\*\s*$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE );
		$groups   = array();

		array_shift( $parts );
		$total = count( $parts );

		for ( $i = 0; $i < $total; $i += 2 ) {
			$groups[] = array(
				'title' => trim( $parts[ $i ] ),
				'html'  => $this->markdown( trim( $parts[ $i + 1 ] ), 'README.md', 'index.html' ),
			);
		}

		return $groups;
	}

	/**
	 * @param string $markdown
	 * @param string $source
	 * @return array<int,array{src:string,alt:string,width:int,height:int}>
	 */
	private function images( $markdown, $source ) {
		$images = array();

		if ( ! preg_match_all( '/<img src="([^"]+\.(?:jpe?g|png|webp|gif))" alt="([^"]*)"/', $markdown, $tags, PREG_SET_ORDER ) ) {
			return $images;
		}

		foreach ( $tags as $tag ) {
			$size = @getimagesize( $this->root . '/' . $this->resolve( dirname( $source ), $tag[1] ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file is reported by link().

			$images[] = array(
				'src'    => $this->link( $tag[1], $source, 'index.html' ),
				'alt'    => $tag[2],
				'width'  => $size ? $size[0] : 0,
				'height' => $size ? $size[1] : 0,
			);
		}

		return $images;
	}

	/**
	 * @param int $count
	 * @return array<int,array{version:string,date:string,items:array<int,string>,more:int}>
	 */
	private function recent_releases( $count ) {
		$recent = array();

		foreach ( array_slice( $this->releases, 0, $count ) as $release ) {
			$items = array();

			if ( preg_match_all( '/^- (.+(?:\n {2,}.+)*)/m', $release['body'], $entries ) ) {
				foreach ( $entries[1] as $entry ) {
					$entry = preg_replace( '/\s+/', ' ', $entry );
					$lead  = preg_match( '/^\*\*(.+?)\*\*/', $entry, $match )
						? $match[1]
						: preg_replace( '/^(.+?[.:])\s.*$/', '$1', $entry );

					$items[] = $this->md( rtrim( $lead, '.:' ), 'CHANGELOG.md' );
				}
			}

			$recent[] = array(
				'version' => $release['version'],
				'date'    => $release['date'],
				'items'   => array_slice( $items, 0, 4 ),
				'more'    => max( 0, count( $items ) - 4 ),
			);
		}

		return $recent;
	}

	/**
	 * @param array{slug:string,source:string,title:string,summary:string} $doc
	 */
	private function build_doc( $doc ) {
		$file     = $doc['slug'] . '.html';
		$markdown = $this->read( $doc['source'] );

		$markdown = preg_replace( '/^# .+\n+/m', '', $markdown, 1 );
		$markdown = preg_replace( '/^\[←[^\]]*\]\([^)]*\)\s*\n+/m', '', $markdown, 1 );
		$markdown = preg_replace( '/^(- \[[^\]]+\]\(#[^)]+\)\n)+\n*/', '', ltrim( $markdown ), 1 );

		$html    = $this->markdown( $markdown, $doc['source'], $file );
		$toc     = $this->toc;
		$summary = '' !== $doc['summary'] ? ucfirst( $doc['summary'] ) . '.' : '';

		$body = $this->template(
			'doc',
			array(
				'doc'     => $doc,
				'summary' => $summary,
				'html'    => $html,
				'toc'     => $toc,
				'edit'    => $this->github( 'edit', $doc['source'] ),
			)
		);

		$this->page(
			$file,
			array(
				'title'       => $doc['title'] . ' — ' . $this->meta['name'],
				'description' => '' !== $summary ? $doc['title'] . ': ' . $doc['summary'] . '.' : $doc['title'],
				'active'      => $doc['slug'],
				'body'        => $body,
			)
		);
	}

	private function build_changelog() {
		$markdown = $this->read( 'CHANGELOG.md' );
		$intro    = trim( preg_replace( '/^# .+\n/', '', substr( $markdown, 0, (int) strpos( $markdown, "\n## " ) ) ) );
		$entries  = array();

		foreach ( $this->releases as $release ) {
			$entries[] = array(
				'id'      => 'v' . $release['version'],
				'version' => $release['version'],
				'date'    => $release['date'],
				'html'    => $this->markdown( $release['body'], 'CHANGELOG.md', 'changelog.html', 'v' . $release['version'] . '-' ),
				'url'     => 'https://github.com/' . $this->repo . '/releases/tag/v' . rawurlencode( $release['version'] ),
			);
		}

		$body = $this->template(
			'changelog',
			array(
				'intro'   => $this->markdown( $intro, 'CHANGELOG.md', 'changelog.html' ),
				'entries' => $entries,
				'edit'    => $this->github( 'edit', 'CHANGELOG.md' ),
			)
		);

		$this->page(
			'changelog.html',
			array(
				'title'       => 'Changelog — ' . $this->meta['name'],
				'description' => 'Every release of ' . $this->meta['name'] . ', newest first.',
				'active'      => 'changelog',
				'body'        => $body,
			)
		);
	}

	private function build_not_found() {
		$this->page(
			'404.html',
			array(
				'title'       => 'Page not found — ' . $this->meta['name'],
				'description' => 'There is no page at this address.',
				'active'      => '',
				'body'        => $this->template( 'not-found', array() ),
				'noindex'     => true,
			)
		);
	}

	/**
	 * @param string              $file
	 * @param array<string,mixed> $page
	 */
	private function page( $file, $page ) {
		$page = array_merge(
			array(
				'file'    => $file,
				'url'     => $this->site_url . ( 'index.html' === $file ? '' : $file ),
				'jsonld'  => null,
				'noindex' => false,
			),
			$page
		);

		$this->pages[ $file ] = $this->template( 'layout', array( 'page' => $page ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function software_jsonld() {
		return array(
			'@context'             => 'https://schema.org',
			'@type'                => 'SoftwareApplication',
			'name'                 => $this->meta['name'],
			'description'          => $this->strip( $this->meta['description'] ),
			'applicationCategory'  => 'BusinessApplication',
			'operatingSystem'      => 'WordPress ' . $this->meta['requires_wp'] . '+',
			'softwareRequirements' => 'PHP ' . $this->meta['requires_php'] . '+',
			'softwareVersion'      => $this->meta['version'],
			'dateModified'         => $this->releases ? $this->releases[0]['date'] : '',
			'downloadUrl'          => $this->download_url(),
			'license'              => $this->meta['license_url'],
			'url'                  => $this->site_url,
			'image'                => $this->image ? $this->site_url . $this->image['src'] : '',
			'author'               => array(
				'@type' => 'Person',
				'name'  => $this->meta['author'],
				'url'   => $this->meta['author_url'],
			),
			'offers'               => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'EUR',
			),
		);
	}

	/**
	 * @param string $markdown
	 * @param string $source
	 * @param string $page
	 * @param string $id_prefix
	 * @return string
	 */
	private function markdown( $markdown, $source, $page, $id_prefix = '' ) {
		$this->ids       = array();
		$this->toc       = array();
		$this->id_prefix = $id_prefix;

		$html = (string) $this->converter->convert( $markdown );

		$html = preg_replace(
			'/<h([2-4]) id="([^"]+)">(.*?)<\/h\1>/s',
			'<h$1 id="$2">$3<a class="anchor" href="#$2" aria-label="Link to this section"></a></h$1>',
			$html
		);

		$html = preg_replace_callback(
			'/\s(href|src)="([^"]*)"/',
			function ( $match ) use ( $source, $page ) {
				$url = $this->link( html_entity_decode( $match[2], ENT_QUOTES ), $source, $page );
				return ' ' . $match[1] . '="' . htmlspecialchars( $url, ENT_QUOTES ) . '"';
			},
			$html
		);

		$html = str_replace( array( '<table>', '</table>' ), array( '<div class="table"><table>', '</table></div>' ), $html );
		$html = preg_replace( '/<img (?![^>]*loading=)/', '<img loading="lazy" decoding="async" ', $html );
		return preg_replace( '/\salign="center"/', ' class="center"', $html );
	}

	/**
	 * @param DocumentParsedEvent $event
	 */
	public function on_parsed( DocumentParsedEvent $event ) {
		$walker = $event->getDocument()->walker();

		while ( $step = $walker->next() ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- the walker's documented idiom.
			$node = $step->getNode();

			if ( ! $step->isEntering() || ! $node instanceof Heading ) {
				continue;
			}

			$text = '';
			foreach ( $node->iterator() as $child ) {
				if ( $child instanceof Text || $child instanceof Code ) {
					$text .= $child->getLiteral();
				}
			}

			$slug = $this->id_prefix . $this->slug( $text );
			$id   = $slug;

			if ( isset( $this->ids[ $slug ] ) ) {
				$id = $slug . '-' . $this->ids[ $slug ];
				++$this->ids[ $slug ];
			} else {
				$this->ids[ $slug ] = 1;
			}

			$node->data->set( 'attributes/id', $id );

			if ( $node->getLevel() <= 3 ) {
				$this->toc[] = array(
					'level' => $node->getLevel(),
					'id'    => $id,
					'text'  => $text,
				);
			}
		}
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function slug( $text ) {
		$slug = mb_strtolower( trim( $text ), 'UTF-8' );
		$slug = preg_replace( '/[^\p{L}\p{N}\p{M}\s_-]/u', '', $slug );
		return preg_replace( '/\s/u', '-', $slug );
	}

	/**
	 * @param string $target
	 * @param string $source
	 * @param string $page
	 * @return string
	 */
	private function link( $target, $source, $page ) {
		if ( '' === $target || preg_match( '/^(?:[a-z][a-z0-9+.\-]*:|\/)/i', $target ) ) {
			return $target;
		}

		$fragment = '';
		$hash     = strpos( $target, '#' );

		if ( false !== $hash ) {
			$fragment = substr( $target, $hash + 1 );
			$target   = substr( $target, 0, $hash );
		}

		if ( '' === $target ) {
			$this->links[] = array(
				'page'     => $page,
				'fragment' => $fragment,
				'source'   => $source,
			);
			return '#' . $fragment;
		}

		$path = $this->resolve( dirname( $source ), $target );

		if ( ! file_exists( $this->root . '/' . $path ) ) {
			$this->errors[] = "{$source}: the link to {$target} points at nothing.";
		}

		$result = $this->published( $path );

		if ( null === $result ) {
			$result = $this->github( is_dir( $this->root . '/' . $path ) ? 'tree' : 'blob', $path );
		} elseif ( '.html' === substr( $result, -5 ) ) {
			$this->links[] = array(
				'page'     => $result,
				'fragment' => $fragment,
				'source'   => $source,
			);

			if ( 'index.html' === $result ) {
				$result = './';
			}
		}

		return '' !== $fragment ? $result . '#' . $fragment : $result;
	}

	/**
	 * @param string $path
	 * @return string|null
	 */
	private function published( $path ) {
		if ( 'README.md' === $path ) {
			return 'index.html';
		}

		if ( 'CHANGELOG.md' === $path ) {
			return 'changelog.html';
		}

		foreach ( $this->docs as $doc ) {
			if ( $doc['source'] === $path ) {
				return $doc['slug'] . '.html';
			}
		}

		if ( preg_match( '/^docs\/((?:screenshots\/)?[\w.-]+\.(?:svg|png|jpe?g|webp|gif))$/', $path, $match ) ) {
			return $match[1];
		}

		return null;
	}

	/**
	 * @param string $base
	 * @param string $path
	 * @return string
	 */
	private function resolve( $base, $path ) {
		$parts = array();

		foreach ( explode( '/', ( '.' === $base ? '' : $base . '/' ) . $path ) as $part ) {
			if ( '..' === $part ) {
				array_pop( $parts );
			} elseif ( '' !== $part && '.' !== $part ) {
				$parts[] = $part;
			}
		}

		return implode( '/', $parts );
	}

	/**
	 * @param string $kind
	 * @param string $path
	 * @return string
	 */
	private function github( $kind, $path ) {
		return 'https://github.com/' . $this->repo . '/' . $kind . '/main/' . str_replace( '%2F', '/', rawurlencode( $path ) );
	}

	private function check_links() {
		$ids = array();

		foreach ( $this->pages as $file => $html ) {
			preg_match_all( '/\sid="([^"]+)"/', $html, $match );
			$ids[ $file ] = array_flip( $match[1] );
		}

		foreach ( $this->links as $link ) {
			if ( ! isset( $ids[ $link['page'] ] ) ) {
				$this->errors[] = "{$link['source']}: the link to {$link['page']} leads to no page on the site.";
			} elseif ( '' !== $link['fragment'] && ! isset( $ids[ $link['page'] ][ $link['fragment'] ] ) ) {
				$this->errors[] = "{$link['source']}: the link to #{$link['fragment']} leads to no section of {$link['page']}.";
			}
		}
	}

	private function write_files() {
		$this->mkdir( $this->out . '/assets' );
		$this->mkdir( $this->out . '/screenshots' );

		$copies = array(
			'site/style.css' => 'assets/style.css',
			'site/site.js'   => 'assets/site.js',
			'docs/icon.svg'  => 'icon.svg',
		);

		foreach ( glob( $this->root . '/docs/screenshots/*' ) as $image ) {
			$copies[ 'docs/screenshots/' . basename( $image ) ] = 'screenshots/' . basename( $image );
		}

		$sitemap = '';

		foreach ( $this->pages as $file => $html ) {
			$this->write( $file, $html );

			if ( '404.html' !== $file ) {
				$sitemap .= sprintf(
					"\t<url><loc>%s</loc><lastmod>%s</lastmod></url>\n",
					htmlspecialchars( $this->site_url . ( 'index.html' === $file ? '' : $file ), ENT_XML1 ),
					$this->modified( $this->source_of( $file ) )
				);
			}
		}

		foreach ( $copies as $from => $to ) {
			if ( ! copy( $this->root . '/' . $from, $this->out . '/' . $to ) ) {
				fwrite( STDERR, "Cannot copy {$from}\n" );
				exit( 1 );
			}
		}

		$this->write( 'sitemap.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$sitemap}</urlset>\n" );
		$this->write( 'robots.txt', "User-agent: *\nAllow: /\n\nSitemap: {$this->site_url}sitemap.xml\n" );
	}

	/**
	 * @param string $file
	 * @return string
	 */
	private function source_of( $file ) {
		foreach ( $this->docs as $doc ) {
			if ( $doc['slug'] . '.html' === $file ) {
				return $doc['source'];
			}
		}

		return 'changelog.html' === $file ? 'CHANGELOG.md' : 'README.md';
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function modified( $path ) {
		$date = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $this->root ) . ' log -1 --format=%cs -- ' . escapeshellarg( $path ) . ' 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- a build tool asking Git for a date, with escaped arguments.
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : gmdate( 'Y-m-d' );
	}

	/**
	 * @param string              $name
	 * @param array<string,mixed> $vars
	 * @return string
	 */
	private function template( $name, $vars ) {
		$site = $this;
		$meta = $this->meta;
		$docs = $this->docs;

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables, from this class only.

		ob_start();
		include $this->root . '/site/' . $name . '.php';
		return ob_get_clean();
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function e( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param string $markdown
	 * @param string $source
	 * @return string
	 */
	public function md( $markdown, $source = 'README.md' ) {
		$html = trim( $this->markdown( $markdown, $source, 'index.html' ) );
		return preg_replace( '/^<p>(.*)<\/p>$/s', '$1', $html );
	}

	/**
	 * @param string $file
	 * @return string
	 */
	public function asset( $file ) {
		return 'assets/' . $file . '?v=' . substr( md5_file( $this->root . '/site/' . $file ), 0, 10 );
	}

	/**
	 * @return array{src:string,alt:string,width:int,height:int}|null
	 */
	public function image() {
		return $this->image;
	}

	/**
	 * @return string
	 */
	public function download_url() {
		return 'https://github.com/' . $this->repo . '/releases/latest/download/' . self::ZIP;
	}

	/**
	 * @return string
	 */
	public function repo_url() {
		return 'https://github.com/' . $this->repo;
	}

	/**
	 * @return string
	 */
	public function site_url() {
		return $this->site_url;
	}

	/**
	 * @param string $date
	 * @return string
	 */
	public function date( $date ) {
		$time = strtotime( $date . ' 12:00 UTC' );
		return $time ? gmdate( 'j F Y', $time ) : $date;
	}

	/**
	 * @param string $markdown
	 * @return string
	 */
	private function strip( $markdown ) {
		return trim( preg_replace( '/[*_`]/', '', $markdown ) );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function read( $path ) {
		$text = file_get_contents( $this->root . '/' . $path );

		if ( false === $text ) {
			fwrite( STDERR, "Cannot read {$path}\n" );
			exit( 1 );
		}

		return str_replace( "\r\n", "\n", $text );
	}

	/**
	 * @param string $file
	 * @param string $content
	 */
	private function write( $file, $content ) {
		if ( false === file_put_contents( $this->out . '/' . $file, $content ) ) {
			fwrite( STDERR, "Cannot write {$file}\n" );
			exit( 1 );
		}
	}

	/**
	 * @param string $dir
	 */
	private function mkdir( $dir ) {
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) ) {
			fwrite( STDERR, "Cannot create {$dir}\n" );
			exit( 1 );
		}
	}
}

$wmds_root     = dirname( __DIR__ );
$wmds_autoload = $wmds_root . '/vendor/autoload.php';

if ( ! is_file( $wmds_autoload ) ) {
	fwrite( STDERR, "The site build needs the development tooling: run composer install first.\n" );
	exit( 1 );
}

require $wmds_autoload;

$wmds_out = isset( $argv[1] ) ? $argv[1] : $wmds_root . '/build/site';
$wmds_out = ( '/' === substr( $wmds_out, 0, 1 ) ) ? $wmds_out : getcwd() . '/' . $wmds_out;

$wmds_site = new WMDS_Site_Builder( $wmds_root, $wmds_out );
exit( $wmds_site->run() );
