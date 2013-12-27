<?php
/**
 * File containing the DateTest class
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Legacy\Tests\Content\FieldValue\Converter;

use eZ\Publish\Core\FieldType\FieldSettings;
use eZ\Publish\SPI\Persistence\Content\FieldValue;
use eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldValue;
use eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldDefinition;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldDefinition;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\Float ;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\Float as FloatConverter;
use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\Float as FloatConverter;
use eZ\Publish\SPI\Persistence\Content\Type\FieldDefinition as PersistenceFieldDefinition;
use eZ\Publish\SPI\Persistence\Content\FieldTypeConstraints;
use PHPUnit_Framework_TestCase;
use eZ\Publish\Core\FieldType\Float;

/**
 * Test case for Date converter in Legacy storage
 *
 * @group fieldType
 * @group date
 */
class FloatTest extends PHPUnit_Framework_TestCase
{
    protected $converter;

    protected function setUp()
    {
        parent::setUp();
        $this->converter = new FloatConverter;
        $this->float = new Float( "@1362614400" );
    }



     /**
     * @covers \eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter\Float::toFieldDefinition
     */
    public function testToFieldDefinition()
    {
        $fieldDef= new PersistenceFieldDefinition;


         $storageFieldValue= new StorageFieldDefinition(
             array(
               'dataFloat1' = 0,
               'dataFloat2' = 10,
               'dataFloat4' = 3;
             )
         );
        $this->converter->toFieldDefinition( $storageFieldValue, $fieldDef );
        self::assertInternalType( "array", $fieldDef->defaultValue->data );
        self::assertNull( $fieldDef->defaultValue->data["rfc850"] );
        self::assertSame( $timestamp, $fieldDef->defaultValue->data["timestamp"] );
    }
}

       /* $this->convert->tof
        $storageDef= new StorageFieldDefinition(
            array(
                 'dataFloat1' => 0,
                 'dataFloat2' => 10,
                 'dataFloat4' => 3;
            )
        )

        $fieldDef = new FieldDefinition;
        $this->converter->toFieldDefinition( $storageFieldValue, $fieldDef);
        self::assertSame( $this->$fieldDef->fieldTypeConstraints->validators[self::FLOAT_VALIDATOR_IDENTIFIER]['maxFloatValue'], $storageFieldValue->dataFloat2 );
        self::assertSame( $this->$fieldDef->fieldTypeConstraints->validators[self::FLOAT_VALIDATOR_IDENTIFIER]['minFloatValue'], $storageFieldValue->dataFloat1 );
        self::assertSame( $this->$fieldDef->dataFlot4, $storageFieldValue->dataFloat4 );
    }

{
$dateTime = new DateTime();
$timestamp = $dateTime->setTime( 0, 0, 0 )->getTimestamp();
$fieldDef = new PersistenceFieldDefinition;
$storageDef = new StorageFieldDefinition(
array(
"dataInt1" => DateType::DEFAULT_CURRENT_DATE
)
);

$this->converter->toFieldDefinition( $storageDef, $fieldDef );
self::assertInternalType( "array", $fieldDef->defaultValue->data );
self::assertCount( 2, $fieldDef->defaultValue->data );
self::assertNull( $fieldDef->defaultValue->data["rfc850"] );
self::assertSame( $timestamp, $fieldDef->defaultValue->data["timestamp"] );
}


public function toFieldDefinition( StorageFieldDefinition $storageDef, FieldDefinition $fieldDef )
{
    $fieldDef->fieldTypeConstraints->fieldSettings = new FieldSettings(
        array(
             "defaultType" => $storageDef->dataInt1
        )
    );

    // Building default value
    switch ( $fieldDef->fieldTypeConstraints->fieldSettings["defaultType"] )
    {
        case DateType::DEFAULT_CURRENT_DATE:
            $dateTime = new DateTime();
            $dateTime->setTime( 0, 0, 0 );
            $data = array(
                "timestamp" => $dateTime->getTimestamp(),
                "rfc850" => null,
            );
            break;
        default:
            $data = null;
    }



}
