<?php
require_once 'simpletest/autorun.php';

use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;
use \Core\Db;
use \Core\ModelActions;

class BlogModels extends UnitTestCase {
    
    public function setUp()
    {
        /**
         *
DELETE FROM mod_txns;
DELETE FROM mod_txns_items;
DELETE FROM mod_txns_payments;
DELETE FROM mod_txns_payments_items;
DELETE FROM mod_txns_shipments;

DELETE FROM mod_categories;
DELETE FROM mod_categories_joins;
DELETE FROM mod_accounts;

         * 
         */
    }

    /**
     *
     * @access
     * @var
     */
    private function addpost()
    {
        $post = array(
            'blog_title'    => "lorem ipsum title ".Tools::Uuid(),
            'blog_details'  => "lorem ipsum details ".Tools::Uuid()
        );

        $Post = Module::Blog('Post');

        //Debug::Dump($Post->resultkey);

        $Post->add($post);

        //Debug::Dump($Post->result);

        $Post->save();

        return array($Post, $post);
    }
    
    /**
     *
     * @access
     * @var
     */
    public function testPostBasicCRUD()
    {
        list($Post, $post) = $this->addpost();

        $blogid = $Post['blogid'];

        /**
         * fit test
         */
        $this->assertEqual($Post['blog_title'], $post['blog_title']);
        $this->assertEqual($Post['blog_details'], $post['blog_details']);
        
        $this->assertEqual($Post->result['blog_title'], $post['blog_title']);
        $this->assertEqual($Post->result['blog_details'], $post['blog_details']);

        $this->assertEqual($Post->resultkey, $blogid);
        $this->assertEqual($Post->result['blogid'], $blogid);
        
        $this->assertTrue(!($blog != 0));

        Debug::Dump($Post->result);

        $Post->clear();

        $this->assertTrue($Post->isEmpty());
        
        /**
         * find post
         * - test custom fields
         */
        $Post->find($blogid, array(
            'where'         => array(
                'blogid_plus_one' => $blogid+1
            ),
            'conditions'    => NULL,
            'select_fields' => array(
                0 => '*',
                'blogid_plus_one' => 'blogid+1' // value must be a valid sql expression
            )
        ));

        $this->assertTrue(!$Post->isEmpty());

        $this->assertEqual($Post['blogid_plus_one'], $blogid+1);

        Debug::Dump($Post->result);

        /**
         * remove the blogid
         */
        $Post->remove();

        /**
         * result should still exists in memory only
         */
        $this->assertEqual($Post->resultkey, $blogid);
        $this->assertEqual($Post->result['blogid'], $blogid);

        /**
         * search should yield not found = true
         */
        $notfound = false;
        try {
            $Post->find($blogid, array(
                'conditions' => NULL
            ));
        } catch (Exception $exc) {
            $notfound = true;
        }//end try

        $this->assertTrue($notfound);
    }

    /**
     *
     * @access
     * @var
     */
    public function testPolymorphicCategoriesCRUD()
    {
        $Category = Module::Polymorphic('Categories');

        $category = array(
            'cat_name'  => 'category name 01 post'.Tools::Uuid() ,
            'cat_group' => 'simpletest',
        );
        
        $Category->add($category);
        $Category->save();

        $categoryid = $Category['categoryid'];

        /**
         * fit test
         */
        $this->assertEqual($Category['cat_name'], $category['cat_name']);
        $this->assertEqual($Category['cat_group'], $category['cat_group']);

        $this->assertEqual($Category->resultkey, $categoryid);
        $this->assertEqual($Category->result['categoryid'], $categoryid);

        Debug::Dump($Category->result);
        
        $Category->remove();

        /**
         * search should yield not found = true
         */
        $notfound = false;
        try {
            $Category->find($categoryid, array(
               'conditions' => NULL
            ));
        } catch (Exception $exc) {
            $notfound = true;
        }//end try

        $this->assertTrue($notfound);
    }

