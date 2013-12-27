<?php
/**
 * File containing the UrlAlias Handler implementation
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\InMemory;

use eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler as UrlAliasHandlerInterface;
use eZ\Publish\SPI\Persistence\Content\UrlAlias;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\ForbiddenException;

/**
 * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler
 */
class UrlAliasHandler implements UrlAliasHandlerInterface
{
    /**
     * @var Handler
     */
    protected $handler;

    /**
     * @var Backend
     */
    protected $backend;

    /**
     * Setups current handler instance with reference to Handler object that created it.
     *
     * @param Handler $handler
     * @param Backend $backend The storage engine backend
     */
    public function __construct( Handler $handler, Backend $backend )
    {
        $this->handler = $handler;
        $this->backend = $backend;
    }

    /**
     * This method creates or updates an urlalias from a new or changed content name in a language
     * (if published). It also can be used to create an alias for a new location of content.
     * On update the old alias is linked to the new one (i.e. a history alias is generated).
     *
     * $alwaysAvailable controls whether the url alias is accessible in all
     * languages.
     *
     * @param mixed $locationId
     * @param mixed $parentLocationId In case of empty( $parentLocationId ), threat as root
     * @param string $name the new name computed by the name schema or url alias schema
     * @param string $languageCode
     * @param boolean $alwaysAvailable
     *
     * @return void Does not return the UrlAlias created / updated with type UrlAlias::LOCATION
     */
    public function publishUrlAliasForLocation( $locationId, $parentLocationId, $name, $languageCode, $alwaysAvailable = false )
    {
        // Get parent url alias
        $parentLocationAlias = $this->loadAutogeneratedAlias( $parentLocationId );
        if ( !isset( $parentLocationAlias ) )
        {
            throw new \RuntimeException( "Did not find any url alias for location:  {$parentLocationId}" );
        }
        $parentId = $parentLocationAlias->id["link"];
        $pathData = $parentLocationAlias->pathData;
        $data = array(
            'parent' => $parentLocationAlias->id["link"],
            'type' => UrlAlias::LOCATION,
            'destination' => $locationId,
            'pathData' => $pathData,
            'languageCodes' => array( $languageCode ),
            'alwaysAvailable' => $alwaysAvailable,
            'isHistory' => false,
            'isCustom' => false,
            'forward' => false,
        );

        $uniqueCounter = 1;
        // Exiting the loop with break;
        while ( true )
        {
            $newText = $name . ( $uniqueCounter > 1 ? $uniqueCounter : "" );
            // Try to load possibly reusable alias
            $reusableAlias = $this->loadAlias( $parentId, $newText );

            if ( !isset( $reusableAlias ) )
            {
                // Check for existing active location entry on this level and reuse it
                $existingAlias = $this->loadAutogeneratedAlias( $locationId, $parentId );
                if ( isset( $existingAlias ) )
                {
                    $this->historizeAndUpdateExistingAlias( $data, $existingAlias, $languageCode, $newText, $alwaysAvailable );
                }
                else
                {
                    $this->createNewAlias( $this->getNextLinkId(), $data, $languageCode, $newText, $alwaysAvailable );
                }

                break;
            }

            // Possibly reusable alias exists, check if it is reusable
            if ( $reusableAlias->type == UrlAlias::VIRTUAL
                || ( $reusableAlias->type == UrlAlias::LOCATION && $reusableAlias->destination == $locationId )
                || $reusableAlias->isHistory )
            {
                // Check for existing active location entry on this level and reuse it
                $existingAlias = $this->loadAutogeneratedAlias( $locationId, $parentId );
                if ( isset( $existingAlias ) )
                {
                    $this->historizeAndUpdateExistingAlias( $data, $existingAlias, $languageCode, $newText, $alwaysAvailable );

                    if ( $existingAlias->id["link"] != $reusableAlias->id["link"] )
                    {
                        $this->downgrade( $reusableAlias, $languageCode );
                    }
                }
                else
                {
                    $this->downgrade( $reusableAlias, $languageCode );
                    $this->createNewAlias( $reusableAlias->id["link"], $data, $languageCode, $newText, $alwaysAvailable );
                }

                break;
            }

            // If existing row is not reusable, increment $uniqueCounter and try again
            $uniqueCounter += 1;
        }
    }

