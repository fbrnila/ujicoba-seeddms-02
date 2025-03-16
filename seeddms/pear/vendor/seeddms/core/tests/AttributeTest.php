<?php
/**
 * Implementation of the attribute tests
 *
 * PHP version 7
 *
 * @category  SeedDMS
 * @package   Tests
 * @author    Uwe Steinmann <uwe@steinmann.cx>
 * @copyright 2021 Uwe Steinmann
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @version   @package_version@
 * @link      https://www.seeddms.org
 */

use PHPUnit\Framework\TestCase;

/**
 * Attribute and attribute definition test class
 *
 * @category  SeedDMS
 * @package   Tests
 * @author    Uwe Steinmann <uwe@steinmann.cx>
 * @copyright 2021 Uwe Steinmann
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @version   Release: @package_version@
 * @link      https://www.seeddms.org
 */
class AttributeTest extends TestCase
{

    /**
     * Create a mock dms object
     *
     * @return SeedDMS_Core_DMS
     */
    protected function getMockDMS() : SeedDMS_Core_DMS
    {
        $db = $this->createMock(SeedDMS_Core_DatabaseAccess::class);
        /* Any UPDATE statement will succeed */
        $db->expects($this->any())
            ->method('getResult')
            ->with($this->stringContains("UPDATE "))
            ->willReturn(true);
        $dms = new SeedDMS_Core_DMS($db, '');
        return $dms;
    }

    /**
     * Create a mock attribute definition object
     *
     * @param int     $type      type of attribute
     * @param boolean $multiple  true if multiple values are allowed
     * @param int     $minvalues minimum number of required values
     * @param int     $maxvalues maximum number of required value
     * @param string  $valueset  list of allowed values separated by the first char
     * @param string  $regex     regular expression the attribute value must match
     *
     * @return SeedDMS_Core_AttributeDefinition
     */
    protected function getAttributeDefinition($objtype, $type, $multiple=false, $minvalues=0, $maxvalues=0, $valueset='', $regex='')
    {
        $attrdef = new SeedDMS_Core_AttributeDefinition(1, 'foo attrdef', $objtype, $type, $multiple, $minvalues, $maxvalues, $valueset, $regex);
        return $attrdef;
    }

    /**
     * Create a mock attribute object
     *
     * @param SeedDMS_Core_AttributeDefinition $attrdef attribute defintion of attribute
     * @param mixed                            $value   value of attribute
     *
     * @return SeedDMS_Core_Attribute
     */
    static protected function getFolderAttribute($attrdef, $value)
    {
        $folder = new SeedDMS_Core_Folder(1, 'Folder', null, '', '', '', 0, 0, 0);
        $attribute = new SeedDMS_Core_Attribute(1, $folder, $attrdef, $value);
        $attribute->setDMS($attrdef->getDMS());
        return $attribute;
    }

    /**
     * Create a mock attribute object
     *
     * @param SeedDMS_Core_AttributeDefinition $attrdef attribute defintion of attribute
     * @param mixed                            $value   value of attribute
     *
     * @return SeedDMS_Core_Attribute
     */
    static protected function getDocumentAttribute($attrdef, $value)
    {
        $document = new SeedDMS_Core_Document(1, 'Document', '', time(), null, 1, 1, 1, M_READ, 0, '', 1.0);
        $attribute = new SeedDMS_Core_Attribute(1, $document, $attrdef, $value);
        $attribute->setDMS($attrdef->getDMS());
        return $attribute;
    }

