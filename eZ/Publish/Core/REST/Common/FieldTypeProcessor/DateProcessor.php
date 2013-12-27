<?php
/**
 * File containing the DateProcessor class.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Common\FieldTypeProcessor;

use eZ\Publish\Core\REST\Common\FieldTypeProcessor;
use eZ\Publish\Core\FieldType\Date\Type;

class DateProcessor extends FieldTypeProcessor
{
    /**
     * {@inheritDoc}
     */
    public function preProcessFieldSettingsHash( $incomingSettingsHash )
    {
        if ( isset( $incomingSettingsHash["defaultType"] ) )
        {
            switch ( $incomingSettingsHash["defaultType"] )
            {
                case 'DEFAULT_EMPTY':
                    $incomingSettingsHash["defaultType"] = Type::DEFAULT_EMPTY;
                    break;
                case 'DEFAULT_CURRENT_DATE':
                    $incomingSettingsHash["defaultType"] = Type::DEFAULT_CURRENT_DATE;
            }
        }

        return $incomingSettingsHash;
    }

    /**
     * {@inheritDoc}
     */
    public function postProcessFieldSettingsHash( $outgoingSettingsHash )
    {
        if ( isset( $outgoingSettingsHash["defaultType"] ) )
        {
            switch ( $outgoingSettingsHash["defaultType"] )
            {
                case Type::DEFAULT_EMPTY:
                    $outgoingSettingsHash["defaultType"] = 'DEFAULT_EMPTY';
                    break;
                case Type::DEFAULT_CURRENT_DATE:
                    $outgoingSettingsHash["defaultType"] = 'DEFAULT_CURRENT_DATE';
            }
        }

        return $outgoingSettingsHash;
    }
}