    /**
     * Creates new alias.
     *
     * @param mixed $linkId
     * @param array $data
     * @param string $languageCode
     * @param string $name
     * @param boolean $alwaysAvailable
     *
     * @return void
     */
    public function createNewAlias( $linkId, $data, $languageCode, $name, $alwaysAvailable )
    {
        $data["link"] = $linkId;
        $data["pathData"][] = array(
            'always-available' => $alwaysAvailable,
            'translations' => array(
                $languageCode => $name
            )
        );
        $this->backend->create( 'Content\\UrlAlias', $data );
    }

    /**
     * Updates existing location alias and creates history if necessary.
     *
     * @param array $data
     * @param \eZ\Publish\SPI\Persistence\Content\UrlAlias $existingAlias
     * @param string $languageCode
     * @param string $name
     * @param boolean $alwaysAvailable
     *
     * @return void
     */
    protected function historizeAndUpdateExistingAlias( $data, $existingAlias, $languageCode, $name, $alwaysAvailable )
    {
        $existingTranslation = $this->getTranslation( $existingAlias, $languageCode );
        // Do not historize letter case changes
        if ( isset( $existingTranslation ) && strcasecmp( $name, $existingTranslation ) !== 0 )
        {
            $this->historize( $existingAlias, $languageCode );
        }

        $pathData = $existingAlias->pathData;
        $lastPathElementData = end( $pathData );
        $lastPathElementData["always-available"] = $alwaysAvailable;
        $lastPathElementData["translations"][$languageCode] = $name;
        $data["pathData"][] = $lastPathElementData;
        if ( !in_array( $languageCode, $data["languageCodes"] ) )
        {
            $data["languageCodes"][] = $languageCode;
        }
        $data["alwaysAvailable"] = $alwaysAvailable;

        $this->backend->update( 'Content\\UrlAlias', $existingAlias->id["id"], $data );
    }

    /**
     * Returns translation in given $languageCode or null if it does not exist.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\UrlAlias $urlAlias
     * @param string $languageCode
     *
     * @return string|null
     */
    protected function getTranslation( $urlAlias, $languageCode )
    {
        $lastIndex = count( $urlAlias->pathData ) - 1;
        if ( isset( $urlAlias->pathData[$lastIndex]["translations"][$languageCode] ) )
        {
            return $urlAlias->pathData[$lastIndex]["translations"][$languageCode];
        }

        return null;
    }

    /**
     * Remove translation in given $languageCode from alias or delete alias if given translation is the last one.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\UrlAlias $urlAlias
     * @param string $languageCode
     *
     * @return void
     */
    protected function downgrade( $urlAlias, $languageCode )
    {
        $urlAlias = clone $urlAlias;
        $lastIndex = count( $urlAlias->pathData ) - 1;
        unset( $urlAlias->pathData[$lastIndex]["translations"][$languageCode] );

        if ( empty( $urlAlias->pathData[$lastIndex]["translations"] ) )
        {
            $urlAlias->languageCodes = array_diff( $urlAlias->languageCodes, array( $languageCode ) );
            $this->backend->update(
                'Content\\UrlAlias',
                $urlAlias->id["id"],
                array(
                    'pathData' => $urlAlias->pathData,
                    'languageCodes' => $urlAlias->languageCodes
                )
            );
        }
        else
        {
            $this->backend->delete( 'Content\\UrlAlias', $urlAlias->id );
        }
    }

    /**
     * Creates history for one translation.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\UrlAlias $urlAlias
     * @param string $languageCode
     *
     * @return void
     */
    protected function historize( $urlAlias, $languageCode )
    {
        $data = (array)$urlAlias;
        $lastIndex = count( $data["pathData"] ) - 1;
        $data["pathData"][$lastIndex]["translations"] = array(
            $languageCode => $data["pathData"][$lastIndex]["translations"][$languageCode]
        );
        $data["languageCodes"] = array( $languageCode );
        $data["isHistory"] = true;

        $this->backend->update( 'Content\\UrlAlias', $urlAlias->id["id"], $data );
    }

