<?php

namespace BxExG;

class Mapper {

    /**
     * Extract a string into a map of <g>, <bx> and <ex> tag(s) (including nested tags)
     *
     * @param $string
     *
     * @return Element[]
     */
    public static function extract( $string ) {

        $map = [];

        $dom = new \DOMDocument;
        libxml_use_internal_errors( true );

        @$dom->loadHTML( $string );
        $html = $dom->getElementsByTagName( 'body' );

        for ( $i = 0; $i < $html->length; $i++ ) {

            /** @var \DOMElement $node */
            $node = $html->item( $i );

            for ( $k = 0; $k < $node->childNodes->length; $k++ ) {
                $element = self::appendBxExGTagMapElement( $node->childNodes->item( $k ) );
                if ( $element->name ) {
                    $map[ $k ] = $element;
                }
            }
        }

        return $map;
    }

    /**
     * @param DOMNode $node
     *
     * @return Element
     */
    private static function appendBxExGTagMapElement( \DOMNode $node ) {

        $element  = new Element();
        $nodeName = $node->nodeName;

        if ( $nodeName === 'g' or $nodeName === 'ex' or $nodeName === 'bx' ) {
            $element->name = $nodeName;

            foreach ( $node->attributes as $attribute ) {
                $element->attributes[ $attribute->nodeName ] = $attribute->nodeValue;
            }

            $element->children = [];
        }

        if ( $nodeName === 'g' ) {
            for ( $j = 0; $j < $node->childNodes->length; $j++ ) {
                $children = self::appendBxExGTagMapElement( $node->childNodes->item( $j ) );

                if ( $children->name ) {
                    $element->children[] = $children;
                }
            }
        }

        return $element;
    }
}