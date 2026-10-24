<?php
/**
 * Small, dependency-free HTML to Markdown converter for post content.
 *
 * Handles headings, paragraphs, emphasis, links, images, lists (nested),
 * blockquotes, code, tables, <details>/<summary> and horizontal rules.
 * Scripts, styles, forms and other non-content elements are dropped.
 *
 * @package AEO
 */

namespace AEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Converter.
 */
class Html_To_Markdown {

	const SKIP = array( 'script', 'style', 'noscript', 'iframe', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'canvas', 'template', 'nav', 'object', 'embed' );

	/**
	 * Convert an HTML fragment.
	 *
	 * @param string $html HTML.
	 * @return string Markdown.
	 */
	public static function convert( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}

		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8"><div id="aeo-md-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $doc->getElementById( 'aeo-md-root' );
		if ( ! $root ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$md = self::children( $root, 0 );
		$md = preg_replace( "/[ \t]+\n/", "\n", $md );
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );
		return trim( $md ) . "\n";
	}

	/**
	 * Convert all child nodes.
	 *
	 * @param \DOMNode $node  Node.
	 * @param int      $depth List depth.
	 * @return string
	 */
	private static function children( \DOMNode $node, $depth ) {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= self::node( $child, $depth );
		}
		return $out;
	}

	/**
	 * Inline text of a node, collapsed to one line.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function inline( \DOMNode $node ) {
		return trim( preg_replace( '/\s+/u', ' ', self::children( $node, 0 ) ) );
	}

	/**
	 * Convert one node.
	 *
	 * @param \DOMNode $node  Node.
	 * @param int      $depth List depth.
	 * @return string
	 */
	private static function node( \DOMNode $node, $depth ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			$text = preg_replace( '/\s+/u', ' ', $node->textContent );
			return self::escape( $text );
		}
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return '';
		}

		/** @var \DOMElement $node */
		$tag = strtolower( $node->nodeName );
		if ( in_array( $tag, self::SKIP, true ) ) {
			return '';
		}
		if ( $node->hasAttribute( 'aria-hidden' ) && 'true' === $node->getAttribute( 'aria-hidden' ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = self::inline( $node );
				return '' === $text ? '' : "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . $text . "\n\n";

			case 'p':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : "\n\n" . $text . "\n\n";

			case 'br':
				return "  \n";

			case 'hr':
				return "\n\n---\n\n";

			case 'strong':
			case 'b':
				$text = self::inline( $node );
				return '' === $text ? '' : '**' . $text . '**';

			case 'em':
			case 'i':
				$text = self::inline( $node );
				return '' === $text ? '' : '*' . $text . '*';

			case 'code':
				if ( $node->parentNode && 'pre' === strtolower( $node->parentNode->nodeName ) ) {
					return $node->textContent;
				}
				return '`' . str_replace( '`', '\\`', $node->textContent ) . '`';

			case 'pre':
				return "\n\n```\n" . rtrim( $node->textContent ) . "\n```\n\n";

			case 'a':
				$text = self::inline( $node );
				$href = trim( $node->getAttribute( 'href' ) );
				if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === stripos( $href, 'javascript:' ) ) {
					return $text;
				}
				return '' === $text ? '' : '[' . $text . '](' . esc_url_raw( $href ) . ')';

			case 'img':
				$src = $node->getAttribute( 'src' );
				if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
					return '';
				}
				return '![' . self::escape( $node->getAttribute( 'alt' ) ) . '](' . esc_url_raw( $src ) . ')';

			case 'figure':
				return "\n\n" . trim( self::children( $node, $depth ) ) . "\n\n";

			case 'figcaption':
				$text = self::inline( $node );
				return '' === $text ? '' : "\n*" . $text . "*\n";

			case 'blockquote':
				$inner = trim( self::children( $node, $depth ) );
				return "\n\n" . preg_replace( '/^/m', '> ', $inner ) . "\n\n";

			case 'ul':
			case 'ol':
				return self::list_items( $node, $depth, 'ol' === $tag );

			case 'table':
				return self::table( $node );

			case 'details':
				$out = '';
				foreach ( $node->childNodes as $child ) {
					if ( XML_ELEMENT_NODE === $child->nodeType && 'summary' === strtolower( $child->nodeName ) ) {
						$out .= "\n\n### " . self::inline( $child ) . "\n\n";
					} else {
						$out .= self::node( $child, $depth );
					}
				}
				return $out;

			case 'summary':
				return "\n\n### " . self::inline( $node ) . "\n\n";

			case 'section':
			case 'article':
			case 'div':
			case 'main':
			case 'header':
			case 'footer':
			case 'aside':
				return "\n" . self::children( $node, $depth ) . "\n";

			default:
				return self::children( $node, $depth );
		}
	}

	/**
	 * Lists, with nesting.
	 *
	 * @param \DOMElement $list    List element.
	 * @param int         $depth   Depth.
	 * @param bool        $ordered Ordered list.
	 * @return string
	 */
	private static function list_items( \DOMElement $list, $depth, $ordered ) {
		$out    = "\n";
		$number = 1;
		$indent = str_repeat( '  ', $depth );

		foreach ( $list->childNodes as $item ) {
			if ( XML_ELEMENT_NODE !== $item->nodeType || 'li' !== strtolower( $item->nodeName ) ) {
				continue;
			}
			$text   = '';
			$nested = '';
			foreach ( $item->childNodes as $child ) {
				$name = XML_ELEMENT_NODE === $child->nodeType ? strtolower( $child->nodeName ) : '';
				if ( 'ul' === $name || 'ol' === $name ) {
					$nested .= self::list_items( $child, $depth + 1, 'ol' === $name );
				} else {
					$text .= self::node( $child, $depth + 1 );
				}
			}
			$marker = $ordered ? ( $number++ ) . '.' : '-';
			$out   .= $indent . $marker . ' ' . trim( preg_replace( '/\s*\n\s*/', ' ', $text ) ) . "\n" . $nested;
		}

		return 0 === $depth ? "\n" . $out . "\n" : $out;
	}

	/**
	 * Tables (GitHub-flavoured).
	 *
	 * @param \DOMElement $table Table.
	 * @return string
	 */
	private static function table( \DOMElement $table ) {
		$rows = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( XML_ELEMENT_NODE === $cell->nodeType && in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
					$cells[] = str_replace( '|', '\\|', self::inline( $cell ) );
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}
		if ( ! $rows ) {
			return '';
		}

		$cols = max( array_map( 'count', $rows ) );
		$out  = "\n\n";
		foreach ( $rows as $i => $cells ) {
			$cells = array_pad( $cells, $cols, '' );
			$out  .= '| ' . implode( ' | ', $cells ) . " |\n";
			if ( 0 === $i ) {
				$out .= '|' . str_repeat( ' --- |', $cols ) . "\n";
			}
		}
		return $out . "\n";
	}

	/**
	 * Escape characters that would start Markdown syntax.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape( $text ) {
		return preg_replace( '/([\\\\`*_\[\]])/', '\\\\$1', $text );
	}
}