    /**
     * Create a user chosen $alias pointing to $locationId in $languageCode.
     *
     * If $languageCode is null the $alias is created in the system's default
     * language. $alwaysAvailable makes the alias available in all languages.
     *
     * @param mixed $locationId
     * @param string $path
     * @param boolean $forwarding
     * @param string $languageCode
     * @param boolean $alwaysAvailable
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias
     */
    public function createCustomUrlAlias( $locationId, $path, $forwarding = false, $languageCode = null, $alwaysAvailable = false )
    {
        return $this->createUrlAlias(
            "eznode:" . $locationId,
            $path,
            $forwarding,
            $languageCode,
            $alwaysAvailable
        );
    }

    /**
     * Create a user chosen $alias pointing to a resource in $languageCode.
     * This method does not handle location resources - if a user enters a location target
     * the createCustomUrlAlias method has to be used.
     *
     * If $languageCode is null the $alias is created in the system's default
     * language. $alwaysAvailable makes the alias available in all languages.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException if the path already exists for the given language
     *
     * @param string $resource
     * @param string $path
     * @param boolean $forwarding
     * @param string $languageCode
     * @param boolean $alwaysAvailable
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias
     */
    public function createGlobalUrlAlias( $resource, $path, $forwarding = false, $languageCode = null, $alwaysAvailable = false )
    {
        return $this->createUrlAlias(
            $resource,
            $path,
            $forwarding,
            $languageCode,
            $alwaysAvailable
        );
    }

    /**
     * Internal method for creating global or custom URL alias (these are handled in the same way)
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\ForbiddenException if the path already exists for the given language
     *
     * @param string $resource
     * @param string $path
     * @param boolean $forwarding
     * @param string|null $languageCode
     * @param boolean $alwaysAvailable
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias With $type = UrlAlias::RESOURCE
     */
    protected function createUrlAlias( $resource, $path, $forwarding = false, $languageCode = null, $alwaysAvailable = false )
    {
        if ( $languageCode === null )
            $languageCode = 'eng-GB';// @todo Reuse settings used in Service layer here

        $pathArray = explode( '/', $path );
        $pathCount = count( $pathArray );
        $pathData = array();
        $parentId = 0;
        foreach ( $pathArray as $index => $pathItem )
        {
            if ( $index + 1 !== $pathCount )
            {
                $existingAlias = $this->loadAlias( $parentId, $pathItem );
                if ( !isset( $existingAlias ) )
                {
                    $pathData[] = array(
                        'always-available' => true,
                        'translations' => array( 'always-available' => $pathItem )
                    );
                    $virtualAlias = $this->backend->create(
                        'Content\\UrlAlias',
                        array(
                            'parent' => $parentId,
                            'link' => $this->getNextLinkId(),
                            'type' => UrlAlias::VIRTUAL,
                            'destination' => null,
                            'pathData' => $pathData,
                            'languageCodes' => array(),
                            'alwaysAvailable' => true,
                            'isHistory' => false,
                            'isCustom' => true,
                            'forward' => true,
                        )
                    );
                    $parentId = $virtualAlias->id["link"];
                }
                else
                {
                    $parentId = $existingAlias->id["link"];
                    $pathData = $existingAlias->pathData;
                }
            }
            else
            {
                $pathData[] = array(
                    'always-available' => $alwaysAvailable,
                    'translations' => array( $languageCode => $pathItem )
                );
            }
        }

        preg_match( "#^([a-zA-Z0-9_]+):(.+)?$#", $resource, $matches );
        $data = array(
            'parent' => $parentId,
            'link' => $this->getNextLinkId(),
            'type' => $matches[1] === "eznode" ? UrlAlias::LOCATION : UrlAlias::RESOURCE,
            'destination' => isset( $matches[2] ) ? $matches[2] : false,
            'pathData' => $pathData,
            'languageCodes' => array( $languageCode ),
            'alwaysAvailable' => $alwaysAvailable,
            'isHistory' => false,
            'isCustom' => true,
            'forward' => $forwarding,
        );
        $reusableAlias = $this->loadAlias( $parentId, $pathItem );

        if ( !isset( $reusableAlias ) )
        {
            $alias = $this->backend->create( 'Content\\UrlAlias', $data );
        }
        else if ( $reusableAlias->type == UrlAlias::VIRTUAL || $reusableAlias->isHistory )
        {
            $this->downgrade( $reusableAlias, $languageCode );
            $alias = $this->backend->create( 'Content\\UrlAlias', $data );
        }
        else
        {
            throw new ForbiddenException( "Path '$path' already exists for the given language" );
        }

        $alias->id = $alias->id["parent"] . "-" . $this->getHash( $pathItem );

        return $alias;
    }

