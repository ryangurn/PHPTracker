<?php

namespace PHPTracker\Bencode;

use PHPTracker\Bencode\Value\AbstractValue;
use PHPTracker\Bencode\Value\Container;
use PHPTracker\Bencode\Value\Dictionary;
use PHPTracker\Bencode\Value\Integer;
use PHPTracker\Bencode\Value\ListValue;
use PHPTracker\Bencode\Value\String;
use PHPTracker\Bencode\Error\ParseError;

/**
 * Parser for Bencode strings.
 *
 * @package PHPTracker
 * @subpackage Bencode
 */
class Parser
{
    private $pointer;
    private $container_stack;
    private $string;

    /**
     * Setting up object.
     *
     * @param string $string String to decode.
     */
    public function __construct( $string )
    {
        $this->string = (string) $string;
    }

    /**
     * Parsing the string attribute of the object.
     *
     * @throws ParseError In case of parse error.
     * @return Abstract Parsed value.
     */
    public function parse()
    {
        $this->pointer = 0;
        $this->container_stack = array();

        $string_length = strlen( $this->string );
        while ( $this->pointer < $string_length )
        {
            if ( isset( $value ) && 0 == count( $this->container_stack ) )
            {
                throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Unstructured values following each other. Use list/dictionary!", $this->pointer );
            }

            switch ( $this->string[$this->pointer] )
            {
                case 'i':
                    $value = $this->parseValueInteger();
                    break;
                case 'l':
                    $value = $this->parseValueList();
                    break;
                case 'd':
                    $value = $this->parseValueDictionary();
                    break;
                case '0':
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                case '8':
                case '8':
                case '9':
                    $value = $this->parseValueString();
                    break;
                case 'e':
                    if ( 0 == count( $this->container_stack ) )
                    {
                        throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Unexpected ending.", $this->pointer );
                    }
                    if ( isset( $possible_key ) )
                    {
                        // If we have a saved possible key, it means that the number of values in
                        // a dictionary is odd, that is, there is no value for a key.
                        throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Incomplete dictionary.", $this->pointer );
                    }

                    // We remove the deepest container from the stack. This might be the final value.
                    $last_container = array_pop( $this->container_stack );
                    $value = null;
                    ++$this->pointer;

                    break;
                default:
                    throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Invalid value.", $this->pointer );
            }

            // We store the current value in the current deepest container (list/dictionary).
            if ( 0 != count( $this->container_stack ) && isset( $value ) )
            {
                $last_container = end( $this->container_stack );

                // With list it's easy: you just throw in the values.
                if ( $last_container instanceof ListValue )
                {
                    $last_container->contain( $value );
                }
                // For the dictionary you have to have a key-value pair.
                elseif ( isset( $possible_key ) )
                {
                    $last_container->contain( $value, $possible_key );
                    unset( $possible_key );
                }
                else
                {
                    // We save the last parsed value as a possible key for a dictionary.
                    $possible_key = $value;
                }
            }

            // If the currently parsed value is a container, we set it as current container.
            if ( $value instanceof Container )
            {
                $this->container_stack[] = $value;
            }
        }

        // At this point we should not have anything in the stack, because we closed all the dictionaries/lists.
        if ( 0 != count( $this->container_stack ) )
        {
            throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Unclosed dictionary/list", $this->pointer );
        }

        // If the whole string is a scalar (int/string), it's OK.
        return isset( $last_container ) ? $last_container : $value;
    }

    /**
     * Parses an integer type at the current cursor position and proceeds the cursor.
     *
     * @throws ParseError In case of parse error.
     * @return Integer
     */
    private function parseValueInteger()
    {
        // This can be FALSE or 0, both are wrong.
        if ( 0 == ( $end_pointer = strpos( $this->string, 'e', $this->pointer ) ) )
        {
            throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Missing ending in integer.", $this->pointer );
        }

        $value = new Integer( substr( $this->string, ( $this->pointer + 1 ), ( $end_pointer - $this->pointer - 1 ) ) );
        $this->pointer = $end_pointer + 1;

        return $value;
    }

    /**
     * Parses a string type at the current cursor position and proceeds the cursor.
     *
     * @throws ParseError In case of parse error.
     * @return String
     */
    private function parseValueString()
    {
        // This can be FALSE or 0, both are wrong.
        if ( 0 == ( $colon_pointer = strpos( $this->string, ':', $this->pointer ) ) )
        {
            throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Missing colon in string.", $this->pointer );
        }

        $length = substr( $this->string, $this->pointer, ( $colon_pointer - $this->pointer ) );
        if ( !( strlen( $length ) < 20 && is_numeric( $length ) && is_int( ( $length + 0 ) ) && $length >= 0 ) )
        {
            throw new ParseError( "Bencode parse error at pointer {$this->pointer}. Invalid length definition in string.", $this->pointer );
        }

        $value = new String( substr( $this->string, ( $colon_pointer + 1 ), $length ) );
        $this->pointer = $colon_pointer + $length + 1;

        return $value;
    }

    /**
     * Parses a list type at the current cursor position and proceeds the cursor.
     *
     * The list is initialized as empty, and will be populated with the upcoming
     * values.
     *
     * @return ListValue
     */
    private function parseValueList()
    {
        ++$this->pointer;
        return new ListValue();
    }

    /**
     * Parses a dictionary type at the current cursor position and proceeds the cursor.
     *
     * The dictionary is initialized as empty, and will be populated with the
     * upcoming values.
     *
     * @return Dictionary
     */
    private function parseValueDictionary()
    {
        ++$this->pointer;
        return new Dictionary();
    }
}
