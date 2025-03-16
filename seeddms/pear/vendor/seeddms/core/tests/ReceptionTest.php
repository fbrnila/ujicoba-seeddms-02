<?php
/**
 * Implementation of the reception tests
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
class ReceptionTest extends SeedDmsTest
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
    public function testReceiptDocumentByUserAndGroup()
    {
        $rootfolder = self::$dms->getRootFolder();
        $user = self::$dms->getUser(1);
        $this->assertIsObject($user);

        /* Add a new user who will be the recipient */
        $recipient = self::$dms->addUser('recipient', 'recipient', 'Recipinet One', 'user1@seeddms.org', 'en_GB', 'bootstrap', '');
        $this->assertIsObject($recipient);

        /* Add a new group which will be the recipient */
        $recipientgrp = self::$dms->addGroup('recipient', '');
        $this->assertIsObject($recipientgrp);

        /* Add a new document */
        $document = self::createDocument($rootfolder, $user, 'Document 1');
        $content = $document->getLatestContent();
        $this->assertIsObject($content);
        $status = $content->getStatus();
        $this->assertIsArray($status);
        $this->assertEquals(S_RELEASED, $status['status']);

        /* A missing recipient or user causes an error */
        $ret = $content->addIndRecipient($recipient, null);
        $this->assertEquals(-1, $ret);

        /* A missing recipient or user causes an error */
        $ret = $content->addIndRecipient(null, $user);
        $this->assertEquals(-1, $ret);

        /* Adding a group instead of a user causes an error */
        $ret = $content->addIndRecipient($recipientgrp, $user);
        $this->assertEquals(-1, $ret);

        /* Finally add the recipient */
        $ret = $content->addIndRecipient($recipient, $user);
        $this->assertGreaterThan(0, $ret);

        /* Adding the user again will yield in an error */
        $ret = $content->addIndRecipient($recipient, $user);
        $this->assertEquals(-3, $ret);

        /* Add Recipients does not change the document status */
        $status = $content->getStatus();
        $this->assertIsArray($status);
        $this->assertEquals(S_RELEASED, $status['status']);

        /* Get all receipts */
        $receiptstatus = $content->getReceiptStatus();
        $this->assertIsArray($receiptstatus);
        $this->assertCount(1, $receiptstatus);

        /* Get list of individual und group recipients */
        $recipients = $content->getRecipients();
        $this->assertIsArray($recipients);
        $this->assertCount(2, $recipients);
        $this->assertCount(1, $recipients['i']);
        $this->assertCount(0, $recipients['g']);

        /* A missing recipient or user causes an error */
        $ret = $content->addGrpRecipient($recipientgrp, null);
        $this->assertEquals(-1, $ret);

        /* A missing recipient or user causes an error */
        $ret = $content->addGrpRecipient(null, $user);
        $this->assertEquals(-1, $ret);

        /* Adding a user instead of a group causes an error */
        $ret = $content->addGrpRecipient($recipient, $user);
        $this->assertEquals(-1, $ret);

        /* Finally add the recipient */
        $ret = $content->addGrpRecipient($recipientgrp, $user);
        $this->assertGreaterThan(0, $ret);

        /* Adding the group again will yield in an error */
        $ret = $content->addGrpRecipient($recipientgrp, $user);
        $this->assertEquals(-3, $ret);

        /* Get all receipts */
        $receiptstatus = $content->getReceiptStatus(3);
        $this->assertIsArray($receiptstatus);
        $this->assertCount(2, $receiptstatus);

        /* Get list of individual und group recipients */
        $recipients = $content->getRecipients();
        $this->assertIsArray($recipients);
        $this->assertCount(2, $recipients);
        $this->assertCount(1, $recipients['i']);
        $this->assertCount(1, $recipients['g']);

        $userstatus = $recipient->getReceiptStatus();
        $groupstatus = $recipientgrp->getReceiptStatus();

        /* There should be two log entries, one for each recipient. Both are sleeping */
        $receiptlog = $content->getReceiptLog(5);
        $this->assertIsArray($receiptlog);
        $this->assertCount(2, $receiptlog);
        $this->assertEquals(0, $receiptlog[0]['status']);
        $this->assertEquals(0, $receiptlog[1]['status']);

        /* Adding a reception without a user of recipient causes an error */
        $ret = $content->setReceiptByInd($recipient, null, S_LOG_ACCEPTED, 'Comment of individual recipient');
        $this->assertEquals(-1, $ret);
        $ret = $content->setReceiptByInd(null, $user, S_LOG_ACCEPTED, 'Comment of individual recipient');
        $this->assertEquals(-1, $ret);

        /* Adding a recipient as an individual but passing a group causes an error */
        $ret = $content->setReceiptByInd($recipientgrp, $user, S_LOG_ACCEPTED, 'Comment of individual recipient');
        $this->assertEquals(-1, $ret);

        /* Individual recipient receives document */
        $ret = $content->setReceiptByInd($recipient, $user, S_LOG_ACCEPTED, 'Comment of individual recipient');
        $this->assertIsInt(0, $ret);
        $this->assertGreaterThan(0, $ret);

        /* Get the last 5 receipt log entries (actually there are just 3 now) */
        $receiptlog = $content->getReceiptLog(5);
        $this->assertIsArray($receiptlog);
        $this->assertCount(3, $receiptlog);
        $this->assertEquals('Comment of individual recipient', $receiptlog[0]['comment']);
        $this->assertEquals(1, $receiptlog[0]['status']);

        /* Adding a receipt without a user of recipient causes an error */
        $ret = $content->setReceiptByGrp($recipientgrp, null, S_LOG_ACCEPTED, 'Comment of group recipient');
        $this->assertEquals(-1, $ret);
        $ret = $content->setReceiptByGrp(null, $user, S_LOG_ACCEPTED, 'Comment of group recipient');
        $this->assertEquals(-1, $ret);

        /* Adding a receipt as an group but passing a user causes an error */
        $ret = $content->setReceiptByGrp($recipient, $user, S_LOG_ACCEPTED, 'Comment of group recipient');
        $this->assertEquals(-1, $ret);

        /* Group recipient receipts document */
        $ret = $content->setReceiptByGrp($recipientgrp, $user, S_LOG_ACCEPTED, 'Comment of group recipient');
        $this->assertIsInt(0, $ret);
        $this->assertGreaterThan(0, $ret);

        /* Get the last 5 receipt log entries (actually there are just 4 now) */
        $receiptlog = $content->getReceiptLog(5);
        $this->assertIsArray($receiptlog);
        $this->assertCount(4, $receiptlog);
        $this->assertEquals('Comment of group recipient', $receiptlog[0]['comment']);
        $this->assertEquals(1, $receiptlog[0]['status']);

        $ret = $document->remove();
        $this->assertTrue($ret);
    }
}