    /**
     * Loads alias by given $parentId and $text.
     *
     * @param mixed $parentId
     * @param string $text
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias|null
     */
    protected function loadAlias( $parentId, $text )
    {
        $list = $this->backend->find( 'Content\\UrlAlias', array( 'parent' => $parentId ) );

        $filteredList = array();
        foreach ( $list as $alias )
        {
            $pathData = end( $alias->pathData );
            foreach ( $pathData["translations"] as $translation )
            {
                if ( strcasecmp( $text, $translation ) === 0 )
                {
                    $filteredList[] = $alias;
                    break;
                }
            }
        }

        if ( isset( $filteredList[1] ) )
        {
            throw new \RuntimeException(
                "Found more then 1 url alias for the parent '{$parentId}' and text '{$text}'"
            );
        }

        return isset( $filteredList[0] ) ? $filteredList[0] : null;
    }

    /**
     * List global aliases.
     *
     * @param string|null $languageCode
     * @param int $offset
     * @param int $limit
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias[]
     */
    public function listGlobalURLAliases( $languageCode = null, $offset = 0, $limit = -1 )
    {
        $filter = array(
            'type' => UrlAlias::RESOURCE,
            'isHistory' => false,
            'isCustom' => true
        );

        if ( $languageCode !== null )
            $filter['languageCodes'] = $languageCode;

        $list = $this->backend->find(
            'Content\\UrlAlias',
            $filter
        );

        if ( !empty( $list ) && !( $offset === 0 && $limit === -1 ) )
        {
            $list = array_slice( $list, $offset, ( $limit === -1 ? null : $limit ) );
        }

        foreach ( $list as &$alias )
        {
            $pathData = end( $alias->pathData );
            $alias->id = $alias->id["parent"] . "-" . $this->getHash( reset( $pathData["translations"] ) );
        }

        return $list;
    }

    /**
     * List of url entries of $urlType, pointing to $locationId.
     *
     * @param mixed $locationId
     * @param boolean $custom if true the user generated aliases are listed otherwise the autogenerated
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias[]
     */
    public function listURLAliasesForLocation( $locationId, $custom = false )
    {
        $list = $this->backend->find(
            'Content\\UrlAlias',
            array(
                'destination' => $locationId,
                'type' => UrlAlias::LOCATION,
                'isCustom' => $custom,
                'isHistory' => false
            )
        );

        $expanded = array();
        foreach ( $list as $alias )
        {
            $expanded = array_merge( $expanded, $this->expandAlias( $alias ) );
        }

        return $expanded;
    }

    /**
     * Expands given $alias to array of new aliases for each different translation.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\UrlAlias $alias
     *
     * @return array
     */
    protected function expandAlias( $alias )
    {
        // extract names
        $names = array();
        $pathData = end( $alias->pathData );
        foreach ( $pathData["translations"] as $text )
        {
            $names[$text] = true;
        }

        // expand
        $expanded = array();
        foreach ( $names as $name => $dummy )
        {
            $cloned = clone $alias;
            $cloned->id = $cloned->id["parent"] . "-" . $this->getHash( $name );
            $expanded[] = $cloned;
        }

        return $expanded;
    }

    /**
     * Removes url aliases.
     *
     * Autogenerated aliases are not removed by this method.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\UrlAlias[] $urlAliases
     *
     * @return boolean
     */
    public function removeURLAliases( array $urlAliases )
    {
        foreach ( $urlAliases as $index => $urlAlias )
        {
            if ( !$urlAlias instanceof UrlAlias )
                throw new \eZ\Publish\Core\Base\Exceptions\InvalidArgumentException( "\$urlAliases[$index]", 'Expected UrlAlias instance' );

            if ( !$urlAlias->isCustom )
                continue;

            $this->backend->delete( 'Content\\UrlAlias', $urlAlias->id );
        }
    }