    /**
     *
     * @access
     * @var
     */
    public function testPostCategoryAssociations()
    {
        list($Post, $post) = $this->addpost();

        $category = array(
            'cat_name'  => 'category name 01 post'.Tools::Uuid() ,
            'cat_group' => 'simpletest' ,
        );

        /**
         * to access or just return the model
         * set the value to false, this will skip the query call on access
         */
        $Post->Categories = false;
        $Categories = $Post->Categories;
        $this->assertTrue($Categories->is_associated);
        
        $Categories->object();
        $this->assertFalse($Categories->is_associated);

        $Categories->add($category);
        $Categories->save();
        
        $categoryid = $Categories->result['categoryid'];
        $blogid = $Post['blogid'];

        $this->assertEqual($Post->Categories->resultkey, $categoryid);
        $this->assertEqual($Post->Categories['categoryid'], $categoryid);

        Debug::Dump($Post->Categories->result);

        $Post->Categories = true;
        /**
         * now link the current blog post
         * to the category by calling the save
         */
        Debug::Dump($Post->result);
        $Post->save();

        /**
         * clear the model object
         */
        $Post->clear();

        /**
         * query the category id,
         * since clear was called disable the query call on access
         */
        $Post->Categories = false;
        /**
         * calling the object() method would ensure that the categories table
         * would not be inner joined with the associated table defined in posts class
         */
        $Post->Categories->object()->find($categoryid, array(
            'conditions' => NULL
        ));

        Debug::Dump($Post->Categories->result);

        $this->assertEqual($Post->Categories->result['categoryid'], $categoryid);
        
        /**
         * enable the query call on access by
         * setting the value true
         */
        $Post->Categories = true;
        
        /**
         * now query all blog post that exists on the found blog post category
         * - try eager loading
         */
        $Post->load->Categories;
        
        /**
         * select join Categories
         */
        $Post->join->Categories->find('*', array(
            'conditions'    => NULL,
            //'select_fields' => array('*')
            'select_fields' => '*' // if passed as string, it will be sliced into array, comman delimited
        ));
        
        Debug::Dump($Post->result);
        $this->assertEqual($Post['blogid'], $blogid);
        $this->assertEqual($Post['joinid'], $blogid);
        $this->assertEqual($Post['categoryid'], $categoryid);
        $this->assertEqual($Post->Categories['categoryid'], $categoryid);
        $this->assertEqual($Post->Categories['joinid'], $blogid);
        
        /**
         * eager loading works if is_cached is true
         */
        $this->assertTrue($Post->Categories->is_cached);

        /**
         * now unlink the $blogid to the $categoryid
         */
        $Post->clear();
        $Post->find($blogid, array(
            'conditions' => NULL
        ));
        
        /**
         * disable query on call access for categories
         */
        $Post->Categories = false;
        /**
         * Categories should be empty
         */
        $this->assertTrue(empty($Post->Categories->result));

        /**
         * enable query on call access for categories
         */
        $Post->Categories = true;

        /**
         * Categories should not be empty
         */
        $this->assertTrue(!empty($Post->Categories->result));
                
        $this->assertEqual($Post->Categories['categoryid'], $categoryid);

        /**
         * now remove the associated category to the post
         */
        $Post->Categories->remove();

        /**
         * lets clear the categories first
         */
        $Post->Categories->clear();

        /**
         * we expect the post category association is now removed
         * so the result should be empty
         */
        $this->assertTrue(empty($Post->Categories->result));


    }

    /**
     *
     * @access
     * @var
     */
    public function testBelongsToAssociations()
    {
        list($Post, $post) = $this->addpost();
        $author = array(
            'acnt_name'    => "author name ".Tools::Uuid()
        );

        /**
         * add author
         */
        $Post->Author->add($author);
        $Post->Author->save();

        /**
         * accountid should not be empty
         */
        $this->assertTrue(!empty($Post->Author['accountid']));

        /**
         * calling the post save should link the current author
         * to the current post automatically
         */
        $Post->save();

        $this->assertEqual($Post['blog_authorid'], $Post->Author['accountid']);

        Debug::Dump($Post->Author->resultkey);
        Debug::Dump($Post->result);

        $this->assertEqual($Post->Author['acnt_name'], $author['acnt_name']);

        $authorid = $Post['blog_authorid'];
        $postid = $Post['blogid'];

        /**
         * test the model iterator
         */
        foreach ($Post as $k => $v) {
            $this->assertEqual($v['blog_authorid'], $v->Author['accountid']);
        }// end foreach

        $Post->clear();

        /**
         * query the author id
         */
        $here = $Post->Author;
        
        $Post->Author->find($authorid);

        Debug::Dump($here->result);

        /**
         * now find posts made by the author id
         */
        $Post->find('*', array(
            'conditions'    => NULL
        ));

        $this->assertEqual($postid, $Post['blogid']);
        $this->assertEqual($authorid, $Post['blog_authorid']);

        /**
         * delete the post
         */
        $Post->remove();
        $Post->clear();

        /**
         * author id should still exists
         */
        $Post->Author->find($authorid);
        $this->assertTrue(!empty($Post->Author->result));
        

        /**
         * post find should not find any posts by the author id
         */
        $is_found = true;
        try {
            $Post->find('*', array(
                'conditions'    => NULL
            ));
        } catch (Exception $exc) {
            Debug::Dump($exc->getMessages());
            $is_found = false;
        }//end try

        $this->assertTrue(is_null($Post->result));
        $this->assertFalse($is_found);

    }

}

