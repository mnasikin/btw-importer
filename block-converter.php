<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class btw_importer_Block_Converter {
    private static array $emitted = [];

    private static function inner_html( DOMNode $node ): string {
        $out = '';
        foreach ( $node->childNodes as $child ) {
            $out .= $child->ownerDocument->saveHTML( $child );
        }
        return $out;
    }

    private static function clean_inline( string $raw ): string {
        $prev = null;
        while ( $prev !== $raw ) {
            $prev = $raw;
            $raw = preg_replace_callback(
                '#<span([^>]*)>(.*?)</span>#si',
                function ( array $m ): string {
                    $attrs = $m[1];
                    $inner = $m[2];

                    if ( preg_match( '/style=["\']([^"\']*)["\']/', $attrs, $sm ) ) {
                        $style = preg_replace( '/(^|;)\s*(font-family|font-size|line-height|mso-[^:]+)\s*:[^;]*/i', '', $sm[1] );
                        $style = trim( preg_replace( '/^[\s,;]+/', '', $style ), " \t\n\r;," );

                        if ( $style === '' ) {
                            $other = trim( preg_replace( '/\s*style=["\'][^"\']*["\']/', '', $attrs ) );
                            return $other === '' ? $inner : '<span ' . $other . '>' . $inner . '</span>';
                        }

                        $other = preg_replace( '/\s*style=["\'][^"\']*["\']/', '', $attrs );
                        return '<span' . $other . ' style="' . esc_attr( $style ) . '">' . $inner . '</span>';
                    }

                    return trim( $attrs ) === '' ? $inner : $m[0];
                },
                $raw
            );
        }

        $raw = preg_replace( '/<(div|p|ul|ol|h[1-6])\b([^>]*?)\s+style=["\'][^"\']*["\']([^>]*)>/i', '<$1$2$3>', $raw );
        $raw = preg_replace( '#<p>\s*</p>#i', '', $raw );
        $raw = preg_replace( '#^(\s*<br\s*/?>\s*)+#i', '', $raw );
        $raw = preg_replace( '#(\s*<br\s*/?>\s*)+$#i', '', $raw );

        return trim( $raw );
    }

    private static function make_paragraph( string $inner ): string {
        $inner = self::clean_inline( $inner );

        if ( $inner === '' ) {
            return '';
        }

        if ( trim( wp_strip_all_tags( $inner ) ) === '' && strpos( $inner, '<img' ) === false ) {
            return '';
        }

        return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->\n";
    }

    private static function make_heading( int $level, string $inner ): string {
        $inner = self::clean_inline( $inner );

        if ( $inner === '' ) {
            return '';
        }

        return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">{$inner}</h{$level}>\n<!-- /wp:heading -->\n";
    }

    private static function make_list( DOMNode $list ): string {
        $items = '';

        foreach ( $list->childNodes as $li ) {
            if ( strtolower( $li->nodeName ) !== 'li' ) {
                continue;
            }

            $inner = self::clean_inline( self::inner_html( $li ) );
            if ( $inner !== '' ) {
                $items .= "<!-- wp:list-item -->\n<li>{$inner}</li>\n<!-- /wp:list-item -->\n";
            }
        }

        if ( $items === '' ) {
            return '';
        }

        $tag  = strtolower( $list->nodeName ) === 'ol' ? 'ol' : 'ul';
        $attr = $tag === 'ol' ? ' {"ordered":true}' : '';

        return "<!-- wp:list{$attr} -->\n<{$tag} class=\"wp-block-list\">\n{$items}</{$tag}>\n<!-- /wp:list -->\n";
    }

    private static function make_image_from_img( DOMNode $img ): string {
        if ( ! ( $img instanceof DOMElement ) ) {
            return '';
        }

        $src = $img->getAttribute( 'src' );
        if ( $src === '' ) {
            return '';
        }

        $alt = $img->getAttribute( 'alt' );
        $w   = $img->getAttribute( 'data-original-width' );
        $h   = $img->getAttribute( 'data-original-height' );
        $dim = ( $w ? ' width="' . esc_attr( $w ) . '"' : '' ) . ( $h ? ' height="' . esc_attr( $h ) . '"' : '' );

        return '<!-- wp:image -->' . "\n" .
            '<figure class="wp-block-image"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"' . $dim . '/></figure>' . "\n" .
            '<!-- /wp:image -->' . "\n";
    }

    private static function make_image_from_table( DOMNode $table ): string {
        $xpath = new DOMXPath( $table->ownerDocument );
        $imgs  = $xpath->query( './/img', $table );
        $caps  = $xpath->query( './/*[contains(@class,"tr-caption")]', $table );

        if ( $imgs->length === 0 ) {
            return '';
        }

        $image = self::make_image_from_img( $imgs->item( 0 ) );
        if ( $image === '' || $caps->length === 0 ) {
            return $image;
        }

        $caption = trim( wp_strip_all_tags( $caps->item( 0 )->textContent ) );
        if ( $caption === '' ) {
            return $image;
        }

        return preg_replace(
            '#</figure>#',
            '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption></figure>',
            $image,
            1
        );
    }

    private static function is_block_node( DOMNode $node ): bool {
        if ( ! ( $node instanceof DOMElement ) ) {
            return false;
        }

        return in_array(
            strtolower( $node->nodeName ),
            [ 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'blockquote', 'pre', 'figure', 'hr', 'section', 'article', 'header', 'footer', 'main' ],
            true
        );
    }

    private static function is_empty_node( DOMNode $node ): bool {
        if ( $node->nodeName === '#text' ) {
            return trim( $node->nodeValue ) === '';
        }

        if ( strtolower( $node->nodeName ) === 'br' ) {
            return true;
        }

        if ( ! ( $node instanceof DOMElement ) ) {
            return false;
        }

        return trim( $node->textContent ) === ''
            && ( new DOMXPath( $node->ownerDocument ) )->query( './/img', $node )->length === 0;
    }

    private static function has_images( DOMNode $node ): bool {
        if ( strtolower( $node->nodeName ) === 'img' ) {
            return true;
        }

        if ( ! ( $node instanceof DOMElement ) ) {
            return false;
        }

        return ( new DOMXPath( $node->ownerDocument ) )->query( './/img', $node )->length > 0;
    }

    private static function has_block_descendants( DOMNode $node ): bool {
        if ( ! ( $node instanceof DOMElement ) ) {
            return false;
        }

        $xpath = new DOMXPath( $node->ownerDocument );
        return $xpath->query( './/div|.//p|.//h1|.//h2|.//h3|.//h4|.//h5|.//h6|.//ul|.//ol|.//table|.//blockquote|.//pre|.//hr', $node )->length > 0;
    }

    private static function flush_inline( string &$buffer ): string {
        $out    = self::make_paragraph( $buffer );
        $buffer = '';
        return $out;
    }

    private static function mark_emitted( DOMNode $node ): bool {
        $id = spl_object_id( $node );

        if ( isset( self::$emitted[ $id ] ) ) {
            return false;
        }

        self::$emitted[ $id ] = true;
        return true;
    }

    private static function convert_children( DOMNodeList $children ): string {
        $out    = '';
        $buffer = '';
        $nodes  = [];

        foreach ( $children as $child ) {
            $nodes[] = $child;
        }

        foreach ( $nodes as $node ) {
            $name  = strtolower( $node->nodeName );
            $class = ( $node instanceof DOMElement ) ? $node->getAttribute( 'class' ) : '';

            if ( $node->nodeName === '#comment' ) {
                if ( trim( $node->nodeValue ) === 'more' ) {
                    $out .= self::flush_inline( $buffer );
                    $out .= "<!-- wp:more -->\n<!--more-->\n<!-- /wp:more -->\n";
                }
                continue;
            }

            if ( self::is_empty_node( $node ) ) {
                continue;
            }

            if ( preg_match( '/^h([1-6])$/', $name, $matches ) ) {
                $out .= self::flush_inline( $buffer );
                $out .= self::make_heading( (int) $matches[1], self::inner_html( $node ) );
                continue;
            }

            if ( $name === 'ul' || $name === 'ol' ) {
                $out .= self::flush_inline( $buffer );
                $out .= self::make_list( $node );
                continue;
            }

            if ( $name === 'table' && strpos( $class, 'tr-caption-container' ) !== false ) {
                if ( self::mark_emitted( $node ) ) {
                    $out .= self::flush_inline( $buffer );
                    $out .= self::make_image_from_table( $node );
                }
                continue;
            }

            if ( $name === 'img' ) {
                if ( self::mark_emitted( $node ) ) {
                    $out .= self::flush_inline( $buffer );
                    $out .= self::make_image_from_img( $node );
                }
                continue;
            }

            if ( $name === 'br' ) {
                if ( $buffer !== '' ) {
                    $buffer .= ' ';
                }
                continue;
            }

            if ( $name === 'div' ) {
                if ( strpos( $class, 'separator' ) !== false ) {
                    $xpath  = new DOMXPath( $node->ownerDocument );
                    $tables = $xpath->query( './/table[contains(@class,"tr-caption-container")]', $node );

                    if ( $tables->length > 0 ) {
                        $out .= self::flush_inline( $buffer );
                        foreach ( $tables as $table ) {
                            if ( self::mark_emitted( $table ) ) {
                                $out .= self::make_image_from_table( $table );
                            }
                        }
                        continue;
                    }

                    $imgs = $xpath->query( './/img', $node );
                    if ( $imgs->length > 0 ) {
                        $out .= self::flush_inline( $buffer );
                        foreach ( $imgs as $img ) {
                            if ( self::mark_emitted( $img ) ) {
                                $out .= self::make_image_from_img( $img );
                            }
                        }
                        continue;
                    }

                    continue;
                }

                $out .= self::flush_inline( $buffer );
                $out .= self::convert_children( $node->childNodes );
                continue;
            }

            if ( $name === 'p' ) {
                $has_block = false;
                foreach ( $node->childNodes as $child ) {
                    if ( self::is_block_node( $child ) ) {
                        $has_block = true;
                        break;
                    }
                }

                if ( $has_block || self::has_block_descendants( $node ) ) {
                    $out .= self::flush_inline( $buffer );
                    $out .= self::convert_children( $node->childNodes );
                    continue;
                }

                $inner = trim( self::inner_html( $node ) );
                if ( $inner !== '' ) {
                    $out .= self::flush_inline( $buffer );
                    $out .= self::make_paragraph( $inner );
                }
                continue;
            }

            if ( $node->nodeName === '#text' ) {
                $value = preg_replace( '/\s+/', ' ', $node->nodeValue );
                if ( $value !== ' ' || $buffer !== '' ) {
                    $buffer .= $value;
                }
                continue;
            }

            if ( self::has_block_descendants( $node ) ) {
                $out .= self::flush_inline( $buffer );
                $out .= self::convert_children( $node->childNodes );
                continue;
            }

            if ( self::has_images( $node ) ) {
                $out  .= self::flush_inline( $buffer );
                $xpath = new DOMXPath( $node->ownerDocument );
                $imgs  = $xpath->query( './/img', $node );

                foreach ( $imgs as $img ) {
                    if ( self::mark_emitted( $img ) ) {
                        $out .= self::make_image_from_img( $img );
                    }
                }

                $clone = $node->cloneNode( true );
                self::remove_images( $clone );

                if ( trim( preg_replace( '/\s+/', ' ', $clone->textContent ) ) !== '' ) {
                    $buffer .= $node->ownerDocument->saveHTML( $clone );
                }
                continue;
            }

            $buffer .= $node->ownerDocument->saveHTML( $node );
        }

        $out .= self::flush_inline( $buffer );
        return $out;
    }

    private static function remove_images( DOMNode $node ): void {
        $children = [];
        foreach ( $node->childNodes as $child ) {
            $children[] = $child;
        }

        foreach ( $children as $child ) {
            if ( strtolower( $child->nodeName ) === 'img' ) {
                $node->removeChild( $child );
            } elseif ( $child instanceof DOMElement ) {
                self::remove_images( $child );
            }
        }
    }

    public static function convert( string $html ): string {
        if ( trim( $html ) === '' || has_blocks( $html ) ) {
            return $html;
        }

        self::$emitted = [];

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $previous = libxml_use_internal_errors( true );
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__btw_importer_root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        $xpath = new DOMXPath( $dom );
        $root  = $xpath->query( '//div[@id="__btw_importer_root__"]' )->item( 0 );

        if ( ! $root ) {
            return $html;
        }

        $blocks = self::convert_children( $root->childNodes );
        $blocks = preg_replace( '#<!-- wp:paragraph -->\s*<p>\s*</p>\s*<!-- /wp:paragraph -->\n?#', '', $blocks );
        $blocks = preg_replace( "/\n{3,}/", "\n\n", $blocks );

        return trim( $blocks );
    }
}