    /**
     * Looks up a url alias for the given url
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @param string $url
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias
     */
    public function lookup( $url )
    {
        $paths = explode( '/', $url );

        /**
         * @var \eZ\Publish\SPI\Persistence\Content\UrlAlias[] $urlAliases
         */
        $urlAliases = array_reverse( $this->backend->find( 'Content\\UrlAlias' ), true );
        foreach ( $urlAliases as $urlAlias )
        {
            foreach ( $paths as $index => $path )
            {
                // skip if url alias does not have this depth
                if ( empty( $urlAlias->pathData[$index]['translations'] ) )
                    continue 2;

                // check path against translations in a case in-sensitive manner
                $match = false;
                foreach ( $urlAlias->pathData[$index]['translations'] as $translatedPath )
                {
                    if ( strcasecmp( $path, $translatedPath ) === 0 )
                    {
                        $match = true;
                        break;
                    }
                }

                if ( !$match )
                    continue 2;
            }

            // skip if url alias has paths on a deeper depth then what $url has
            if ( isset( $urlAlias->pathData[$index + 1]['translations'] ) )
                continue;

            // This urlAlias seems to match, return it
            $urlAlias->id = $urlAlias->id["parent"] . "-" . $this->getHash( $translatedPath );
            return $urlAlias;
        }

        throw new NotFoundException( 'UrlAlias', $url );
    }

    /**
     * Loads URL alias by given $id
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @param string $id
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias
     */
    public function loadUrlAlias( $id )
    {
        return $this->backend->load( 'Content\\UrlAlias', $id );
    }

    /**
     * Notifies the underlying engine that a location has moved.
     *
     * This method triggers the change of the autogenerated aliases
     *
     * @param mixed $locationId
     * @param mixed $oldParentId
     * @param mixed $newParentId
     */
    public function locationMoved( $locationId, $oldParentId, $newParentId )
    {
        // Get url alias for old parent location
        $oldParentLocationAlias = $this->loadAutogeneratedAlias( $oldParentId );
        if ( !isset( $oldParentLocationAlias ) )
        {
            throw new \RuntimeException( "Did not find any url alias for location: {$oldParentLocationAlias}" );
        }
        // Get url alias for new parent location
        $newParentLocationAlias = $this->loadAutogeneratedAlias( $newParentId );
        if ( !isset( $newParentLocationAlias ) )
        {
            throw new \RuntimeException( "Did not find any url alias for location: {$newParentLocationAlias}" );
        }
        // Get url alias for old location
        $oldLocationAlias = $this->loadAutogeneratedAlias( $locationId, $oldParentLocationAlias->id["link"] );
        if ( !isset( $oldLocationAlias ) )
        {
            throw new \RuntimeException( "Did not find any url alias for location: {$oldLocationAlias}" );
        }

        // Mark as history and use pathData form existing location
        /** @var \eZ\Publish\SPI\Persistence\Content\UrlAlias[] $list */
        $this->backend->update( 'Content\\UrlAlias', $oldLocationAlias->id["id"], array( 'isHistory' => true ) );
        $pathItem = array_pop( $oldLocationAlias->pathData );

        // Make path data based on new location and the original
        $pathData = $newParentLocationAlias->pathData;
        $pathData[] = $pathItem;
        $pathIndex = count( $pathData ) - 1;

         // Create the new url alias object
        $newAlias = $this->backend->create(
            'Content\\UrlAlias',
            array(
                'parent' => $newParentLocationAlias->id["link"],
                'link' => $this->getNextLinkId(),
                'type' => UrlAlias::LOCATION,
                'destination' => $locationId,
                'pathData' => $pathData,
                'languageCodes' => array_keys( $pathData[$pathIndex]['translations'] ),
                'alwaysAvailable' => $pathData[$pathIndex]['always-available'],
                'isHistory' => false,
                'isCustom' => false,
                'forward' => false,
            )
        );

        // Fetch old location children
        $children = $this->backend->find(
            'Content\\UrlAlias',
            array(
                'parent' => $oldParentLocationAlias->id["link"],
                'type' => UrlAlias::LOCATION,
                'isHistory' => false,
                'isCustom' => false
            )
        );

        // @todo: this needs to recursively historize and copy (with updated path data) the complete subtree
        // Reparent
        foreach ( $children as $child )
        {
            $this->backend->update(
                'Content\\UrlAlias',
                $child->id["id"],
                array(
                    'parent' => $newAlias->id["link"]
                )
            );
        }
    }

