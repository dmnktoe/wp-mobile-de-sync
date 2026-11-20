<?php
/**
 * Builds the project site from the files the repository already keeps up to
 * date: README.md, docs/*.md, CHANGELOG.md, the plugin header and readme.txt.
 *
 * Usage, after composer install:
 *
 *   php bin/build-site.php [output directory, default build/site]
 *
 * SITE_URL sets the absolute address the site is served from, for canonical
 * links, the sitemap and social previews. GITHUB_REPOSITORY names the
 * repository the links point at. Both default to the public project.
 *
 * The build fails on a link to a page, a section or a file that does not
 * exist, so a renamed heading or a moved document cannot leave a dead link.
 */

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

/**
 * Reads the sources, renders every page and writes the site.
 */
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

	/** @var array<string,int> Heading ids handed out in the current document. */
	private $ids = array();

	/** @var string Prefix for heading ids, where one page holds several documents. */
	private $id_prefix = '';

	/** @var array<int,array{level:int,id:string,text:string}> */
	private $toc = array();

	/** @var array<string,string> Output page => HTML, kept for the link check. */
	private $pages = array();

	/** @var array<int,array{from:string,page:string,fragment:string,source:string}> */
	private $links = array();

	/** @var array<int,string> */
	private $errors = array();

	/** @var array<string,mixed> */
	private $meta = array();

	/** @var array<int,array{slug:string,source:string,title:string,summary:string}> */
	private $docs = array();

	/** @var array<int,array{version:string,date:string,body:string}> */
	private $releases = array();

	/**
	 * @param string $root Repository root.
	 * @param string $out  Output directory.
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
	 * @return int Exit code.
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

		$this->build_home();

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
	 * @return array<string,mixed>
	 */
	private function read_meta() {
		$plugin = $this->read( 'wp-mobile-de-sync.php' );
		$readme = $this->read( 'readme.txt' );

		$header = function ( $text, $name ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':\s*(.+)$/mi', $text, $match ) ) {
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
	 * The documentation pages, in the order and with the summaries of the
	 * table in the README. A file in docs/ the table does not list is still
	 * published, after the others.
	 *
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

			$markdown = $this->read( $source );
			$title    = preg_match( '/^# (.+)$/m', $markdown, $match ) ? trim( $match[1] ) : $row[0];

			$docs[] = array(
				'slug'    => basename( $source, '.md' ),
				'source'  => $source,
				'title'   => '' !== $row[0] ? $row[0] : $title,
				'summary' => $row[1],
			);
		}

		return $docs;
	}


	private function build_home() {
		$readme   = $this->read( 'README.md' );
		$parts    = preg_split( '/^## (.+)$/m', $readme, -1, PREG_SPLIT_DELIM_CAPTURE );
		$intro    = array_shift( $parts );
		$sections = array();

		$tagline = preg_match( '/^\*\*(.+)\*\*\s*$/m', $intro, $match ) ? $match[1] : $this->meta['description'];

		// The intro is centred markup with badges and the screenshot; the prose follows it.
		$lead = trim( substr( $intro, (int) strrpos( $intro, '</div>' ) + 6 ) );
		$lead = trim( preg_replace( '/^<br>\s*/', '', $lead ) );

		$highlights  = array();
		$screenshots = array();

		$total = count( $parts );

		for ( $i = 0; $i < $total; $i += 2 ) {
			$title = trim( $parts[ $i ] );
			$body  = trim( $parts[ $i + 1 ] );

			if ( 'License' === $title ) {
				continue;
			}

			if ( 'Highlights' === $title ) {
				list( $highlights, $screenshots ) = $this->split_highlights( $body );
				continue;
			}

			$sections[] = array(
				'id'    => $this->slug( $title ),
				'title' => $title,
				'kind'  => 'Documentation' === $title ? 'docs' : 'prose',
				'html'  => 'Documentation' === $title ? '' : $this->markdown( $body, 'README.md', 'index.html' ),
			);
		}

		$hero_image = preg_match( '/<img src="([^"]+\.(?:jpe?g|png|webp))" alt="([^"]*)"/', $intro, $match )
			? array(
				'src' => $this->link( $match[1], 'README.md', 'index.html' ),
				'alt' => $match[2],
			)
			: null;

		$body = $this->template(
			'home',
			array(
				'tagline'     => $tagline,
				'lead'        => $this->markdown( $lead, 'README.md', 'index.html' ),
				'hero_image'  => $hero_image,
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
	 * The README's highlights are bold group titles, each followed by a list,
	 * with screenshots in between.
	 *
	 * @param string $markdown
	 * @return array{0:array<int,array{title:string,html:string}>,1:array<int,array{src:string,alt:string}>}
	 */
	private function split_highlights( $markdown ) {
		$screenshots = array();

		if ( preg_match_all( '/<img src="([^"]+)" alt="([^"]*)"[^>]*>/', $markdown, $images, PREG_SET_ORDER ) ) {
			foreach ( $images as $image ) {
				$screenshots[] = array(
					'src' => $this->link( $image[1], 'README.md', 'index.html' ),
					'alt' => $image[2],
				);
			}
		}

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

		return array( $groups, $screenshots );
	}

	/**
	 * The newest releases with the lead of each entry, for the home page.
	 *
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

					$items[] = $this->inline( rtrim( $lead, '.:' ) );
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

		// The title goes into the page header, the back link and the list of
		// sections into the navigation around the page.
		$markdown = preg_replace( '/^# .+\n+/m', '', $markdown, 1 );
		$markdown = preg_replace( '/^\[←[^\]]*\]\([^)]*\)\s*\n+/m', '', $markdown, 1 );
		$markdown = preg_replace( '/^(- \[[^\]]+\]\(#[^)]+\)\n)+\n*/', '', ltrim( $markdown ), 1 );

		$html = $this->markdown( $markdown, $doc['source'], $file );
		$toc  = $this->toc;

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
				'source'      => $doc['source'],
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
				'description' => 'Every release of ' . $this->meta['name'] . ', newest first: what was added, changed and fixed.',
				'active'      => 'changelog',
				'body'        => $body,
				'source'      => 'CHANGELOG.md',
			)
		);
	}

	private function build_not_found() {
		$this->page(
			'404.html',
			array(
				'title'       => 'Page not found — ' . $this->meta['name'],
				'description' => 'This page does not exist.',
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
				'source'  => '',
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
			'softwareVersion'      => $this->meta['version'],
			'dateModified'         => $this->releases ? $this->releases[0]['date'] : null,
			'downloadUrl'          => $this->download_url(),
			'license'              => 'https://www.gnu.org/licenses/gpl-2.0.html',
			'url'                  => $this->site_url,
			'image'                => $this->site_url . 'screenshots/archive.jpg',
			'softwareRequirements' => 'PHP ' . $this->meta['requires_php'] . '+',
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
	 * @param string $source    Repository path the Markdown was read from.
	 * @param string $page      Output page it is rendered into.
	 * @param string $id_prefix Prefix for heading ids, where one page holds several documents.
	 * @return string
	 */
	private function markdown( $markdown, $source, $page, $id_prefix = '' ) {
		$this->ids       = array();
		$this->toc       = array();
		$this->id_prefix = $id_prefix;

		$html = (string) $this->converter->convert( $markdown );

		$html = preg_replace_callback(
			'/<h([2-4]) id="([^"]+)">(.*?)<\/h\1>/s',
			function ( $match ) {
				return sprintf(
					'<h%1$s id="%2$s">%3$s<a class="anchor" href="#%2$s" aria-label="Link to this section"></a></h%1$s>',
					$match[1],
					$match[2],
					$match[3]
				);
			},
			$html
		);

		$html = preg_replace_callback(
			'/\s(href|src)="([^"]*)"/',
			function ( $match ) use ( $source, $page ) {
				return ' ' . $match[1] . '="' . $this->link( html_entity_decode( $match[2], ENT_QUOTES ), $source, $page, true ) . '"';
			},
			$html
		);

		$html = str_replace( array( '<table>', '</table>' ), array( '<div class="table"><table>', '</table></div>' ), $html );
		$html = preg_replace( '/<img (?![^>]*loading=)/', '<img loading="lazy" decoding="async" ', $html );
		$html = preg_replace( '/\salign="center"/', ' class="center"', $html );

		return $html;
	}

	/**
	 * Gives every heading the id GitHub would give it, so a link to a
	 * section works on github.com and on the site alike.
	 *
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
	 * @param string $markdown One line of inline Markdown.
	 * @return string
	 */
	private function inline( $markdown ) {
		$html = trim( (string) $this->converter->convert( $markdown ) );
		return preg_replace( '/^<p>(.*)<\/p>$/s', '$1', $html );
	}

	/**
	 * The anchor GitHub generates for a heading.
	 *
	 * @param string $text
	 * @return string
	 */
	private function slug( $text ) {
		$slug = mb_strtolower( trim( $text ), 'UTF-8' );
		$slug = preg_replace( '/[^\p{L}\p{N}\p{M}\s_-]/u', '', $slug );
		return preg_replace( '/\s/u', '-', $slug );
	}


	/**
	 * Turns a link as written in the repository into one that works on the
	 * site: documents become pages, pictures in docs/ are published beside
	 * them, and everything else points at the file on GitHub.
	 *
	 * @param string $target
	 * @param string $source Repository path of the document the link is in.
	 * @param string $page   Output page the link ends up on.
	 * @param bool   $escape Whether the result goes into an attribute.
	 * @return string
	 */
	private function link( $target, $source, $page, $escape = false ) {
		$result = $target;

		if ( '' === $target || preg_match( '/^(?:[a-z][a-z0-9+.\-]*:|\/\/|\/)/i', $target ) ) {
			return $escape ? htmlspecialchars( $result, ENT_QUOTES ) : $result;
		}

		$fragment = '';
		$hash     = strpos( $target, '#' );

		if ( false !== $hash ) {
			$fragment = substr( $target, $hash + 1 );
			$target   = substr( $target, 0, $hash );
		}

		if ( '' === $target ) {
			$this->links[] = array(
				'from'     => $page,
				'page'     => $page,
				'fragment' => $fragment,
				'source'   => $source,
			);
			return $escape ? htmlspecialchars( '#' . $fragment, ENT_QUOTES ) : '#' . $fragment;
		}

		$path = $this->resolve( dirname( $source ), $target );

		if ( ! file_exists( $this->root . '/' . $path ) ) {
			$this->errors[] = "{$source}: the link to {$target} points at nothing.";
		}

		$published = $this->published( $path );

		if ( null !== $published ) {
			$result = $published;

			if ( '.html' === substr( $published, -5 ) ) {
				$this->links[] = array(
					'from'     => $page,
					'page'     => $published,
					'fragment' => $fragment,
					'source'   => $source,
				);

				if ( 'index.html' === $published ) {
					$result = './';
				}
			}
		} else {
			$result = $this->github( is_dir( $this->root . '/' . $path ) ? 'tree' : 'blob', $path );
		}

		if ( '' !== $fragment ) {
			$result .= '#' . $fragment;
		}

		return $escape ? htmlspecialchars( $result, ENT_QUOTES ) : $result;
	}

	/**
	 * Where a repository file is published on the site, if it is.
	 *
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
	 * @param string $base Directory, relative to the repository root.
	 * @param string $path Relative path.
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
	 * @param string $kind blob, tree or edit.
	 * @param string $path
	 * @return string
	 */
	private function github( $kind, $path ) {
		return 'https://github.com/' . $this->repo . '/' . $kind . '/main/' . str_replace( '%2F', '/', rawurlencode( $path ) );
	}

	/**
	 * @return string
	 */
	public function download_url() {
		return 'https://github.com/' . $this->repo . '/releases/latest/download/' . self::ZIP;
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
		$this->mkdir( $this->out );
		$this->mkdir( $this->out . '/assets' );
		$this->mkdir( $this->out . '/screenshots' );

		foreach ( $this->pages as $file => $html ) {
			file_put_contents( $this->out . '/' . $file, $html );
		}

		copy( $this->root . '/site/style.css', $this->out . '/assets/style.css' );
		copy( $this->root . '/site/site.js', $this->out . '/assets/site.js' );
		copy( $this->root . '/docs/icon.svg', $this->out . '/icon.svg' );

		foreach ( glob( $this->root . '/docs/screenshots/*' ) as $image ) {
			copy( $image, $this->out . '/screenshots/' . basename( $image ) );
		}

		$urls = '';
		foreach ( array_keys( $this->pages ) as $file ) {
			if ( '404.html' === $file ) {
				continue;
			}

			$urls .= sprintf(
				"\t<url><loc>%s</loc><lastmod>%s</lastmod></url>\n",
				htmlspecialchars( $this->site_url . ( 'index.html' === $file ? '' : $file ), ENT_XML1 ),
				$this->modified( $this->source_of( $file ) )
			);
		}

		file_put_contents(
			$this->out . '/sitemap.xml',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}</urlset>\n"
		);
		file_put_contents( $this->out . '/robots.txt', "User-agent: *\nAllow: /\n\nSitemap: {$this->site_url}sitemap.xml\n" );
	}

	/**
	 * @param string $file Output page.
	 * @return string Repository path it was built from.
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
	 * The date of the last commit that touched a file, or today outside a
	 * Git checkout.
	 *
	 * @param string $path
	 * @return string
	 */
	private function modified( $path ) {
		$date = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $this->root ) . ' log -1 --format=%cs -- ' . escapeshellarg( $path ) . ' 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- a build tool asking Git for a date, with escaped arguments.
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : gmdate( 'Y-m-d' );
	}


	/**
	 * @param string              $name Template under site/, without .php.
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
	 * Escapes text for HTML. Used by the templates.
	 *
	 * @param string $text
	 * @return string
	 */
	public function e( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Inline Markdown to HTML. Used by the templates.
	 *
	 * @param string $markdown
	 * @return string
	 */
	public function md( $markdown ) {
		return $this->inline( $markdown );
	}

	/**
	 * A cache-busting address for a file under site/. Used by the templates.
	 *
	 * @param string $file
	 * @return string
	 */
	public function asset( $file ) {
		return 'assets/' . $file . '?v=' . substr( md5_file( $this->root . '/site/' . $file ), 0, 10 );
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
	 * A date as "20 November 2026".
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public function date( $date ) {
		$time = strtotime( $date . ' 12:00 UTC' );
		return $time ? gmdate( 'j F Y', $time ) : $date;
	}

	/**
	 * @param string $markdown
	 * @return string Plain text.
	 */
	private function strip( $markdown ) {
		return trim( preg_replace( '/[*_`]/', '', $markdown ) );
	}

	/**
	 * @param string $path Relative to the repository root.
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
