<?php
/**
 * Implementation of the revision tests
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

use PHPUnit\Framework\SeedDmsTest;

/**
 * Group test class
 *
 * @category  SeedDMS
 * @package   Tests
 * @author    Uwe Steinmann <uwe@steinmann.cx>
 * @copyright 2021 Uwe Steinmann
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @version   Release: @package_version@
 * @link      https://www.seeddms.org
 */
class RevisionTest extends SeedDmsTest
{

    /**
     * Create a real sqlite database in memory
     *
     * @return void
     */
    protected function setUp(): void
    {
        self::$dbh = self::createInMemoryDatabase();
        self::$contentdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit-'.time();
        mkdir(self::$contentdir);
        //      echo "Creating temp content dir: ".self::$contentdir."\n";
        self::$dms = new SeedDMS_Core_DMS(self::$dbh, self::$contentdir);
    }

    /**
     * Clean up at tear down
     *
     * @return void
     */
    protected function tearDown(): void
    {
        self::$dbh = null;
        //      echo "\nRemoving temp. content dir: ".self::$contentdir."\n";
        exec('rm -rf '.self::$contentdir);
    }

    /**
     * Test method addIndRevisor(), addGrpRevisor(), verifyStatus(),
     * getRevisionStatus(), removeRevision(), delIndRevisor()
     *
     * This method uses a real in memory sqlite3 database.
     *
     * @return void
     */
    public function testReviseDocumentByUserAndGroup()
    {
        $rootfolder = self::$dms->getRootFolder();
        $user = self::$dms->getUser(1);
        $this->assertIsObject($user);

        /* Add a new user who will be the revisor */
        $revisor = self::$dms->addUser('revisor', 'revisor', 'Revisor One', 'user1@seeddms.org', 'en_GB', 'bootstrap', '');
        $this->assertIsObject($revisor);

        /* Add a new group which will be the revisor */
        $revisorgrp = self::$dms->addGroup('revisor', '');
        $this->assertIsObject($revisorgrp);

        /* Add a new document */
        $document = self::createDocument($rootfolder, $user, 'Document 1');
        $content = $document->getLatestContent();
        $this->assertIsObject($content);
        $status = $content->getStatus();
        $this->assertIsArray($status);
        $this->assertEquals(S_RELEASED, $status['status']);

        /* A missing revisor or user causes an error */
        $ret = $content->addIndRevisor($revisor, null);
        $this->assertEquals(-1, $ret);

        /* A missing revisor or user causes an error */
        $ret = $content->addIndRevisor(null, $user);
        $this->assertEquals(-1, $ret);

        /* Adding a group instead of a user causes an error */
        $ret = $content->addIndRevisor($revisorgrp, $user);
        $this->assertEquals(-1, $ret);

        /* Finally add the revisor */
        $ret = $content->addIndRevisor($revisor, $user);
        $this->assertGreaterThan(0, $ret);

        /* Adding the user again will yield in an error */
        $ret = $content->addIndRevisor($revisor, $user);
        $this->assertEquals(-3, $ret);

        /* Add Revisors does not change the document status */
        $status = $content->getStatus();
        $this->assertIsArray($status);
        $this->assertEquals(S_RELEASED, $status['status']);

        /* Get all revisions */
        $revisionstatus = $content->getRevisionStatus(3);
        $this->assertIsArray($revisionstatus);
        $this->assertCount(1, $revisionstatus);

        /* Get list of individual und group revisors */
        $revisors = $content->getRevisors();
        $this->assertIsArray($revisors);
        $this->assertCount(2, $revisors);
        $this->assertCount(1, $revisors['i']);
        $this->assertCount(0, $revisors['g']);

        /* A missing revisor or user causes an error */
        $ret = $content->addGrpRevisor($revisorgrp, null);
        $this->assertEquals(-1, $ret);

        /* A missing revisor or user causes an error */
        $ret = $content->addGrpRevisor(null, $user);
        $this->assertEquals(-1, $ret);

        /* Adding a user instead of a group causes an error */
        $ret = $content->addGrpRevisor($revisor, $user);
        $this->assertEquals(-1, $ret);

        /* Finally add the revisor */
        $ret = $content->addGrpRevisor($revisorgrp, $user);
        $this->assertGreaterThan(0, $ret);

        /* Adding the group again will yield in an error */
        $ret = $content->addGrpRevisor($revisorgrp, $user);
        $this->assertEquals(-3, $ret);

        /* Get all revisions */
        $revisionstatus = $content->getRevisionStatus(3);
        $this->assertIsArray($revisionstatus);
        $this->assertCount(2, $revisionstatus);

        /* Get list of individual und group revisors */
        $revisors = $content->getRevisors();
        $this->assertIsArray($revisors);
        $this->assertCount(2, $revisors);
        $this->assertCount(1, $revisors['i']);
        $this->assertCount(1, $revisors['g']);

        $userstatus = $revisor->getRevisionStatus();
        $groupstatus = $revisorgrp->getRevisionStatus();

        /* There should be two log entries, one for each revisor. Both are sleeping */
        $revisionlog = $content->getRevisionLog(5);
        $this->assertIsArray($revisionlog);
        $this->assertCount(2, $revisionlog);
        $this->assertEquals(S_LOG_SLEEPING, $revisionlog[0]['status']);
        $this->assertEquals(S_LOG_SLEEPING, $revisionlog[1]['status']);

        /* set a revision if it has not been started yet causes an error */
        $ret = $content->setRevisionByInd($revisor, $user, S_LOG_ACCEPTED, 'Comment of individual revisor');
        $this->assertEquals(-5, $ret);

        /* Start revision */
        $ret = $content->startRevision($user);
        $this->assertTrue($ret);

        /* After starting the revision, the number log entries has doubled */
        $revisionlog = $content->getRevisionLog(5);
        $this->assertIsArray($revisionlog);
        $this->assertCount(4, $revisionlog);

        /* The document status changes to S_IN_REVISION */
        $newstatus = $content->verifyStatus(false, $user);
        $this->assertIsInt($newstatus);
        $this->assertEquals(S_IN_REVISION, $newstatus);

        /* Adding a revision without a user of revisor causes an error */
        $ret = $content->setRevisionByInd($revisor, null, S_LOG_ACCEPTED, 'Comment of individual revisor');
        $this->assertEquals(-1, $ret);
        $ret = $content->setRevisionByInd(null, $user, S_LOG_ACCEPTED, 'Comment of individual revisor');
        $this->assertEquals(-1, $ret);

        /* Adding a revisor as an individual but passing a group causes an error */
        $ret = $content->setRevisionByInd($revisorgrp, $user, S_LOG_ACCEPTED, 'Comment of individual revisor');
        $this->assertEquals(-1, $ret);

        /* Individual revisor revises document */
        $ret = $content->setRevisionByInd($revisor, $user, S_LOG_ACCEPTED, 'Comment of individual revisor');
        $this->assertIsInt(0, $ret);
        $this->assertGreaterThan(0, $ret);

        /* Get the last 5 revision log entries (actually there are just 3 now) */
        $revisionlog = $content->getRevisionLog(5);
        $this->assertIsArray($revisionlog);
        $this->assertCount(5, $revisionlog);
        $this->assertEquals('Comment of individual revisor', $revisionlog[0]['comment']);
        $this->assertEquals(1, $revisionlog[0]['status']);

        /* Needs to call verifyStatus() in order to recalc the status.
         * It must not be changed because the group revisor has not done the
         * revision.
         */
        $newstatus = $content->verifyStatus(false, $user);
        $this->assertIsInt($newstatus);
        $this->assertEquals(S_IN_REVISION, $newstatus);

        /* Adding a revision without a user of revisor causes an error */
        $ret = $content->setRevisionByGrp($revisorgrp, null, S_LOG_ACCEPTED, 'Comment of group revisor');
        $this->assertEquals(-1, $ret);
        $ret = $content->setRevisionByGrp(null, $user, S_LOG_ACCEPTED, 'Comment of group revisor');
        $this->assertEquals(-1, $ret);

        /* Adding a revision as an group but passing a user causes an error */
        $ret = $content->setRevisionByGrp($revisor, $user, S_LOG_ACCEPTED, 'Comment of group revisor');
        $this->assertEquals(-1, $ret);

        /* Group revisor revisions document */
        $ret = $content->setRevisionByGrp($revisorgrp, $user, S_LOG_ACCEPTED, 'Comment of group revisor');
        $this->assertIsInt(0, $ret);
        $this->assertGreaterThan(0, $ret);

        /* Get the last 5 revision log entries (actually there are just 4 now) */
        $revisionlog = $content->getRevisionLog(8);
        $this->assertIsArray($revisionlog);
        $this->assertCount(6, $revisionlog);
        $this->assertEquals('Comment of group revisor', $revisionlog[0]['comment']);
        $this->assertEquals(1, $revisionlog[0]['status']);

        /* Now the document has received all revisions */
        $newstatus = $content->verifyStatus(false, $user);
        $this->assertIsInt($newstatus);
        $this->assertEquals(S_RELEASED, $newstatus);

        $ret = $document->remove();
        $this->assertTrue($ret);
    }

}