    /**
     * Loads autogenerated location alias for given $locationId, optionally limited by given $parentId.
     *
     * @throws \RuntimeException
     *
     * @param mixed $locationId
     * @param null $parentId
     *
     * @return \eZ\Publish\SPI\Persistence\Content\UrlAlias|null
     */
    protected function loadAutogeneratedAlias( $locationId, $parentId = null )
    {
        $match = array(
            'destination' => $locationId,
            'type' => UrlAlias::LOCATION,
            'isHistory' => false,
            'isCustom' => false
        );
        if ( isset( $parentId ) )
        {
            $match["parent"] = $parentId;
        }
        $list = $this->backend->find( 'Content\\UrlAlias', $match );

        if ( isset( $list[1] ) )
        {
            throw new \RuntimeException( 'Found more then 1 url alias pointing to same location: ' . $locationId );
        }
        else if ( empty( $list ) )
        {
            return null;
        }

        return $list[0];
    }

    /**
     * Notifies the underlying engine that a location has moved.
     *
     * This method triggers the creation of the autogenerated aliases for the copied locations
     *
     * @param mixed $locationId
     * @param mixed $oldParentId
     * @param mixed $newParentId
     */
    public function locationCopied( $locationId, $oldParentId, $newParentId )
    {
        // Get url alias for location
        $list = $this->backend->find(
            'Content\\UrlAlias',
            array(
                'destination' => $locationId,
                'type' => UrlAlias::LOCATION,
                'isHistory' => false,
                'isCustom' => false
            )
        );

        if ( isset( $list[1] ) )
            throw new \RuntimeException( 'Found more then 1 url alias pointing to same location: ' . $locationId );
        else if ( empty( $list ) )
            throw new \RuntimeException( "Did not find any url alias for location:  {$locationId}" );

        // Use pathData from existing location
        /** @var \eZ\Publish\SPI\Persistence\Content\UrlAlias[] $list */
        $pathItem = array_pop( $list[0]->pathData );

        // Get url alias for new parent location
        $list = $this->backend->find(
            'Content\\UrlAlias',
            array(
                'destination' => $newParentId,
                'type' => UrlAlias::LOCATION,
                'isHistory' => false,
                'isCustom' => false
            )
        );

        if ( isset( $list[1] ) )
            throw new \RuntimeException( 'Found more then 1 url alias pointing to same location: ' . $newParentId );
        else if ( empty( $list ) )
            throw new \RuntimeException( "Did not find any url alias for new parent location: {$newParentId}" );

        // Make path data based on new location and the original
        $pathData = $list[0]->pathData;
        $pathData[] = $pathItem;
        $pathIndex = count( $pathData ) - 1;

        // Create the new url alias object
        $this->backend->create(
            'Content\\UrlAlias',
            array(
                'parent' => $list[0]->id["link"],
                'link' => $this->getNextLinkId(),
                'type' => UrlAlias::LOCATION,
                'destination' => $locationId,
                'pathData' => $pathData,
                'languageCodes' => array_keys( $pathData[$pathIndex]['translations'] ),
                'alwaysAvailable' => $pathData[$pathIndex]['always-available'],
                'isHistory' => false,
                'isCustom' => false,
                'forward' => false,
            )
        );
    }

    /**
     * Notifies the underlying engine that a location was deleted or moved to trash
     *
     * @param mixed $locationId
     */
    public function locationDeleted( $locationId )
    {
        $this->backend->deleteByMatch( 'Content\\UrlAlias', array( 'destination' => $locationId ) );
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function getHash( $text )
    {
        return md5( strtolower( $text ) );
    }

    /**
     * Returns max found link id incremented by 1
     *
     * @return int
     */
    protected function getNextLinkId()
    {
        $list = $this->backend->find( 'Content\\UrlAlias' );

        $id = 0;
        foreach ( $list as $alias )
        {
            $id = max( $id, $alias->id["link"] );
        }
        return $id + 1;
    }
}