    /**
     * Test getId()
     *
     * @return void
     */
    public function testGetId()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int);
        $attrdef->setDMS(self::getMockDMS());
        $attribute = self::getFolderAttribute($attrdef, '');
        $this->assertEquals(1, $attribute->getId());
        $this->assertIsObject($attribute->getDMS());
    }

    /**
     * Test attribute definition
     *
     * @return void
     */
    public function testAttributeDefinitionName()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int);
        $attrdef->setDMS(self::getMockDMS());
        $this->assertEquals('foo attrdef', $attrdef->getName());
        $attrdef->setName('new name');
        $this->assertEquals('new name', $attrdef->getName());
    }

    /**
     * Test attribute definition
     *
     * @return void
     */
    public function testAttributeDefinitionInt()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int);
        $attrdef->setDMS(self::getMockDMS());
        $this->assertEquals(SeedDMS_Core_AttributeDefinition::objtype_folder, $attrdef->getObjType());
        $this->assertEquals(SeedDMS_Core_AttributeDefinition::type_int, $attrdef->getType());
        $this->assertFalse($attrdef->getMultipleValues());
        $this->assertEquals('', $attrdef->getValueSet());
        $this->assertIsArray($attrdef->getValueSetAsArray());
        $this->assertCount(0, $attrdef->getValueSetAsArray());
        $this->assertFalse($attrdef->getValueSetValue(0));
        $this->assertTrue($attrdef->validate(0));
        $this->assertFalse($attrdef->validate('a'));
    }

    /**
     * Test attribute definition
     *
     * @return void
     */
    public function testAttributeDefinitionIntValueSet()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int, true, 0, 0, ',1,3,6');
        $attrdef->setDMS(self::getMockDMS());
        $this->assertEquals(SeedDMS_Core_AttributeDefinition::objtype_folder, $attrdef->getObjType());
        $this->assertEquals(SeedDMS_Core_AttributeDefinition::type_int, $attrdef->getType());
        $this->assertTrue($attrdef->getMultipleValues());
        $this->assertEquals(',1,3,6', $attrdef->getValueSet());
        $this->assertEquals(',', $attrdef->getValueSetSeparator());
        $this->assertIsArray($attrdef->getValueSetAsArray());
        $this->assertCount(3, $attrdef->getValueSetAsArray());
        $this->assertEquals(1, $attrdef->getValueSetValue(0));
        $this->assertEquals(6, $attrdef->getValueSetValue(2));
        $this->assertTrue($attrdef->validate(1));
        $this->assertFalse($attrdef->validate(2));
        $this->assertFalse($attrdef->validate('a'));
        $this->assertIsArray($attrdef->parseValue('2'));
        $this->assertCount(2, $attrdef->parseValue(',2,5'));
        $this->assertTrue($attrdef->setMinValues(3));
        $this->assertFalse($attrdef->validate(1));
        $this->assertFalse($attrdef->validate([1,3]));
        $this->assertTrue($attrdef->validate([1,3,6]));
        /* Valueset hat only 3 Elements */
        $this->assertFalse($attrdef->validate([1,3,6,8]));
        $this->assertTrue($attrdef->setValueSet(',3,6,8,1,9,0'));
        $this->assertTrue($attrdef->validate([1,3,6,8]));
    }

    /**
     * Test attribute definition
     *
     * @return void
     */
    public function testAttributeDefinitionBool()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_boolean);
        $attrdef->setDMS(self::getMockDMS());
        $this->assertEquals(SeedDMS_Core_AttributeDefinition::objtype_folder, $attrdef->getObjType());
        $this->assertEquals(SeedDMS_Core_AttributeDefinition::type_boolean, $attrdef->getType());
        $this->assertFalse($attrdef->getMultipleValues());
        $this->assertEquals('', $attrdef->getValueSet());
        $this->assertTrue($attrdef->validate(0));
        $this->assertTrue($attrdef->validate(1));
        $this->assertTrue($attrdef->validate(true));
        $this->assertTrue($attrdef->validate(false));
        $this->assertFalse($attrdef->validate('a'));
    }
    /**
     * Test getValue()
     *
     * @return void
     */
    public function testGetValue()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int);
        $attrdef->setDMS(self::getMockDMS());
        $attribute = self::getFolderAttribute($attrdef, 7);
        $this->assertEquals(7, $attribute->getValue());
        $this->assertIsObject($attribute->getDMS());
    }

    /**
     * Test getValueAsArray()
     *
     * @return void
     */
    public function testGetValueAsArray()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int);
        $attrdef->setDMS(self::getMockDMS());
        $attribute = self::getFolderAttribute($attrdef, 7);
        $this->assertIsArray($attribute->getValueAsArray());
        $this->assertCount(1, $attribute->getValueAsArray());
        $this->assertContains(7, $attribute->getValueAsArray());

        /* Test a multi value integer */
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int, true);
        $attribute = self::getFolderAttribute($attrdef, [3,4,6]);
        $value = $attribute->getValueAsArray();
        $this->assertIsArray($attribute->getValueAsArray());
        $this->assertCount(3, $attribute->getValueAsArray());
        $this->assertContains(6, $attribute->getValueAsArray());
        /* getValue() must return the same result as getValueAsArray() */
        $value = $attribute->getValue();
        $this->assertIsArray($attribute->getValueAsArray());
        $this->assertCount(3, $attribute->getValueAsArray());
        $this->assertContains(6, $attribute->getValueAsArray());
    }

    /**
     * Test setValue()
     *
     * @return void
     */
    public function testSetValue()
    {
        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int);
        $attrdef->setDMS(self::getMockDMS());
        $attribute = self::getFolderAttribute($attrdef, 0);
        $this->assertTrue($attribute->setValue(9));
        $this->assertEquals(9, $attribute->getValue());
        /* Setting an array of values for a none multi value attribute will just take the
         * first element of the array.
         */
        $this->assertTrue($attribute->setValue([8,9]));
        $this->assertEquals(8, $attribute->getValue());

        $attrdef = self::getAttributeDefinition(SeedDMS_Core_AttributeDefinition::objtype_folder, SeedDMS_Core_AttributeDefinition::type_int, true);
        $attrdef->setDMS(self::getMockDMS());
        $attribute = self::getFolderAttribute($attrdef, [3,4,6]);
        $attribute->setValue([8,9,10]);
        $this->assertIsArray($attribute->getValue());
        $this->assertCount(3, $attribute->getValue());
        $this->assertContains(9, $attribute->getValue());
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: s
 * End:
 * vim600: fdm=marker
 * vim: et sw=4 ts=4
 */
