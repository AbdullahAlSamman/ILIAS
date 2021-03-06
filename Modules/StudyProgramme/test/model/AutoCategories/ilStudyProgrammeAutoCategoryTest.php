<?php

class ilStudyProgrammeAutoCategoryTest extends \PHPUnit\Framework\TestCase
{
    protected $backupGlobals = false;

    public function setUp() : void
    {
        PHPUnit_Framework_Error_Deprecated::$enabled = false;
        $this->prg_obj_id = 123;
        $this->cat_ref_id = 666;
        $this->usr_id = 6;
        $this->dat = new DateTimeImmutable('2019-06-05 15:25:12');
    }

    public function testConstruction()
    {
        $ac = new ilStudyProgrammeAutoCategory(
            $this->prg_obj_id,
            $this->cat_ref_id,
            $this->usr_id,
            $this->dat
        );
        $this->assertInstanceOf(
            ilStudyProgrammeAutoCategory::class,
            $ac
        );
        return $ac;
    }

    /**
     * @depends testConstruction
     */
    public function testGetPrgObjId($ac)
    {
        $this->assertEquals(
            $this->prg_obj_id,
            $ac->getPrgObjId()
        );
    }

    /**
     * @depends testConstruction
     */
    public function testGetCategoryRefId($ac)
    {
        $this->assertEquals(
            $this->cat_ref_id,
            $ac->getCategoryRefId()
        );
    }

    /**
     * @depends testConstruction
     */
    public function testGetLastEditorId($ac)
    {
        $this->assertEquals(
            $this->usr_id,
            $ac->getLastEditorId()
        );
    }

    /**
     * @depends testConstruction
     */
    public function testGetLastEdited($ac)
    {
        $this->assertEquals(
            $this->dat,
            $ac->getLastEdited()
        );
    }
}
