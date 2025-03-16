<?php
declare(strict_types=1);
/**
 * Implementation of the access tests
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

require_once('SeedDmsBase.php');

/**
 * Access test class
 *
 * @category  SeedDMS
 * @package   Tests
 * @author    Uwe Steinmann <uwe@steinmann.cx>
 * @copyright 2021 Uwe Steinmann
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @version   Release: @package_version@
 * @link      https://www.seeddms.org
 */
class AccessTest extends SeedDmsTest
{

    /**
     * Create a real sqlite database in memory
     *
     * @return void
     */
    protected function setUp(): void
    {
        self::$dbh = self::createInMemoryDatabase();
        // set STORAGE in phpunit.xml
        switch(STORAGE) {
        case 'file':
            self::$contentdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit-'.time();
            mkdir(self::$contentdir);
            $storage = new SeedDMS_Core_Storage_File(self::$contentdir);
            self::$dms = new SeedDMS_Core_DMS(self::$dbh, $storage);
            break;
        case '':
        case 'legacy':
            self::$contentdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit-'.time();
            mkdir(self::$contentdir);
            self::$dms = new SeedDMS_Core_DMS(self::$dbh, self::$contentdir);
            break;
        default:
            $storage = (STORAGE."_storage_init")();
            self::$dms = new SeedDMS_Core_DMS(self::$dbh, $storage);
            break;
        }
    }

    /**
     * Clean up at tear down
     *
     * @return void
     */
    protected function tearDown(): void
    {
        self::$dbh = null;
        if($storage = self::$dms->getStorage()) {
            $storage->deleteContentDir();
        } else {
            exec('rm -rf '.self::$contentdir);
        }
    }

    /**
     * Test method addAccess(), getAccessList()
     *
     * This method uses a real in memory sqlite3 database.
     *
     * @return void
     */
    public function testAddAccess()
    {
        $rootfolder = self::$dms->getRootFolder();
        $user = self::$dms->getUser(1);
        $rootfolder->addAccess(M_ALL, $user->getId(), 1);
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(0, $accesslist['groups']);
        $this->assertCount(1, $accesslist['users']);
        $this->assertTrue($accesslist['users'][0]->isAdmin());
        $this->assertEquals(M_ALL, $accesslist['users'][0]->getMode());
        $this->assertEquals(1, $accesslist['users'][0]->getUser()->getId());
    }

    /**
     * Test method getReadAccessList()
     *
     * This method uses a real in memory sqlite3 database.
     *
     * @return void
     */
    public function testReadAccess()
    {
        /* Create two groups, each with three users.
         * After calling createGroupsAndUsers() there will be
         * 6 regular users
         * 1 admin
         * 1 guest
         * 2 groups
         */
        self::createGroupsAndUsers();
        $rootfolder = self::$dms->getRootFolder();
        $rootuser = self::$dms->getUser(1);
        $this->assertTrue($rootuser->isAdmin());
        $guest = self::$dms->getUser(2);
        $this->assertTrue($guest->isGuest());
        /* User with id=3 or 4 are members of group with id=1 */
        $user = self::$dms->getUser(3);
        $this->assertTrue($user->isUser());
        $user2 = self::$dms->getUser(4);
        $this->assertTrue($user2->isUser());
        $group = self::$dms->getGroup(1);
        $group2 = self::$dms->getGroup(2);

        /* There are no specific access rights right now */
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(0, $accesslist['groups']);
        $this->assertCount(0, $accesslist['users']);

        /* Calling getReadAccessList() without any parameters will not
         * include admins, guests, and the owner of the folder.
         */
        $readaccess = $rootfolder->getReadAccessList();
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(6, $readaccess['users']);

        /* Include admins, no owner, no guests
         * Including admins doesn't make a difference because the admin
         * is the owner and owners will be removed from the list
         */
        $readaccess = $rootfolder->getReadAccessList(1, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(6, $readaccess['users']);

        /* Include owner, no admin, no guests
         * Same as before, but now the admin user is removed because
         * admins are not included, though owners are included.
         */
        $readaccess = $rootfolder->getReadAccessList(0, 1, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(6, $readaccess['users']);

        /* Include admins, no owner, include guests */
        $readaccess = $rootfolder->getReadAccessList(0, 0, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(7, $readaccess['users']);

        /* Include admins, owner, no guests
         * Since the owner is an admin, read access is still granted to
         * 6 regular users
         * 1 admin/owner
         * 0 guests
         */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(7, $readaccess['users']);

        /* Include admins, owners, guests */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(8, $readaccess['users']);

        /* Setting a regular user as an owner */
        $ret = $rootfolder->setOwner($user);
        $this->assertTrue($ret);

        /* The owner will now be skipped. Since admins and guests
         * are also skipped, there will be only 5 users left with
         * read access.
         */
        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(5, $readaccess['users']);

        /* Taking the admin into account will result into
         * 5 regular users + 1 admin
         */
        $readaccess = $rootfolder->getReadAccessList(1, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(6, $readaccess['users']);

        /* Also adding the owner and we have 
         * 6 regular users (1 of them is the owner) + 1 admin
         */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(7, $readaccess['users']);

        /* Take all users into account */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(8, $readaccess['users']);

        /* No access at all for a regular user (not the owner) of the
         * folder. Since the owner is skipped as well only 4 regular
         * users are left.
         */
        $rootfolder->addAccess(M_NONE, $user2->getId(), 1);
        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(4, $readaccess['users']);

        /* Remove access for group which has $user and $user2 as members
         * Only the second group with 3 members have still read access
         */
        $rootfolder->addAccess(M_NONE, $group->getId(), 0);
        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(1, $readaccess['groups']);
        $this->assertCount(3, $readaccess['users']);

        /* Remove access for group2 too, so no regular user has read access
         * anymore.
         */
        $rootfolder->addAccess(M_NONE, $group2->getId(), 0);
        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(0, $readaccess['groups']);
        $this->assertCount(0, $readaccess['users']);

        /* Including admins, owner, guests will result in 3 users with
         * read access
         * 1 admin user
         * 1 owner
         * 1 guest
         */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(0, $readaccess['groups']);
        $this->assertCount(3, $readaccess['users']);

        /* Remove all access restrictions */
        $rootfolder->clearAccessList();
        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(2, $readaccess['groups']);
        $this->assertCount(8, $readaccess['users']);

        /* Set default access to 'no access' */
        $rootfolder->setDefaultAccess(M_NONE);

        /* Only admin and owner is returned */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(0, $readaccess['groups']);
        $this->assertCount(2, $readaccess['users']);

        /* Not even admin and owner is returned */
        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(0, $readaccess['groups']);
        $this->assertCount(0, $readaccess['users']);

        /* Add read access for group2 */
        $rootfolder->addAccess(M_READ, $group2->getId(), 0);

        /* 1 group and all it's 3 members have access */
        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(1, $readaccess['groups']);
        $this->assertCount(3, $readaccess['users']);

        /* Add read access for a single user in the first group */
        $rootfolder->addAccess(M_READ, $user2->getId(), 1);

        $readaccess = $rootfolder->getReadAccessList(0, 0, 0);
        $this->assertCount(2, $readaccess);
        $this->assertCount(1, $readaccess['groups']);
        $this->assertCount(4, $readaccess['users']);

        /* Take the owner and admin into account returns
         * 1 group
         * 3 regular user of that group, 1 single user, 1 owner, 1 admin
         */
        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(1, $readaccess['groups']);
        $this->assertCount(6, $readaccess['users']);

        /* Add read access for the guest user */
        $rootfolder->addAccess(M_READ, $guest->getId(), 1);

        $readaccess = $rootfolder->getReadAccessList(1, 1, 1);
        $this->assertCount(2, $readaccess);
        $this->assertCount(1, $readaccess['groups']);
        $this->assertCount(7, $readaccess['users']);

    }

    /**
     * Test method addAccess(), changeAccess(), removeAccess()
     *
     * This method uses a real in memory sqlite3 database.
     *
     * @return void
     */
    public function testModifyAccess()
    {
        /* Create two groups, each with three users.
         * After calling createGroupsAndUsers() there will be
         * 6 regular users
         * 1 admin
         * 1 guest
         * 2 groups
         */
        self::createGroupsAndUsers();
        $rootfolder = self::$dms->getRootFolder();
        $rootuser = self::$dms->getUser(1);
        $this->assertTrue($rootuser->isAdmin());
        $guest = self::$dms->getUser(2);
        $this->assertTrue($guest->isGuest());
        /* User with id=3 is member of group with id=1 */
        $user = self::$dms->getUser(3);
        $group = self::$dms->getGroup(1);

        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(0, $accesslist['groups']);
        $this->assertCount(0, $accesslist['users']);

        $mode = $rootfolder->getGroupAccessMode($group);
        $this->assertEquals(M_READ, $mode);

        $rootfolder->addAccess(M_ALL, $user->getId(), 1);
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(0, $accesslist['groups']);
        $this->assertCount(1, $accesslist['users']);

        $rootfolder->addAccess(M_ALL, $group->getId(), 0);
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(1, $accesslist['groups']);
        $this->assertCount(1, $accesslist['users']);

        $g = $accesslist['groups'][0]->getGroup();
        $this->assertIsObject($g);
        $this->assertEquals($group->getId(), $g->getId());
        $this->assertEquals($accesslist['groups'][0]->getMode(), M_ALL);

        $mode = $rootfolder->getGroupAccessMode($group);
        $this->assertEquals(M_ALL, $mode);

        $u = $accesslist['users'][0]->getUser();
        $this->assertIsObject($u);
        $this->assertEquals($user->getId(), $u->getId());
        $this->assertEquals($accesslist['users'][0]->getMode(), M_ALL);

        $mode = $rootfolder->getAccessMode($user);
        $this->assertEquals(M_ALL, $mode);

        /* Change access rights of group */
        $old = $rootfolder->changeAccess(M_READWRITE, $group->getId(), 0);
        $this->assertEquals(M_ALL, $old);

        /* The number of access rights has not changed */
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(1, $accesslist['groups']);
        $this->assertCount(1, $accesslist['users']);

        $this->assertEquals(M_READWRITE, $accesslist['groups'][0]->getMode());

        $mode = $rootfolder->getGroupAccessMode($group);
        $this->assertEquals(M_READWRITE, $mode);

        /* Change access rights of group which does not exists */
        $ret = $rootfolder->changeAccess(M_READWRITE, 47, 0);
        $this->assertFalse($ret);

        /* Change access rights of user */
        $old = $rootfolder->changeAccess(M_READ, $user->getId(), 1);
        $this->assertEquals(M_ALL, $old);

        /* The number of access rights has not changed */
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(1, $accesslist['groups']);
        $this->assertCount(1, $accesslist['users']);

        /* The access right is now M_READ and not M_ALL anymore */
        $this->assertEquals(M_READ, $accesslist['users'][0]->getMode());

        $mode = $rootfolder->getAccessMode($user);
        $this->assertEquals(M_READ, $mode);

        /* Remove a none existent access right of a group */
        $ret = $rootfolder->removeAccess(4, 0);
        $this->assertFalse($ret);

        /* Remove access rights of group */
        $ret = $rootfolder->removeAccess($group->getId(), 0);
        $this->assertTrue($ret);

        /* We are back at the default access right */
        $mode = $rootfolder->getGroupAccessMode($group);
        $this->assertEquals(M_READ, $mode);

        /* The access rights for groups are gone */
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(0, $accesslist['groups']);
        $this->assertCount(1, $accesslist['users']);

        /* Remove a none existent access right of a user */
        $ret = $rootfolder->removeAccess(4, 1);
        $this->assertFalse($ret);

        /* Remove access rights of user */
        $ret = $rootfolder->removeAccess($user->getId(), 1);
        $this->assertTrue($ret);

        /* We are back at the default access right */
        $mode = $rootfolder->getAccessMode($user);
        $this->assertEquals(M_READ, $mode);

        /* The access rights for users are gone */
        $accesslist = $rootfolder->getAccessList();
        $this->assertCount(2, $accesslist);
        $this->assertCount(0, $accesslist['groups']);
        $this->assertCount(0, $accesslist['users']);
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
